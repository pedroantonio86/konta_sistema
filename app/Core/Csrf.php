<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Proteção contra CSRF (Cross-Site Request Forgery).
 *
 * Ataque: um site malicioso faz o navegador do usuário logado enviar um POST
 * ao Konta (ex.: excluir empresa); o cookie de sessão vai junto sozinho.
 * Defesa: todo formulário leva um token aleatório guardado na sessão. O site
 * atacante não consegue ler esse token, então o POST dele é recusado (403).
 */
final class Csrf
{
    public const CAMPO = '_csrf';
    private const CHAVE_SESSAO = 'csrf_token';

    /** Token da sessão atual (criado na primeira vez: 32 bytes aleatórios = 64 hex). */
    public static function token(): string
    {
        if (empty($_SESSION[self::CHAVE_SESSAO])) {
            $_SESSION[self::CHAVE_SESSAO] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CHAVE_SESSAO];
    }

    /** Campo oculto para colocar dentro de todo <form method="post">. */
    public static function campo(): string
    {
        return '<input type="hidden" name="' . self::CAMPO . '" value="' . self::token() . '">';
    }

    /**
     * Confere o token enviado com o da sessão.
     * hash_equals compara em tempo constante: o tempo de resposta não revela
     * quantos caracteres iniciais o atacante acertou (ataque de tempo).
     */
    public static function validar(mixed $enviado): bool
    {
        $esperado = $_SESSION[self::CHAVE_SESSAO] ?? '';

        if (!is_string($enviado) || !is_string($esperado) || $esperado === '') {
            return false;
        }

        return hash_equals($esperado, $enviado);
    }

    /** Gera token novo (usado no login/logout, junto com session_regenerate_id). */
    public static function renovar(): void
    {
        unset($_SESSION[self::CHAVE_SESSAO]);
        self::token();
    }
}
