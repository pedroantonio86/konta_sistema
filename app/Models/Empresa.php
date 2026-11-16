<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Validator;
use PDO;

final class Empresa extends Model
{
    /** Valor gravado no banco => texto exibido. */
    public const REGIMES = [
        'MEI'       => 'MEI',
        'SIMPLES'   => 'Simples Nacional',
        'PRESUMIDO' => 'Lucro Presumido',
        'REAL'      => 'Lucro Real',
    ];

    /**
     * Busca por razão social, nome fantasia ou CNPJ (com ou sem máscara).
     * A collation utf8mb4_unicode_ci ignora maiúsculas e acentos:
     * "pao" encontra "Pão Quente".
     *
     * @return list<array<string, mixed>>
     */
    public function listar(string $busca, int $limite, int $offset): array
    {
        [$where, $params] = $this->filtroBusca($busca);

        $stmt = $this->db->prepare(
            "SELECT id, razao_social, nome_fantasia, cnpj, whatsapp, regime
               FROM empresas {$where}
              ORDER BY razao_social
              LIMIT :limite OFFSET :offset"
        );
        foreach ($params as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function contar(string $busca): int
    {
        [$where, $params] = $this->filtroBusca($busca);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM empresas {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array<int, string> id => nome, para campos <select> */
    public function listarParaSelect(): array
    {
        $linhas = $this->db->query(
            'SELECT id, COALESCE(nome_fantasia, razao_social) AS nome FROM empresas ORDER BY nome'
        )->fetchAll();

        return array_column($linhas, 'nome', 'id');
    }

    /** @return array<string, mixed>|null */
    public function buscar(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM empresas WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    /** $ignorarId: na edição, a própria empresa não conta como duplicada. */
    public function cnpjExiste(string $cnpj, ?int $ignorarId = null): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM empresas WHERE cnpj = ? AND id <> ?');
        $stmt->execute([$cnpj, $ignorarId ?? 0]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, string> $dados já validados */
    public function criar(array $dados): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO empresas (razao_social, nome_fantasia, cnpj, email, whatsapp, regime)
             VALUES (:razao_social, :nome_fantasia, :cnpj, :email, :whatsapp, :regime)'
        );
        $stmt->execute(self::paraBanco($dados));

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, string> $dados já validados */
    public function atualizar(int $id, array $dados): void
    {
        $stmt = $this->db->prepare(
            'UPDATE empresas
                SET razao_social = :razao_social, nome_fantasia = :nome_fantasia, cnpj = :cnpj,
                    email = :email, whatsapp = :whatsapp, regime = :regime
              WHERE id = :id'
        );
        $stmt->execute(self::paraBanco($dados) + ['id' => $id]);
    }

    public function contarObrigacoes(int $id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM obrigacoes WHERE empresa_id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Exclui a empresa (e, por CASCADE, os logins de cliente dela).
     * O controller verifica antes se há obrigações; o banco também bloqueia
     * (ON DELETE RESTRICT) como última linha de defesa.
     */
    public function excluir(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM empresas WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Converte o formulário para o formato do banco: CNPJ e WhatsApp só com
     * caracteres válidos; campo opcional vazio vira NULL.
     *
     * @param array<string, string> $dados
     * @return array<string, ?string>
     */
    private static function paraBanco(array $dados): array
    {
        $whatsapp = Validator::normalizarWhatsapp($dados['whatsapp']);

        return [
            'razao_social'  => $dados['razao_social'],
            'nome_fantasia' => $dados['nome_fantasia'] !== '' ? $dados['nome_fantasia'] : null,
            'cnpj'          => Validator::normalizarCnpj($dados['cnpj']),
            'email'         => $dados['email'] !== '' ? $dados['email'] : null,
            'whatsapp'      => $whatsapp !== '' ? $whatsapp : null,
            'regime'        => $dados['regime'],
        ];
    }

    /**
     * Monta o WHERE da busca. Com prepared statements nativos o mesmo
     * parâmetro nomeado não pode aparecer duas vezes, por isso :b1, :b2, :b3.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private function filtroBusca(string $busca): array
    {
        if ($busca === '') {
            return ['', []];
        }

        return [
            'WHERE razao_social LIKE :b1 OR nome_fantasia LIKE :b2 OR cnpj LIKE :b3',
            [
                ':b1' => self::termoLike($busca),
                ':b2' => self::termoLike($busca),
                ':b3' => self::termoLike(Validator::normalizarCnpj($busca)),
            ],
        ];
    }
}
