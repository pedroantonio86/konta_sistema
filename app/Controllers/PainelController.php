<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Empresa;
use App\Models\Painel;

/**
 * Painel inicial: indicadores de prazos e as próximas guias a vencer.
 * Contador vê o escritório inteiro; cliente vê só a própria empresa
 * (empresa vinda da sessão, como na listagem de obrigações).
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
            'proximas'    => $painel->proximas($empresaId),
        ]);
    }
}
