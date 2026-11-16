<?php

declare(strict_types=1);

use App\Core\Config;

/**
 * Funções curtas usadas nas views.
 */

/**
 * Escapa texto para HTML (proteção contra XSS). Use em TODA saída de dado:
 * <?= e($empresa['razao_social']) ?>
 * ENT_QUOTES também escapa aspas, então é seguro dentro de atributos (value="...").
 */
function e(string|int|float|null $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Monta o link de uma rota: url('empresas/editar', ['id' => 5])
 * -> /konta_sistema/public/index.php?url=empresas%2Feditar&id=5
 *
 * @param array<string, scalar|null> $params
 */
function url(string $rota = '', array $params = []): string
{
    $base = (string) Config::obter('app.base_url', '/');

    if ($rota === '' && $params === []) {
        return $base;
    }

    return $base . 'index.php?' . http_build_query(['url' => $rota] + $params);
}

/** Caminho de arquivo estático em public/: asset('assets/css/app.css') */
function asset(string $caminho): string
{
    return Config::obter('app.base_url', '/') . ltrim($caminho, '/');
}

/** 1234.5 -> "R$ 1.234,50" */
function moeda(string|int|float|null $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** "2026-11-20" ou "2026-11-20 14:30:00" -> "20/11/2026" */
function data_br(?string $data): string
{
    if ($data === null || $data === '') {
        return '—';
    }

    return date('d/m/Y', (int) strtotime($data));
}

/** "11222333000181" -> "11.222.333/0001-81" (vale para CNPJ alfanumérico também) */
function cnpj_formatado(?string $cnpj): string
{
    if ($cnpj === null || strlen($cnpj) !== 14) {
        return (string) $cnpj;
    }

    return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3)
        . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
}

/** "86999990001" -> "(86) 99999-0001"; "8632210000" -> "(86) 3221-0000" */
function telefone_formatado(?string $telefone): string
{
    if ($telefone === null || !preg_match('/^(\d{2})(\d{4,5})(\d{4})$/', $telefone, $partes)) {
        return (string) $telefone;
    }

    return "({$partes[1]}) {$partes[2]}-{$partes[3]}";
}

/**
 * Selo colorido do semáforo: <?= selo_semaforo($situacao) ?>
 * A cor nunca é a única informação: o texto acompanha (acessibilidade).
 */
function selo_semaforo(string $situacao): string
{
    $semaforo = \App\Services\Semaforo::class;

    return '<span class="badge text-bg-' . e($semaforo::classe($situacao)) . '">'
        . e($semaforo::rotulo($situacao)) . '</span>';
}

/**
 * Botão "Cobrar no WhatsApp" de uma obrigação pendente.
 * Sem número cadastrado, aparece desabilitado com a explicação.
 *
 * @param array<string, mixed> $obrigacao linha com os dados da empresa
 */
function botao_whatsapp(array $obrigacao, string $tamanho = ''): string
{
    $link = \App\Services\WhatsAppLink::paraObrigacao($obrigacao);
    $classe = 'btn btn-success' . ($tamanho !== '' ? ' btn-' . $tamanho : '');

    if ($link === null) {
        return '<span class="d-inline-block" tabindex="0" title="Empresa sem WhatsApp válido cadastrado">'
            . '<button type="button" class="' . $classe . '" disabled>Cobrar no WhatsApp</button></span>';
    }

    // noopener: a aba do WhatsApp não consegue controlar a aba do Konta.
    return '<a class="' . $classe . '" href="' . e($link) . '" target="_blank" rel="noopener noreferrer">'
        . 'Cobrar no WhatsApp</a>';
}

/** 348160 -> "340 KB"; 1258291 -> "1,2 MB" */
function tamanho_arquivo(int|string $bytes): string
{
    $bytes = (int) $bytes;

    return $bytes >= 1024 * 1024
        ? number_format($bytes / 1024 / 1024, 1, ',', '.') . ' MB'
        : max(1, (int) round($bytes / 1024)) . ' KB';
}

/** "2026-10-01" -> "10/2026" */
function competencia_br(?string $competencia): string
{
    if ($competencia === null || $competencia === '') {
        return '—';
    }

    return date('m/Y', (int) strtotime($competencia));
}

/**
 * Classe do Bootstrap que pinta o campo de vermelho quando há erro.
 * Uso: class="form-control<?= classe_invalido($erros, 'cnpj') ?>"
 *
 * @param array<string, string> $erros
 */
function classe_invalido(array $erros, string $campo): string
{
    return isset($erros[$campo]) ? ' is-invalid' : '';
}

/**
 * Mensagem de erro logo abaixo do campo (já escapada).
 *
 * @param array<string, string> $erros
 */
function erro_campo(array $erros, string $campo): string
{
    return isset($erros[$campo])
        ? '<div class="invalid-feedback">' . e($erros[$campo]) . '</div>'
        : '';
}

/** Campo oculto com o token CSRF: use em todo <form method="post">. */
function csrf_campo(): string
{
    return \App\Core\Csrf::campo();
}

/**
 * Devolve e APAGA as mensagens flash (aparecem uma única vez).
 *
 * @return list<array{tipo: string, mensagem: string}>
 */
function flash_consumir(): array
{
    $mensagens = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $mensagens;
}
