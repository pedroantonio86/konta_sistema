<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Roteador por lista branca (whitelist).
 *
 * Por quê: a URL (?url=empresas/index) só serve de CHAVE para procurar a rota
 * em app/rotas.php. Nunca fazemos new $_GET['controller'], então ninguém
 * consegue executar uma classe ou método que não esteja listado.
 * Cada rota também declara os perfis que podem acessá-la (aplicado na T07).
 */
final class Router
{
    public const ROTA_PADRAO = 'painel/index';

    /**
     * @param array<string, array<string, array{0: class-string, 1: string, 2: list<string>}>> $rotas
     *        formato: ['GET' => ['empresas/index' => [EmpresaController::class, 'index', ['contador']]]]
     */
    public function __construct(private readonly array $rotas)
    {
    }

    public function despachar(string $metodo, string $url): void
    {
        $url = self::normalizar($url);
        $rota = $this->rotas[$metodo][$url] ?? null;

        if ($rota === null) {
            $this->responderRotaInexistente($url);
            return;
        }

        [$classe, $acao, $perfis] = $rota;

        if (!$this->autorizado($perfis)) {
            return;
        }

        // Envio acima do post_max_size do PHP: o PHP descarta o formulário
        // INTEIRO (inclusive o token CSRF). Sem esta checagem o usuário veria
        // "Sessão expirada" em vez do motivo real.
        if ($metodo === 'POST' && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            View::erro(413, null, 'Arquivo grande demais', 'O envio passou do limite. Envie arquivos de até 5 MB.');
            return;
        }

        // CSRF checado aqui, antes de qualquer controller: impossível esquecer em um POST.
        if ($metodo === 'POST' && !Csrf::validar($_POST[Csrf::CAMPO] ?? null)) {
            View::erroCsrf();
            return;
        }

        (new $classe())->$acao();
    }

    /**
     * Controle de acesso por perfil, num lugar só:
     * sem login -> vai para a tela de login; perfil errado -> 403.
     * (Se o registro pertence à empresa do cliente é checado no controller,
     * porque só ele sabe de qual registro se trata.)
     *
     * @param list<string> $perfis
     */
    private function autorizado(array $perfis): bool
    {
        if (in_array(Auth::PUBLICO, $perfis, true)) {
            return true;
        }

        if (!Auth::logado()) {
            header('Location: ' . url('auth/login'));
            return false;
        }

        if (!Auth::perfilPermitido($perfis)) {
            View::erro(403);
            return false;
        }

        return true;
    }

    /** ' /Empresas/Index/ ' -> 'empresas/index'; vazio -> rota padrão. */
    public static function normalizar(string $url): string
    {
        $url = strtolower(trim($url, " \t\n\r\0\x0B/"));

        return $url === '' ? self::ROTA_PADRAO : $url;
    }

    /**
     * 405 se a rota existe em outro método (ex.: GET em empresas/excluir,
     * que só aceita POST); 404 se não existe em nenhum.
     */
    private function responderRotaInexistente(string $url): void
    {
        $permitidos = [];
        foreach ($this->rotas as $metodo => $rotasDoMetodo) {
            if (isset($rotasDoMetodo[$url])) {
                $permitidos[] = $metodo;
            }
        }

        if ($permitidos === []) {
            View::erro(404);
            return;
        }

        header('Allow: ' . implode(', ', $permitidos));
        View::erro(405);
    }
}
