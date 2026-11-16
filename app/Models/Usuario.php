<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Usuario extends Model
{
    /**
     * @return array{id: int, nome: string, email: string, senha_hash: string, perfil: string, empresa_id: ?int}|null
     */
    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nome, email, senha_hash, perfil, empresa_id FROM usuarios WHERE email = ?'
        );
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        return $usuario === false ? null : $usuario;
    }

    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Cria o login de um cliente. A senha é guardada só como hash
     * (password_hash usa bcrypt com "sal" aleatório): nem quem tem acesso
     * ao banco consegue ler a senha original.
     */
    public function criarCliente(int $empresaId, string $nome, string $email, string $senha): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (nome, email, senha_hash, perfil, empresa_id) VALUES (?, ?, ?, 'cliente', ?)"
        );
        $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), $empresaId]);

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array{id: int, nome: string, email: string, criado_em: string}> */
    public function listarPorEmpresa(int $empresaId): array
    {
        $stmt = $this->db->prepare('SELECT id, nome, email, criado_em FROM usuarios WHERE empresa_id = ? ORDER BY nome');
        $stmt->execute([$empresaId]);

        return $stmt->fetchAll();
    }
}
