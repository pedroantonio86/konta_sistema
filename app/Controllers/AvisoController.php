<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;

/**
 * Faixa de aviso do cliente (guias vencidas e a vencer, no topo do layout).
 */
final class AvisoController extends Controller
{
    /** Fecha a faixa até o próximo login (guardado na sessão). */
    public function fechar(): void
    {
        $_SESSION[Auth::AVISO_FECHADO] = true;

        header('Location: ' . $this->voltarPara($_POST['voltar'] ?? null));
        exit;
    }

    /**
     * Volta para a página onde o cliente estava, MAS só se for uma página
     * do próprio Konta. Um endereço externo no campo "voltar" (open
     * redirect) mandaria o usuário para um site falso: nesse caso, painel.
     */
    private function voltarPara(mixed $voltar): string
    {
        $base = (string) Config::obter('app.base_url', '/');

        $interno = is_string($voltar)
            && str_starts_with($voltar, $base)
            && !str_contains($voltar, '//')
            && !preg_match('/[\r\n\\\\]/', $voltar);

        return $interno ? $voltar : url('painel/index');
    }
}
