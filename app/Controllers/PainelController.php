<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Empresa;
use App\Models\Painel;

/**
 * Painel inicial: indicadores, gráficos e as próximas guias a vencer.
 * Contador vê o escritório inteiro (e o ranking de empresas); cliente vê
 * só a própria empresa (empresa vinda da sessão) e não vê o ranking.
 */
final class PainelController extends Controller
{
    public function index(): void
    {
        $ehContador = Auth::ehContador();
        $empresaId = $ehContador ? null : Auth::empresaId();
        $painel = new Painel();

        $this->render('painel/index', [
            'titulo'      => 'Painel',
            'ehContador'  => $ehContador,
            'empresa'     => $empresaId === null ? null : (new Empresa())->buscar($empresaId),
            'indicadores' => $painel->indicadores($empresaId),
            'situacoes'   => $painel->porSituacao($empresaId),
            'ranking'     => $ehContador ? $painel->rankingVencidas() : [],
            'semanas'     => $painel->vencimentosPorSemana($empresaId),
            'proximas'    => $painel->proximas($empresaId),
        ]);
    }
}
