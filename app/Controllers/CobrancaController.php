<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Cobranca;
use App\Models\Empresa;
use App\Services\Semaforo;
use App\Services\WhatsAppLink;

/**
 * Central de cobrança (só contador, garantido pela tabela de rotas):
 * uma linha por empresa com tudo que ela deve, e UM botão de WhatsApp
 * por empresa com todas as guias numa mensagem só.
 */
final class CobrancaController extends Controller
{
    private Cobranca $cobrancas;

    public function __construct()
    {
        $this->cobrancas = new Cobranca();
    }

    public function index(): void
    {
        $ultimas = $this->cobrancas->ultimaPorEmpresa();
        $grupos = [];

        foreach ($this->cobrancas->guiasACobrar() as $guia) {
            $id = (int) $guia['empresa_id'];
            $grupos[$id] ??= [
                'empresa'  => $guia,
                'guias'    => [],
                'total'    => 0.0,
                'vencidas' => 0,
                'ultima'   => $ultimas[$id] ?? null,
            ];
            $grupos[$id]['guias'][] = $guia;
            $grupos[$id]['total'] += (float) $guia['valor'];
            if (Semaforo::calcular($guia['status'], $guia['vencimento']) === Semaforo::VENCIDA) {
                $grupos[$id]['vencidas']++;
            }
        }

        // Quem tem guia vencida primeiro; depois, o maior valor.
        usort($grupos, static fn (array $a, array $b): int =>
            [$b['vencidas'] > 0, $b['total']] <=> [$a['vencidas'] > 0, $a['total']]);

        $this->render('cobranca/index', [
            'titulo' => 'Central de cobrança',
            'grupos' => $grupos,
            'total'  => array_sum(array_column($grupos, 'total')),
        ]);
    }

    /**
     * Registra a cobrança e manda o navegador para o WhatsApp.
     * O link é montado AQUI, com os dados do banco: o formulário só envia
     * o id da empresa. Assim ninguém consegue usar esta rota para mandar o
     * usuário a um endereço qualquer (open redirect).
     */
    public function cobrar(): void
    {
        $empresaId = $this->idOu404($_POST, 'empresa_id');
        $empresa = (new Empresa())->buscar($empresaId) ?? $this->naoEncontrado();
        $guias = $this->cobrancas->guiasACobrar($empresaId);

        if ($guias === []) {
            $this->flash('info', 'Nenhuma guia vencida ou vencendo em breve para esta empresa.');
            $this->redirect('obrigacoes/cobranca');
        }

        $link = WhatsAppLink::paraCobranca($empresa, $guias);
        if ($link === null) {
            $this->flash('danger', 'Empresa sem WhatsApp válido cadastrado. Edite a empresa para cobrar por aqui.');
            $this->redirect('obrigacoes/cobranca');
        }

        $this->cobrancas->registrar($empresaId, Auth::usuario()['id']);

        header('Location: ' . $link);
        exit;
    }
}
