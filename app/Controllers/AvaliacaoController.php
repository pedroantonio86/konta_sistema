<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Validator;
use App\Models\Avaliacao;

/**
 * Tela de avaliação para o teste com usuário real.
 * Qualquer usuário logado avalia; só o contador vê o resultado.
 */
final class AvaliacaoController extends Controller
{
    public const NOTAS = [
        1 => 'Muito ruim',
        2 => 'Ruim',
        3 => 'Regular',
        4 => 'Bom',
        5 => 'Excelente',
    ];

    public function criar(): void
    {
        $this->exibirFormulario(['nota' => '', 'comentario' => '']);
    }

    public function salvar(): void
    {
        $dados = Validator::limpar($_POST, ['nota', 'comentario']);

        $v = (new Validator($dados))
            ->obrigatorio('nota')->inteiroEntre('nota', 1, 5)
            ->tamanho('comentario', 0, 1000);

        if (!$v->valido()) {
            $this->exibirFormulario($dados, $v->erros());
            return;
        }

        (new Avaliacao())->criar(Auth::usuario()['id'], (int) $dados['nota'], $dados['comentario']);
        $this->flash('success', 'Obrigado pela avaliação! Ela ajuda a melhorar o Konta.');
        $this->redirect('painel/index');
    }

    public function index(): void
    {
        $avaliacoes = new Avaliacao();

        $this->render('avaliacoes/index', [
            'titulo'     => 'Avaliações',
            'resumo'     => $avaliacoes->resumo(),
            'avaliacoes' => $avaliacoes->recentes(),
            'notas'      => self::NOTAS,
        ]);
    }

    /**
     * @param array<string, string> $dados
     * @param array<string, string> $erros
     */
    private function exibirFormulario(array $dados, array $erros = []): void
    {
        $this->render('avaliacoes/form', [
            'titulo' => 'Avaliar o Konta',
            'dados'  => $dados,
            'erros'  => $erros,
            'notas'  => self::NOTAS,
        ]);
    }
}
