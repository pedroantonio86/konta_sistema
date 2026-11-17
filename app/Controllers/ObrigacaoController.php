<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginacao;
use App\Core\Validator;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Obrigacao;
use App\Services\Agenda;
use App\Services\Planilha;
use App\Services\Semaforo;
use App\Services\Upload;

/**
 * CRUD de obrigações (guias e declarações) com filtros e semáforo.
 * Contador: tudo. Cliente: só listar e ver as da própria empresa.
 */
final class ObrigacaoController extends Controller
{
    private const CAMPOS = ['empresa_id', 'tipo', 'competencia', 'valor', 'vencimento', 'observacao'];

    private Obrigacao $obrigacoes;
    private Empresa $empresas;

    public function __construct()
    {
        $this->obrigacoes = new Obrigacao();
        $this->empresas = new Empresa();
    }

    public function index(): void
    {
        $ehContador = Auth::ehContador();
        [$filtros, $filtrosLink] = $this->filtrosDoUsuario();

        $paginacao = new Paginacao($this->obrigacoes->contar($filtros), $_GET['pagina'] ?? 1);

        $this->render('obrigacoes/index', [
            'titulo'      => 'Obrigações',
            'filtros'     => $filtros,
            'obrigacoes'  => $this->obrigacoes->listar($filtros, $paginacao->porPagina, $paginacao->offset),
            'paginacao'   => $paginacao,
            'empresas'    => $ehContador ? $this->empresas->listarParaSelect() : [],
            'filtrosLink' => $filtrosLink,
            'ehContador'  => $ehContador,
        ]);
    }

    /**
     * Baixa em CSV exatamente o que a lista mostra com os filtros atuais,
     * sem paginação. Leitura apenas (GET): por isso não leva token CSRF.
     */
    public function exportar(): void
    {
        [$filtros] = $this->filtrosDoUsuario();
        $obrigacoes = $this->obrigacoes->listarTodas($filtros);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="obrigacoes-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: private, no-store');

        $saida = fopen('php://output', 'w');
        Planilha::escrever(
            $saida,
            ['Empresa', 'CNPJ', 'Tipo', 'Competência', 'Valor (R$)', 'Vencimento', 'Situação', 'Pago em', 'Observação'],
            array_map(static fn (array $o): array => [
                $o['razao_social'],
                cnpj_formatado($o['cnpj']),
                Obrigacao::TIPOS[$o['tipo']],
                competencia_br($o['competencia']),
                number_format((float) $o['valor'], 2, ',', ''), // sem ponto de milhar: o Excel lê como número
                data_br($o['vencimento']),
                Semaforo::rotulo(Semaforo::calcular($o['status'], $o['vencimento'])),
                $o['pago_em'] === null ? '' : data_br($o['pago_em']),
                $o['observacao'] ?? '',
            ], $obrigacoes)
        );
        fclose($saida);
        exit;
    }

    /**
     * Baixa um .ics com as guias PENDENTES dos filtros atuais, para importar
     * na agenda do celular. Mesmos filtros e mesmo escopo da planilha.
     */
    public function agenda(): void
    {
        [$filtros] = $this->filtrosDoUsuario();
        $pendentes = array_values(array_filter(
            $this->obrigacoes->listarTodas($filtros),
            static fn (array $o): bool => $o['status'] === 'pendente'
        ));

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="konta-vencimentos.ics"');
        header('Cache-Control: private, no-store');

        echo Agenda::gerar($pendentes, new \DateTimeImmutable());
        exit;
    }

    public function ver(): void
    {
        $obrigacao = $this->buscarOu404($this->idOu404($_GET));
        $this->autorizarEmpresa((int) $obrigacao['empresa_id']);

        $this->render('obrigacoes/ver', [
            'titulo'     => Obrigacao::TIPOS[$obrigacao['tipo']] . ' ' . competencia_br($obrigacao['competencia']),
            'obrigacao'  => $obrigacao,
            'situacao'   => Semaforo::calcular($obrigacao['status'], $obrigacao['vencimento']),
            'ehContador' => Auth::ehContador(),
            'documentos' => (new Documento())->listarPorObrigacao((int) $obrigacao['id']),
        ]);
    }

