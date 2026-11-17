<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Semaforo;

/**
 * Central de cobrança: o que cobrar (guias vencidas ou vencendo em até
 * 7 dias, por empresa) e o registro de cada cobrança feita.
 */
final class Cobranca extends Model
{
    /**
     * Guias pendentes que já venceram ou vencem em até DIAS_ALERTA dias,
     * com os dados da empresa. Mesmas condições SQL do Semaforo.
     * $empresaId = null: todas as empresas.
     *
     * @return list<array<string, mixed>>
     */
    public function guiasACobrar(?int $empresaId = null): array
    {
        $condicao = '((' . Semaforo::condicaoSql(Semaforo::VENCIDA) . ') OR ('
            . Semaforo::condicaoSql(Semaforo::ALERTA) . '))';
        $params = [];

        if ($empresaId !== null) {
            $condicao .= ' AND o.empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }

        $stmt = $this->db->prepare(
            "SELECT o.id, o.empresa_id, o.tipo, o.competencia, o.valor, o.vencimento, o.status,
                    e.razao_social, e.nome_fantasia, e.whatsapp
               FROM obrigacoes o
               JOIN empresas e ON e.id = o.empresa_id
              WHERE {$condicao}
              ORDER BY o.vencimento, o.id"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<int, string> empresa_id => data da última cobrança */
    public function ultimaPorEmpresa(): array
    {
        $linhas = $this->db->query(
            'SELECT empresa_id, MAX(cobrado_em) AS ultima FROM cobrancas GROUP BY empresa_id'
        )->fetchAll();

        return array_column($linhas, 'ultima', 'empresa_id');
    }

    public function registrar(int $empresaId, int $usuarioId): int
    {
        $stmt = $this->db->prepare('INSERT INTO cobrancas (empresa_id, usuario_id) VALUES (?, ?)');
        $stmt->execute([$empresaId, $usuarioId]);

        return (int) $this->db->lastInsertId();
    }
}
