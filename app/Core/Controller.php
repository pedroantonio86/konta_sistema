<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base dos controllers: atalhos para renderizar, redirecionar e avisar.
 */
abstract class Controller
{
    /** @param array<string, mixed> $dados */
    protected function render(string $view, array $dados = []): void
    {
        View::render($view, $dados);
    }

    /**
     * Redireciona e encerra. Usado após POST com sucesso (Post/Redirect/Get):
     * assim o F5 do usuário não reenvia o formulário.
     *
     * @param array<string, scalar> $params
     */
    protected function redirect(string $rota, array $params = []): never
    {
        header('Location: ' . url($rota, $params));
        exit;
    }

    /**
     * Lê um id positivo de $_GET/$_POST; se inválido, responde 404 e encerra.
     *
     * @param array<string, mixed> $origem
     */
    protected function idOu404(array $origem, string $campo = 'id'): int
    {
        $id = filter_var($origem[$campo] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($id === false) {
            $this->naoEncontrado();
        }

        return $id;
    }

    /**
     * Cliente só acessa registros da PRÓPRIA empresa; contador acessa todos.
     * Checado aqui (e não no Router) porque só o controller sabe de qual
     * empresa é o registro pedido. Sem isso, bastaria trocar o id na URL.
     */
    protected function autorizarEmpresa(int $empresaId): void
    {
        if (!Auth::ehContador() && Auth::empresaId() !== $empresaId) {
            View::erro(403);
            exit;
        }
    }

    protected function naoEncontrado(): never
    {
        View::erro(404);
        exit;
    }

    /** Mensagem exibida uma única vez na próxima página (tipo = cor do Bootstrap). */
    protected function flash(string $tipo, string $mensagem): void
    {
        $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
    }
}
