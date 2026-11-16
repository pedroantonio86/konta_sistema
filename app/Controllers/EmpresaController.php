<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginacao;
use App\Core\Validator;
use App\Models\Empresa;
use App\Models\Usuario;

/**
 * CRUD de empresas (somente contador: garantido pela tabela de rotas).
 */
final class EmpresaController extends Controller
{
    private const CAMPOS = ['razao_social', 'nome_fantasia', 'cnpj', 'email', 'whatsapp', 'regime'];

    private Empresa $empresas;
    private Usuario $usuarios;

    public function __construct()
    {
        $this->empresas = new Empresa();
        $this->usuarios = new Usuario();
    }

    public function index(): void
    {
        $busca = Validator::limpar($_GET, ['busca'])['busca'];
        $paginacao = new Paginacao($this->empresas->contar($busca), $_GET['pagina'] ?? 1);

        $this->render('empresas/index', [
            'titulo'    => 'Empresas',
            'busca'     => $busca,
            'empresas'  => $this->empresas->listar($busca, $paginacao->porPagina, $paginacao->offset),
            'paginacao' => $paginacao,
            'filtros'   => array_filter(['busca' => $busca]),
        ]);
    }

    public function ver(): void
    {
        $this->exibirDetalhes($this->buscarOu404($this->idOu404($_GET)));
    }

    /** Contador cria o login do cliente desta empresa (formulário na página da empresa). */
    public function criarAcesso(): void
    {
        $empresa = $this->buscarOu404($this->idOu404($_POST, 'empresa_id'));
        $dados = Validator::limpar($_POST, ['nome', 'email', 'senha']);

        $v = (new Validator($dados))
            ->obrigatorio('nome')->tamanho('nome', 3, 100)
            ->obrigatorio('email')->email('email')
            ->obrigatorio('senha')->tamanho('senha', 8, 72);

        // bcrypt só considera os primeiros 72 BYTES; acentos ocupam 2 bytes cada.
        if (strlen($dados['senha']) > 72) {
            $v->adicionarErro('senha', 'Senha longa demais.');
        }
        if (!isset($v->erros()['email']) && $this->usuarios->emailExiste($dados['email'])) {
            $v->adicionarErro('email', 'E-mail já usado por outro usuário.');
        }

        if (!$v->valido()) {
            unset($dados['senha']); // a senha nunca volta para a tela
            $this->exibirDetalhes($empresa, $dados, $v->erros());
            return;
        }

        $this->usuarios->criarCliente((int) $empresa['id'], $dados['nome'], $dados['email'], $dados['senha']);
        $this->flash('success', "Acesso criado para {$dados['nome']}. Envie o e-mail e a senha ao cliente.");
        $this->redirect('empresas/ver', ['id' => $empresa['id']]);
    }

    public function criar(): void
    {
        $this->exibirFormulario(array_fill_keys(self::CAMPOS, ''));
    }

    public function salvar(): void
    {
        $dados = Validator::limpar($_POST, self::CAMPOS);
        $v = $this->validar($dados);

        if (!$v->valido()) {
            $this->exibirFormulario($dados, $v->erros());
            return;
        }

        $id = $this->empresas->criar($dados);
        $this->flash('success', 'Empresa cadastrada.');
        $this->redirect('empresas/ver', ['id' => $id]);
    }

    public function editar(): void
    {
        $empresa = $this->buscarOu404($this->idOu404($_GET));

        $dados = array_map(static fn ($valor): string => (string) $valor, array_intersect_key($empresa, array_flip(self::CAMPOS)));
        $dados['cnpj'] = cnpj_formatado($dados['cnpj']);
        $dados['whatsapp'] = telefone_formatado($dados['whatsapp']);

        $this->exibirFormulario($dados, [], (int) $empresa['id']);
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

        $this->empresas->atualizar($id, $dados);
        $this->flash('success', 'Empresa atualizada.');
        $this->redirect('empresas/ver', ['id' => $id]);
    }

    public function excluir(): void
    {
        $id = $this->idOu404($_POST);
        $empresa = $this->buscarOu404($id);

        $total = $this->empresas->contarObrigacoes($id);
        if ($total > 0) {
            // Histórico fiscal não é apagado em cascata (ON DELETE RESTRICT).
            $this->flash('danger', "Não é possível excluir: a empresa possui {$total} obrigação(ões). Exclua-as antes.");
            $this->redirect('empresas/ver', ['id' => $id]);
        }

        $this->empresas->excluir($id);
        $this->flash('success', 'Empresa "' . $empresa['razao_social'] . '" excluída.');
        $this->redirect('empresas/index');
    }

    /** @param array<string, string> $dados */
    private function validar(array $dados, ?int $id = null): Validator
    {
        $v = (new Validator($dados))
            ->obrigatorio('razao_social')->tamanho('razao_social', 3, 150)
            ->tamanho('nome_fantasia', 0, 150)
            ->obrigatorio('cnpj')->cnpj('cnpj')
            ->email('email')
            ->whatsapp('whatsapp')
            ->obrigatorio('regime')->emLista('regime', array_keys(Empresa::REGIMES));

        // Unicidade só o banco sabe: checada depois que o formato está correto.
        if (!isset($v->erros()['cnpj']) && $this->empresas->cnpjExiste(Validator::normalizarCnpj($dados['cnpj']), $id)) {
            $v->adicionarErro('cnpj', 'CNPJ já cadastrado.');
        }

        return $v;
    }

    /**
     * Página da empresa: dados, obrigações e logins de cliente.
     *
     * @param array<string, mixed>  $empresa
     * @param array<string, string> $acesso      valores do formulário de acesso (mantidos se houver erro)
     * @param array<string, string> $errosAcesso
     */
    private function exibirDetalhes(array $empresa, array $acesso = [], array $errosAcesso = []): void
    {
        $this->render('empresas/ver', [
            'titulo'      => $empresa['razao_social'],
            'empresa'     => $empresa,
            'obrigacoes'  => $this->empresas->contarObrigacoes((int) $empresa['id']),
            'usuarios'    => $this->usuarios->listarPorEmpresa((int) $empresa['id']),
            'acesso'      => $acesso + ['nome' => '', 'email' => ''],
            'errosAcesso' => $errosAcesso,
        ]);
    }

    /** @return array<string, mixed> */
    private function buscarOu404(int $id): array
    {
        return $this->empresas->buscar($id) ?? $this->naoEncontrado();
    }

    /**
     * @param array<string, string> $dados
     * @param array<string, string> $erros
     */
    private function exibirFormulario(array $dados, array $erros = [], ?int $id = null): void
    {
        $this->render('empresas/form', [
            'titulo' => $id === null ? 'Nova empresa' : 'Editar empresa',
            'dados'  => $dados,
            'erros'  => $erros,
            'id'     => $id,
        ]);
    }
}
