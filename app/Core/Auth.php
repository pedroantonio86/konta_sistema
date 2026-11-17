<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Quem está logado (guardado na sessão) e o que o perfil pode acessar.
 * Só dados mínimos vão para a sessão: nunca o hash da senha.
 */
final class Auth
{
    public const PUBLICO = 'publico';
    public const CONTADOR = 'contador';
    public const CLIENTE = 'cliente';

    /** Chave da sessão: o cliente fechou a faixa de aviso de guias. */
    public const AVISO_FECHADO = 'aviso_fechado';

    /** @param array{id: int, nome: string, perfil: string, empresa_id: ?int} $usuario */
    public static function entrar(array $usuario): void
    {
        // Troca o ID da sessão no login: se um atacante "plantou" um ID
        // conhecido no navegador da vítima (session fixation), ele perde a validade.
        session_regenerate_id(true);

        // A cada login a faixa de aviso volta a aparecer.
        unset($_SESSION[self::AVISO_FECHADO]);

        $_SESSION['usuario'] = [
            'id'         => (int) $usuario['id'],
            'nome'       => $usuario['nome'],
            'perfil'     => $usuario['perfil'],
            'empresa_id' => $usuario['empresa_id'] === null ? null : (int) $usuario['empresa_id'],
        ];

        Csrf::renovar();
    }

    /** Limpa os dados e troca o ID; a sessão nova continua viva só para a mensagem flash. */
    public static function sair(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        Csrf::renovar();
    }

    /** @return array{id: int, nome: string, perfil: string, empresa_id: ?int}|null */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function logado(): bool
    {
        return self::usuario() !== null;
    }

    public static function ehContador(): bool
    {
        return (self::usuario()['perfil'] ?? null) === self::CONTADOR;
    }

    /** Empresa do cliente logado (null para o contador, que vê todas). */
    public static function empresaId(): ?int
    {
        return self::usuario()['empresa_id'] ?? null;
    }

    /** @param list<string> $perfis perfis permitidos na rota */
    public static function perfilPermitido(array $perfis): bool
    {
        $perfil = self::usuario()['perfil'] ?? null;

        return $perfil !== null && in_array($perfil, $perfis, true);
    }
}
