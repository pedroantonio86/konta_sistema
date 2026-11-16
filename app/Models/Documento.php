<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Documento extends Model
{
    /** @return list<array<string, mixed>> documentos da obrigação, com o nome de quem enviou */
    public function listarPorObrigacao(int $obrigacaoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.id, d.nome_original, d.tamanho, d.enviado_em, u.nome AS enviado_por
               FROM documentos d
          LEFT JOIN usuarios u ON u.id = d.usuario_id
              WHERE d.obrigacao_id = ?
              ORDER BY d.enviado_em DESC, d.id DESC'
        );
        $stmt->execute([$obrigacaoId]);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null documento + empresa_id da obrigação (para autorizar) */
    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, o.empresa_id
               FROM documentos d
               JOIN obrigacoes o ON o.id = d.obrigacao_id
              WHERE d.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** @param array{arquivo: string, mime: string, tamanho: int, nome_original: string} $arquivo */
    public function criar(int $obrigacaoId, int $usuarioId, array $arquivo): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO documentos (obrigacao_id, usuario_id, nome_original, arquivo, mime, tamanho)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $obrigacaoId, $usuarioId, $arquivo['nome_original'],
            $arquivo['arquivo'], $arquivo['mime'], $arquivo['tamanho'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return list<string> nomes dos arquivos físicos da obrigação (para apagar do disco) */
    public function arquivosDaObrigacao(int $obrigacaoId): array
    {
        $stmt = $this->db->prepare('SELECT arquivo FROM documentos WHERE obrigacao_id = ?');
        $stmt->execute([$obrigacaoId]);

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
