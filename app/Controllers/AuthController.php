<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Validator;
use App\Models\Usuario;

final class AuthController extends Controller
{
    /**
     * Hash de uma senha qualquer, usado quando o e-mail não existe.
     * Por quê: password_verify é lento de propósito (~50 ms). Se só o
     * executássemos para e-mails existentes, a resposta mais rápida
     * revelaria quais e-mails estão cadastrados.
     */
    private const HASH_FALSO = '$2y$10$ETz0FC3zDU5u6FTKbsaTTO94g.6QievQ4F43GgKdAAp7kKaKdpwY.';

    public function formulario(): void
    {
        if (Auth::logado()) {
            $this->redirect('painel/index');
        }

        $this->exibirFormulario(['email' => '']);
    }

    public function entrar(): void
    {
        $dados = Validator::limpar($_POST, ['email', 'senha']);

        $v = (new Validator($dados))
            ->obrigatorio('email')->email('email')
            ->obrigatorio('senha');

        if (!$v->valido()) {
            $this->exibirFormulario($dados, $v->erros());
            return;
        }

        $usuario = (new Usuario())->buscarPorEmail($dados['email']);
        $senhaConfere = password_verify($dados['senha'], $usuario['senha_hash'] ?? self::HASH_FALSO);

        if ($usuario === null || !$senhaConfere) {
            // Mensagem genérica: não diz se o erro foi no e-mail ou na senha.
            $this->exibirFormulario($dados, [], 'E-mail ou senha inválidos.');
            return;
        }

        Auth::entrar($usuario);
        $this->flash('success', 'Bem-vindo(a), ' . $usuario['nome'] . '!');
        $this->redirect('painel/index');
    }

    public function sair(): void
    {
        Auth::sair();
        $this->flash('info', 'Você saiu do sistema.');
        $this->redirect('auth/login');
    }

    /**
     * @param array<string, string> $dados
     * @param array<string, string> $erros
     */
    private function exibirFormulario(array $dados, array $erros = [], ?string $erroGeral = null): void
    {
        unset($dados['senha']); // a senha nunca volta para a tela

        $this->render('auth/login', [
            'titulo'    => 'Entrar',
            'dados'     => $dados,
            'erros'     => $erros,
            'erroGeral' => $erroGeral,
        ]);
    }
}
