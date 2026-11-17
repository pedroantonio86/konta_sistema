<?php
/**
 * Layout da tela de login: página inteira (barra branca com o logo, faixa
 * na cor da marca, rodapé). Mesmos CSS, fontes e ícones locais do sistema:
 * funciona sem internet. As mensagens flash são mostradas pela própria
 * view, dentro do cartão de login.
 *
 * @var string      $conteudo HTML já renderizado da view
 * @var string|null $titulo
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' · Konta' : 'Konta') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <link rel="stylesheet"
          href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="pagina-login">
<header class="login-topo">
    <div class="container py-3"><?= logo_konta() ?></div>
</header>

<main class="login-faixa">
    <div class="container py-5">
        <?= $conteudo /* já escapado dentro da view */ ?>
    </div>
</main>

<footer class="login-rodape">
    <div class="container py-3 small">Todos os direitos reservados · 2026</div>
</footer>

<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"></script>
</body>
</html>
