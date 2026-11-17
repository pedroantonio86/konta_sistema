<?php

declare(strict_types=1);

/**
 * Tabela de rotas (lista branca). Só o que estiver aqui pode ser acessado.
 * Formato: 'controller/acao' => [Classe::class, 'metodo', [perfis permitidos]]
 * Perfis: Auth::PUBLICO (sem login), Auth::CONTADOR, Auth::CLIENTE.
 */

use App\Controllers\AuthController;
use App\Controllers\AvaliacaoController;
use App\Controllers\AvisoController;
use App\Controllers\CobrancaController;
use App\Controllers\DocumentoController;
use App\Controllers\EmpresaController;
use App\Controllers\ObrigacaoController;
use App\Controllers\PainelController;
use App\Core\Auth;

$publico  = [Auth::PUBLICO];
$logados  = [Auth::CONTADOR, Auth::CLIENTE];
$contador = [Auth::CONTADOR];

return [
    'GET' => [
        'auth/login'      => [AuthController::class, 'formulario', $publico],
        'painel/index'    => [PainelController::class, 'index', $logados],

        'empresas/index'  => [EmpresaController::class, 'index', $contador],
        'empresas/ver'    => [EmpresaController::class, 'ver', $contador],
        'empresas/criar'  => [EmpresaController::class, 'criar', $contador],
        'empresas/editar' => [EmpresaController::class, 'editar', $contador],

        // cliente também acessa: o controller limita à empresa dele
        'obrigacoes/index'  => [ObrigacaoController::class, 'index', $logados],
        'obrigacoes/ver'    => [ObrigacaoController::class, 'ver', $logados],
        'obrigacoes/exportar' => [ObrigacaoController::class, 'exportar', $logados],
        'obrigacoes/agenda'   => [ObrigacaoController::class, 'agenda', $logados],
        'obrigacoes/cobranca' => [CobrancaController::class, 'index', $contador],
        'obrigacoes/criar'  => [ObrigacaoController::class, 'criar', $contador],
        'obrigacoes/editar' => [ObrigacaoController::class, 'editar', $contador],

        'documentos/baixar' => [DocumentoController::class, 'baixar', $logados],

        'avaliacoes/criar'  => [AvaliacaoController::class, 'criar', $logados],
        'avaliacoes/index'  => [AvaliacaoController::class, 'index', $contador],
    ],
    'POST' => [
        'auth/login'        => [AuthController::class, 'entrar', $publico],
        'auth/logout'       => [AuthController::class, 'sair', $logados],

        'empresas/salvar'    => [EmpresaController::class, 'salvar', $contador],
        'empresas/atualizar' => [EmpresaController::class, 'atualizar', $contador],
        'empresas/excluir'   => [EmpresaController::class, 'excluir', $contador],
        'empresas/acesso'    => [EmpresaController::class, 'criarAcesso', $contador],

        'obrigacoes/salvar'    => [ObrigacaoController::class, 'salvar', $contador],
        'obrigacoes/atualizar' => [ObrigacaoController::class, 'atualizar', $contador],
        'obrigacoes/excluir'   => [ObrigacaoController::class, 'excluir', $contador],
        'obrigacoes/pagar'     => [ObrigacaoController::class, 'pagar', $logados],
        'obrigacoes/reabrir'   => [ObrigacaoController::class, 'reabrir', $contador],
        'obrigacoes/cobrar'    => [CobrancaController::class, 'cobrar', $contador],

        'documentos/enviar'    => [DocumentoController::class, 'enviar', $logados],

        'avaliacoes/salvar'    => [AvaliacaoController::class, 'salvar', $logados],

        'avisos/fechar'        => [AvisoController::class, 'fechar', [Auth::CLIENTE]],
    ],
];