    public function criar(): void
    {
        $dados = array_fill_keys(self::CAMPOS, '');
        // "Nova obrigação" a partir da página da empresa já vem com ela selecionada
        $dados['empresa_id'] = Validator::limpar($_GET, ['empresa_id'])['empresa_id'];

        $this->exibirFormulario($dados);
    }

    public function salvar(): void
    {
        $dados = Validator::limpar($_POST, self::CAMPOS);
        $v = $this->validar($dados);

        if (!$v->valido()) {
            $this->exibirFormulario($dados, $v->erros());
            return;
        }

        $id = $this->obrigacoes->criar($dados);
        $this->flash('success', 'Obrigação cadastrada.');
        $this->redirect('obrigacoes/ver', ['id' => $id]);
    }

    public function editar(): void
    {
        $obrigacao = $this->buscarOu404($this->idOu404($_GET));

        $this->exibirFormulario([
            'empresa_id'  => (string) $obrigacao['empresa_id'],
            'tipo'        => $obrigacao['tipo'],
            'competencia' => substr($obrigacao['competencia'], 0, 7),        // '2026-10-01' -> '2026-10'
            'valor'       => number_format((float) $obrigacao['valor'], 2, ',', '.'),
            'vencimento'  => $obrigacao['vencimento'],
            'observacao'  => (string) $obrigacao['observacao'],
        ], [], (int) $obrigacao['id']);
    }

    public function atualizar(): void
    {
        $id = $this->idOu404($_POST);
        $this->buscarOu404($id);

        $dados = Validator::limpar($_POST, self::CAMPOS);
        $v = $this->validar($dados, $id);

        if (!$v->valido()) {
            $this->exibirFormulario($dados, $v->erros(), $id);
            return;
        }

        $this->obrigacoes->atualizar($id, $dados);
        $this->flash('success', 'Obrigação atualizada.');
        $this->redirect('obrigacoes/ver', ['id' => $id]);
    }

    /** Contador ou cliente (da própria empresa) informa que a guia foi paga. */
    public function pagar(): void
    {
        $id = $this->idOu404($_POST);
        $obrigacao = $this->buscarOu404($id);
        $this->autorizarEmpresa((int) $obrigacao['empresa_id']);

        $nome = Obrigacao::TIPOS[$obrigacao['tipo']] . ' ' . competencia_br($obrigacao['competencia']);

        if ($this->obrigacoes->marcarPaga($id)) {
            $this->flash('success', "{$nome} marcada como paga.");
        } else {
            $this->flash('warning', "{$nome} já estava paga.");
        }

        $this->redirect('obrigacoes/ver', ['id' => $id]);
    }

    /** Só o contador (rota): volta uma guia paga para pendente. */
    public function reabrir(): void
    {
        $id = $this->idOu404($_POST);
        $obrigacao = $this->buscarOu404($id);

        if ($this->obrigacoes->reabrir($id)) {
            $this->flash('success', sprintf(
                '%s %s reaberta: voltou a pendente.',
                Obrigacao::TIPOS[$obrigacao['tipo']],
                competencia_br($obrigacao['competencia'])
            ));
        } else {
            $this->flash('warning', 'Não está paga.');
        }

        $this->redirect('obrigacoes/ver', ['id' => $id]);
    }

    public function excluir(): void
    {
        $id = $this->idOu404($_POST);
        $obrigacao = $this->buscarOu404($id);

        // O CASCADE do banco apaga as LINHAS de documentos, mas não os arquivos
        // do disco: guardamos os nomes antes e apagamos depois do DELETE.
        $arquivos = (new Documento())->arquivosDaObrigacao($id);
        $this->obrigacoes->excluir($id);
        array_map([Upload::class, 'apagar'], $arquivos);

        $this->flash('success', sprintf(
            'Obrigação %s %s excluída.',
            Obrigacao::TIPOS[$obrigacao['tipo']],
            competencia_br($obrigacao['competencia'])
        ));
        $this->redirect('obrigacoes/index');
    }

