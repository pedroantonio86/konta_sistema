<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Renderiza views PHP dentro do layout.
 * As views só exibem dados: toda saída de texto passa por e() (anti-XSS).
 */
final class View
{
    private const PASTA = __DIR__ . '/../Views/';

    /** Mensagens padrão das páginas de erro, por código HTTP. */
    private const ERROS = [
        403 => ['Acesso negado', 'Você não tem permissão para acessar esta página.'],
        404 => ['Página não encontrada', 'O endereço acessado não existe.'],
        405 => ['Método não permitido', 'Esta ação não pode ser feita por este caminho.'],
        500 => ['Erro interno', 'Algo deu errado. Tente novamente em instantes.'],
    ];

    /**
     * @param string               $view  caminho relativo a app/Views, sem ".php" (ex.: 'empresas/index')
     * @param array<string, mixed> $dados variáveis disponíveis na view
     */
    public static function render(string $view, array $dados = []): void
    {
        $conteudo = self::renderizarArquivo(self::PASTA . $view . '.php', $dados);

        echo self::renderizarArquivo(self::PASTA . 'layout.php', $dados + ['conteudo' => $conteudo]);
    }

    /**
     * Página de erro do token CSRF inválido.
     * Por quê 403 e não 419: 419 não é código HTTP padrão (é invenção do
     * Laravel) e o Apache o transforma em 500. 403 = "requisição recusada".
     */
    public static function erroCsrf(): void
    {
        self::erro(403, null, 'Sessão expirada', 'O formulário expirou. Recarregue a página e tente novamente.');
    }

    /** Envia o código HTTP e mostra a página de erro padrão. */
    public static function erro(int $codigo, ?string $detalhe = null, ?string $titulo = null, ?string $mensagem = null): void
    {
        [$tituloPadrao, $mensagemPadrao] = self::ERROS[$codigo] ?? self::ERROS[500];
        $titulo ??= $tituloPadrao;
        $mensagem ??= $mensagemPadrao;

        http_response_code($codigo);
        self::render('erros/erro', [
            'titulo'   => $titulo,
            'codigo'   => $codigo,
            'mensagem' => $mensagem,
            'detalhe'  => $detalhe,
        ]);
    }

    /**
     * Executa o arquivo da view num escopo isolado e devolve o HTML gerado.
     * Por quê isolado: as variáveis da view não colidem com as desta classe.
     *
     * @param array<string, mixed> $__dados
     */
    private static function renderizarArquivo(string $__arquivo, array $__dados): string
    {
        extract($__dados, EXTR_SKIP);
        ob_start();
        require $__arquivo;

        return (string) ob_get_clean();
    }
}
