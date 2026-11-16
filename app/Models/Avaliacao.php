<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Avaliações do teste com usuário real (nota 1–5 + comentário).
 */
final class Avaliacao extends Model
{
    /** Sem paginação (corte aprovado do MVP): mostra as mais recentes. */
    public const LIMITE_LISTA = 100;

    public function criar(int $usuarioId, int $nota, string $comentario): int
    {
        $stmt = $this->db->prepare('INSERT INTO avaliacoes (usuario_id, nota, comentario) VALUES (?, ?, ?)');
        $stmt->execute([$usuarioId, $nota, $comentario !== '' ? $comentario : null]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Média, total e quantas avaliações de cada nota (para as barras).
     *
     * @return array{media: float, total: int, por_nota: array<int, int>}
     */
    public function resumo(): array
    {
        $linhas = $this->db->query('SELECT nota, COUNT(*) AS quantidade FROM avaliacoes GROUP BY nota')->fetchAll();

        $porNota = array_fill_keys([5, 4, 3, 2, 1], 0);
        foreach ($linhas as $linha) {
            $porNota[(int) $linha['nota']] = (int) $linha['quantidade'];
        }

        $total = array_sum($porNota);
        $soma = array_sum(array_map(static fn (int $nota, int $qtd): int => $nota * $qtd, array_keys($porNota), $porNota));

        return [
            'media'    => $total > 0 ? round($soma / $total, 1) : 0.0,
            'total'    => $total,
            'por_nota' => $porNota,
        ];
    }

    /** @return list<array<string, mixed>> mais recentes primeiro, com nome e perfil de quem avaliou */
    public function recentes(): array
    {
        return $this->db->query(
            'SELECT a.nota, a.comentario, a.criado_em, u.nome, u.perfil
               FROM avaliacoes a
          LEFT JOIN usuarios u ON u.id = a.usuario_id
              ORDER BY a.criado_em DESC, a.id DESC
              LIMIT ' . self::LIMITE_LISTA
        )->fetchAll();
    }
}
