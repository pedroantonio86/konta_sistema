<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base dos models: todos usam a mesma conexão PDO.
 * Regra da casa: SQL só com prepared statements (? ou :nome), NUNCA
 * concatenando valor digitado pelo usuário.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::conexao();
    }

    /**
     * Prepara um termo para busca com LIKE ("contém").
     * Escapa % e _ digitados pelo usuário: buscar "50%" procura o texto
     * "50%", e não "começa com 50".
     */
    protected static function termoLike(string $termo): string
    {
        return '%' . addcslashes($termo, '%_\\') . '%';
    }
}
