<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Services\Semaforo;

/**
 * Números do painel inicial. $empresaId = null: todas (contador);
 * preenchido: só aquela empresa (cliente).
 *
 * As condições de "vencida" e "a vencer" vêm do Semaforo, a mesma regra
 * usada nas cores e nos filtros: o painel nunca discorda da listagem.
 */
final class Painel extends Model
{
    public const PROXIMAS = 5;

    /**
     * Uma única consulta: cada indicador é uma soma condicional.
     *
     * @return array{vencidas: int, valor_vencido: string, a_vencer: int, valor_a_vencer: string, pagas_mes: int, total_pendente: string}
     */
    public function indicadores(?int $empresaId): array
    {
        $vencida = Semaforo::condicaoSql(Semaforo::VENCIDA);
        $alerta = Semaforo::condicaoSql(Semaforo::ALERTA);
        [$where, $params] = self::filtroEmpresa($empresaId);

        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM({$vencida}), 0)                                   AS vencidas,
                COALESCE(SUM(CASE WHEN {$vencida} THEN o.valor END), 0)        AS valor_vencido,
                COALESCE(SUM({$alerta}), 0)                                    AS a_vencer,
                COALESCE(SUM(CASE WHEN {$alerta} THEN o.valor END), 0)         AS valor_a_vencer,
                COALESCE(SUM(o.status = 'paga'
                    AND o.pago_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0)   AS pagas_mes,
                COALESCE(SUM(CASE WHEN o.status = 'pendente' THEN o.valor END), 0) AS total_pendente
               FROM obrigacoes o {$where}"
        );
        $stmt->execute($params);
        $linha = $stmt->fetch();

        return [
            'vencidas'       => (int) $linha['vencidas'],
            'valor_vencido'  => (string) $linha['valor_vencido'],
            'a_vencer'       => (int) $linha['a_vencer'],
            'valor_a_vencer' => (string) $linha['valor_a_vencer'],
            'pagas_mes'      => (int) $linha['pagas_mes'],
            'total_pendente' => (string) $linha['total_pendente'],
        ];
    }

    /**
     * Próximas pendentes a vencer (de hoje em diante), da mais próxima.
     *
     * @return list<array<string, mixed>>
     */
    public function proximas(?int $empresaId): array
    {
        [$where, $params] = self::filtroEmpresa($empresaId);
        $where .= ($where === '' ? 'WHERE' : ' AND') . " o.status = 'pendente' AND o.vencimento >= CURDATE()";

        $stmt = $this->db->prepare(
            'SELECT o.id, o.tipo, o.competencia, o.valor, o.vencimento, o.status,
                    e.razao_social, e.nome_fantasia
               FROM obrigacoes o
               JOIN empresas e ON e.id = o.empresa_id
             ' . $where . '
              ORDER BY o.vencimento, o.id
              LIMIT ' . self::PROXIMAS
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array{0: string, 1: array<string, int>} */
    private static function filtroEmpresa(?int $empresaId): array
    {
        return $empresaId === null
            ? ['', []]
            : ['WHERE o.empresa_id = :empresa_id', [':empresa_id' => $empresaId]];
    }
}
