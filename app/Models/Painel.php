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
     * @return array{vencidas: int, valor_vencido: string, a_vencer: int, valor_a_vencer: string, pagas_mes: int, valor_pago_mes: string, total_pendente: string}
     */
    public function indicadores(?int $empresaId): array
    {
        $vencida = Semaforo::condicaoSql(Semaforo::VENCIDA);
        $alerta = Semaforo::condicaoSql(Semaforo::ALERTA);
        $pagaNoMes = "o.status = 'paga' AND o.pago_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        [$where, $params] = self::filtroEmpresa($empresaId);

        $stmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM({$vencida}), 0)                                   AS vencidas,
                COALESCE(SUM(CASE WHEN {$vencida} THEN o.valor END), 0)        AS valor_vencido,
                COALESCE(SUM({$alerta}), 0)                                    AS a_vencer,
                COALESCE(SUM(CASE WHEN {$alerta} THEN o.valor END), 0)         AS valor_a_vencer,
                COALESCE(SUM({$pagaNoMes}), 0)                                 AS pagas_mes,
                COALESCE(SUM(CASE WHEN {$pagaNoMes} THEN o.valor END), 0)      AS valor_pago_mes,
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
            'valor_pago_mes' => (string) $linha['valor_pago_mes'],
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

    /**
     * Quantas obrigações há em cada situação do semáforo (barra empilhada).
     * Cada contagem usa a condição SQL do próprio Semaforo.
     *
     * @return array<string, int> situação => quantidade, na ordem do Semaforo
     */
    public function porSituacao(?int $empresaId): array
    {
        $colunas = [];
        foreach (array_keys(Semaforo::opcoes()) as $situacao) {
            // $situacao é uma das 4 constantes do Semaforo: nunca texto do usuário
            $colunas[] = 'COALESCE(SUM(' . Semaforo::condicaoSql($situacao) . "), 0) AS {$situacao}";
        }
        [$where, $params] = self::filtroEmpresa($empresaId);

        $stmt = $this->db->prepare('SELECT ' . implode(', ', $colunas) . " FROM obrigacoes o {$where}");
        $stmt->execute($params);

        return array_map('intval', $stmt->fetch());
    }

    /**
     * As empresas com maior valor vencido (só o contador vê).
     *
     * @return list<array{id: int, razao_social: string, nome_fantasia: ?string, whatsapp: ?string, guias: int, valor: string}>
     */
    public function rankingVencidas(int $limite = 5): array
    {
        $vencida = Semaforo::condicaoSql(Semaforo::VENCIDA);

        $stmt = $this->db->prepare(
            "SELECT e.id, e.razao_social, e.nome_fantasia, e.whatsapp,
                    COUNT(*) AS guias, SUM(o.valor) AS valor
               FROM obrigacoes o
               JOIN empresas e ON e.id = o.empresa_id
              WHERE {$vencida}
              GROUP BY e.id, e.razao_social, e.nome_fantasia, e.whatsapp
              ORDER BY valor DESC, guias DESC
              LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $l): array => ['guias' => (int) $l['guias']] + $l, $stmt->fetchAll());
    }

    public const SEMANAS = 4;

    /**
     * Valor pendente que vence em cada uma das próximas 4 semanas
     * (semana 1 = hoje até hoje + 6). Semanas sem guia aparecem com zero.
     *
     * @return list<array{rotulo: string, valor: float, guias: int}>
     */
    public function vencimentosPorSemana(?int $empresaId): array
    {
        [$where, $params] = self::filtroEmpresa($empresaId);
        $where .= ($where === '' ? 'WHERE' : ' AND')
            . " o.status = 'pendente' AND o.vencimento BETWEEN CURDATE() AND CURDATE() + INTERVAL "
            . (self::SEMANAS * 7 - 1) . ' DAY';

        $stmt = $this->db->prepare(
            "SELECT FLOOR(DATEDIFF(o.vencimento, CURDATE()) / 7) AS semana,
                    SUM(o.valor) AS valor, COUNT(*) AS guias
               FROM obrigacoes o {$where}
              GROUP BY semana"
        );
        $stmt->execute($params);
        $porSemana = array_column($stmt->fetchAll(), null, 'semana');

        $hoje = new \DateTimeImmutable('today');
        $semanas = [];
        for ($i = 0; $i < self::SEMANAS; $i++) {
            $inicio = $hoje->modify('+' . ($i * 7) . ' days');
            $semanas[] = [
                'rotulo' => $inicio->format('d/m') . '–' . $inicio->modify('+6 days')->format('d/m'),
                'valor'  => (float) ($porSemana[$i]['valor'] ?? 0),
                'guias'  => (int) ($porSemana[$i]['guias'] ?? 0),
            ];
        }

        return $semanas;
    }

    /** @return array{0: string, 1: array<string, int>} */
    private static function filtroEmpresa(?int $empresaId): array
    {
        return $empresaId === null
            ? ['', []]
            : ['WHERE o.empresa_id = :empresa_id', [':empresa_id' => $empresaId]];
    }
}