    /**
     * Filtros da listagem/planilha já com o escopo do perfil aplicado.
     * Cliente: a empresa vem da SESSÃO, nunca do GET. Mesmo que ele digite
     * ?empresa_id=3 na URL, só verá (e exportará) a própria empresa.
     *
     * @return array{0: array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string}, 1: array<string, string>}
     *         [filtros para a consulta, filtros preenchidos para os links]
     */
    private function filtrosDoUsuario(): array
    {
        $filtros = $this->lerFiltros();
        $filtrosLink = array_filter(array_map('strval', $filtros));

        if (!Auth::ehContador()) {
            $filtros['empresa_id'] = Auth::empresaId();
            unset($filtrosLink['empresa_id']);
        }

        return [$filtros, $filtrosLink];
    }

    /**
     * Filtros da listagem vindos do GET. Valor fora do permitido é
     * descartado (vira "sem filtro") em vez de gerar erro.
     *
     * @return array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string}
     */
    private function lerFiltros(): array
    {
        $f = Validator::limpar($_GET, ['busca', 'empresa_id', 'tipo', 'situacao', 'competencia']);
        $empresaId = filter_var($f['empresa_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'busca'       => $f['busca'],
            'empresa_id'  => $empresaId === false ? null : $empresaId,
            'tipo'        => isset(Obrigacao::TIPOS[$f['tipo']]) ? $f['tipo'] : '',
            'situacao'    => isset(Semaforo::opcoes()[$f['situacao']]) ? $f['situacao'] : '',
            'competencia' => Validator::competenciaValida($f['competencia']) ? $f['competencia'] : '',
        ];
    }

    /** @param array<string, string> $dados */
    private function validar(array $dados, ?int $id = null): Validator
    {
        $v = (new Validator($dados))
            ->obrigatorio('empresa_id')->inteiroEntre('empresa_id', 1, PHP_INT_MAX)
            ->obrigatorio('tipo')->emLista('tipo', array_keys(Obrigacao::TIPOS))
            ->obrigatorio('competencia')->competencia('competencia')
            ->obrigatorio('valor')->valor('valor')
            ->obrigatorio('vencimento')->data('vencimento')
            ->tamanho('observacao', 0, 500);

        $erros = $v->erros();

        if (!isset($erros['empresa_id']) && $this->empresas->buscar((int) $dados['empresa_id']) === null) {
            $v->adicionarErro('empresa_id', 'Empresa não encontrada.');
        }

        // Regra de negócio: a guia vence depois do mês a que se refere.
        if (!isset($erros['competencia']) && !isset($erros['vencimento'])
            && $dados['vencimento'] < $dados['competencia'] . '-01') {
            $v->adicionarErro('vencimento', 'O vencimento não pode ser anterior à competência.');
        }

        if ($v->valido() && $this->obrigacoes->existeDuplicada((int) $dados['empresa_id'], $dados['tipo'], $dados['competencia'], $id)) {
            $v->adicionarErro('competencia', sprintf(
                'Já existe %s %s para esta empresa.',
                Obrigacao::TIPOS[$dados['tipo']],
                competencia_br($dados['competencia'] . '-01')
            ));
        }

        return $v;
    }

    /** @return array<string, mixed> */
    private function buscarOu404(int $id): array
    {
        return $this->obrigacoes->buscar($id) ?? $this->naoEncontrado();
    }

    /**
     * @param array<string, string> $dados
     * @param array<string, string> $erros
     */
    private function exibirFormulario(array $dados, array $erros = [], ?int $id = null): void
    {
        $this->render('obrigacoes/form', [
            'titulo'   => $id === null ? 'Nova obrigação' : 'Editar obrigação',
            'dados'    => $dados,
            'erros'    => $erros,
            'id'       => $id,
            'empresas' => $this->empresas->listarParaSelect(),
        ]);
    }
}
