<?php
/**
 * Layout base de todas as páginas.
 * @var string      $conteudo HTML já renderizado da view
 * @var string|null $titulo
 */
$usuarioLogado = \App\Core\Auth::usuario();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' · Konta' : 'Konta') ?></title>
    <!-- Bootstrap 5.3.3 servido do próprio Konta (public/assets/vendor): funciona sem internet.
         O integrity confere que o arquivo é idêntico ao oficial publicado no CDN. -->
    <link rel="stylesheet"
          href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url()) ?>">Konta</a>
        <?php if ($usuarioLogado !== null): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                    aria-controls="menu" aria-expanded="false" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('painel/index')) ?>">Painel</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('obrigacoes/index')) ?>">Obrigações</a></li>
                    <?php if ($usuarioLogado['perfil'] === \App\Core\Auth::CONTADOR): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('empresas/index')) ?>">Empresas</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('avaliacoes/index')) ?>">Avaliações</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(url('avaliacoes/criar')) ?>">Avaliar</a></li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center gap-2">
                    <span class="navbar-text small">
                        <?= e($usuarioLogado['nome']) ?>
                        <span class="badge text-bg-secondary ms-1"><?= e($usuarioLogado['perfil']) ?></span>
                    </span>
                    <form method="post" action="<?= e(url('auth/logout')) ?>" class="m-0">
                        <?= csrf_campo() ?>
                        <button type="submit" class="btn btn-outline-light btn-sm">Sair</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container pb-5">
    <?php if (!empty($avisoCliente)) { require __DIR__ . '/partials/aviso_cliente.php'; } ?>
    <?php foreach (flash_consumir() as $aviso): ?>
        <div class="alert alert-<?= e($aviso['tipo']) ?> alert-dismissible fade show" role="alert">
            <?= e($aviso['mensagem']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endforeach; ?>

    <?= $conteudo /* já escapado dentro de cada view */ ?>
</main>

<script src="<?= e(asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"></script>
<script src="<?= e(asset('assets/js/mascaras.js')) ?>" defer></script>
</body>
</html>
