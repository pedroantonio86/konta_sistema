<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Guarda a configuração carregada de config/config.php.
 * Acesso por "caminho com ponto": Config::obter('db.nome').
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $dados = [];

    /** @param array<string, mixed> $dados */
    public static function carregar(array $dados): void
    {
        self::$dados = $dados;
    }

    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        $valor = self::$dados;

        foreach (explode('.', $chave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $padrao;
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }
}
