<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Linha do tempo de cada guia: quem cadastrou, editou, pagou, reabriu ou
 * anexou documento, e quando. Responde à pergunta "quem marcou como paga?".
 */
final class Historico extends Model
{
    public const CRIADA = 'criada';
    public const EDITADA = 'editada';
    public const PAGA = 'paga';
    public const REABERTA = 'reaberta';
    public const DOCUMENTO = 'documento';

    /** Como cada ação aparece na tela. */
    public const ROTULOS = [
        self::CRIADA    => 'Cadastrada',
        self::EDITADA   => 'Editada',
        self::PAGA      => 'Marcada como paga',
        self::REABERTA  => 'Reaberta',
        self::DOCUMENTO => 'Documento enviado',
    ];

    public function registrar(int $obrigacaoId, ?int $usuarioId, string $acao, ?string $detalhe = null): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO historico (obrigacao_id, usuario_id, acao, detalhe) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $obrigacaoId,
            $usuarioId,
            $acao,
            $detalhe === null || $detalhe === '' ? null : mb_substr($detalhe, 0, 255), // cabe na coluna
        ]);
    }

    /** @return list<array<string, mixed>> mais recentes primeiro, com nome de quem fez */
    public function listarPorObrigacao(int $obrigacaoId): array
    {
        $stmt = $this->db->prepare(
            'SELECT h.acao, h.detalhe, h.criado_em, u.nome, u.perfil
               FROM historico h
          LEFT JOIN usuarios u ON u.id = h.usuario_id
              WHERE h.obrigacao_id = ?
              ORDER BY h.criado_em DESC, h.id DESC'
        );
        $stmt->execute([$obrigacaoId]);

        return $stmt->fetchAll();
    }
}
