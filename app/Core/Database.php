<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDO;

/**
 * Conexão PDO única por requisição (aberta só quando alguém precisa).
 *
 * Decisões de segurança/consistência:
 * - ERRMODE_EXCEPTION: erro de SQL vira exceção, nunca falha silenciosa.
 * - EMULATE_PREPARES = false: o MySQL recebe o SQL e os valores SEPARADOS
 *   (prepared statement real), o que elimina SQL Injection e faz LIMIT/OFFSET
 *   aceitarem parâmetros inteiros.
 * - time_zone da sessão = fuso do PHP: CURDATE() e date() concordam, então o
 *   semáforo (PHP) e os filtros/painel (SQL) nunca divergem perto da meia-noite.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function conexao(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::conectar();
        }

        return self::$pdo;
    }

    private static function conectar(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            Config::obter('db.host'),
            (int) Config::obter('db.porta', 3306),
            Config::obter('db.nome'),
            Config::obter('db.charset', 'utf8mb4')
        );

        $pdo = new PDO($dsn, Config::obter('db.usuario'), Config::obter('db.senha'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Ex.: "-03:00", calculado a partir do fuso configurado no PHP.
        $deslocamento = (new DateTimeImmutable())->format('P');
        $pdo->prepare('SET time_zone = ?')->execute([$deslocamento]);

        return $pdo;
    }
}
