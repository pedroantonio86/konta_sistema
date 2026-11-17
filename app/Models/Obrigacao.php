<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Validator;
use App\Services\Semaforo;
use PDO;

final class Obrigacao extends Model
{
    /** Valor gravado no banco => texto exibido. */
    public const TIPOS = [
        'DAS'     => 'DAS',
        'DARF'    => 'DARF',
        'INSS'    => 'INSS (GPS)',
        'FGTS'    => 'FGTS',
        'ISS'     => 'ISS',
        'ICMS'    => 'ICMS',
        'DCTFWEB' => 'DCTFWeb',
        'OUTRO'   => 'Outro',
    ];

    private const SELECT = 'SELECT o.*, e.razao_social, e.nome_fantasia, e.cnpj, e.whatsapp
                              FROM obrigacoes o
                              JOIN empresas e ON e.id = o.empresa_id';

    private const ORDEM = "ORDER BY o.status = 'paga', o.vencimento, o.id";

    /**
     * Uma página da listagem. Pendentes primeiro, da mais urgente para a
     * mais distante; pagas no fim.
     *
     * @param array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string} $filtros
     * @return list<array<string, mixed>>
     */
    public function listar(array $filtros, int $limite, int $offset): array
    {
        [$where, $params] = $this->montarFiltros($filtros);

        $stmt = $this->db->prepare(self::SELECT . " {$where} " . self::ORDEM . ' LIMIT :limite OFFSET :offset');
        foreach ($params as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Todas as obrigações dos filtros, sem paginação (para a planilha).
     * Mesmo WHERE e mesma ordem da listagem: a planilha bate com a tela.
     *
     * @param array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string} $filtros
     * @return list<array<string, mixed>>
     */
    public function listarTodas(array $filtros): array
    {
        [$where, $params] = $this->montarFiltros($filtros);

        $stmt = $this->db->prepare(self::SELECT . " {$where} " . self::ORDEM);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @param array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string} $filtros */
    public function contar(array $filtros): int
    {
        [$where, $params] = $this->montarFiltros($filtros);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM obrigacoes o JOIN empresas e ON e.id = o.empresa_id {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null obrigação + dados da empresa */
    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare(self::SELECT . ' WHERE o.id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** Mesma guia (empresa + tipo + competência) já lançada? Espelha a UNIQUE do banco. */
    public function existeDuplicada(int $empresaId, string $tipo, string $competencia, ?int $ignorarId = null): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM obrigacoes WHERE empresa_id = ? AND tipo = ? AND competencia = ? AND id <> ?'
        );
        $stmt->execute([$empresaId, $tipo, $competencia . '-01', $ignorarId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, string> $dados já validados */
    public function criar(array $dados): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO obrigacoes (empresa_id, tipo, competencia, valor, vencimento, observacao)
             VALUES (:empresa_id, :tipo, :competencia, :valor, :vencimento, :observacao)'
        );
        $stmt->execute(self::paraBanco($dados));

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, string> $dados já validados (status/pagamento não mudam aqui) */
    public function atualizar(int $id, array $dados): void
    {
        $stmt = $this->db->prepare(
            'UPDATE obrigacoes
                SET empresa_id = :empresa_id, tipo = :tipo, competencia = :competencia,
                    valor = :valor, vencimento = :vencimento, observacao = :observacao
              WHERE id = :id'
        );
        $stmt->execute(self::paraBanco($dados) + ['id' => $id]);
    }

    /**
     * Marca como paga agora. O "AND status = 'pendente'" torna a operação
     * atômica: se dois cliques chegarem juntos, só o primeiro altera a linha.
     *
     * @return bool false se ela já estava paga
     */
    public function marcarPaga(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE obrigacoes SET status = 'paga', pago_em = NOW() WHERE id = ? AND status = 'pendente'"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Desfaz o pagamento (ex.: o cliente marcou por engano). Status e data
     * mudam JUNTOS, então o CHECK ck_obrigacoes_pagto continua satisfeito.
     * Mesma ideia do marcarPaga: a condição no próprio UPDATE evita corrida.
     *
     * @return bool false se ela não estava paga
     */
    public function reabrir(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE obrigacoes SET status = 'pendente', pago_em = NULL WHERE id = ? AND status = 'paga'"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() === 1;
    }

    /** Documentos ligados são removidos pelo banco (ON DELETE CASCADE). */
    public function excluir(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM obrigacoes WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * @param array<string, string> $dados
     * @return array<string, string|int|null>
     */
    private static function paraBanco(array $dados): array
    {
        return [
            'empresa_id'  => (int) $dados['empresa_id'],
            'tipo'        => $dados['tipo'],
            'competencia' => $dados['competencia'] . '-01', // 'AAAA-MM' -> 1º dia do mês
            'valor'       => Validator::normalizarValor($dados['valor']),
            'vencimento'  => $dados['vencimento'],
            'observacao'  => $dados['observacao'] !== '' ? $dados['observacao'] : null,
        ];
    }

    /**
     * Monta o WHERE só com filtros preenchidos. Os valores vão como
     * parâmetros; a situação vira SQL fixo do Semaforo (nunca texto do usuário).
     *
     * @param array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string} $filtros
     * @return array{0: string, 1: array<string, string|int>}
     */
    private function montarFiltros(array $filtros): array
    {
        $condicoes = [];
        $params = [];

        if ($filtros['empresa_id'] !== null) {
            $condicoes[] = 'o.empresa_id = :empresa_id';
            $params[':empresa_id'] = $filtros['empresa_id'];
        }
        if ($filtros['tipo'] !== '') {
            $condicoes[] = 'o.tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        if ($filtros['competencia'] !== '') {
            $condicoes[] = 'o.competencia = :competencia';
            $params[':competencia'] = $filtros['competencia'] . '-01';
        }
        $sqlSituacao = Semaforo::condicaoSql($filtros['situacao']);
        if ($sqlSituacao !== null) {
            $condicoes[] = "({$sqlSituacao})";
        }
        if ($filtros['busca'] !== '') {
            $condicoes[] = '(e.razao_social LIKE :b1 OR e.nome_fantasia LIKE :b2 OR o.observacao LIKE :b3)';
            $params[':b1'] = $params[':b2'] = $params[':b3'] = self::termoLike($filtros['busca']);
        }

        return [$condicoes === [] ? '' : 'WHERE ' . implode(' AND ', $condicoes), $params];
    }
}
