<?php
/**
 * Layout base de todas as páginas.
 * @var string      $conteudo HTML já renderizado da view
 * @var string|null $titulo
 */
use App\Core\Auth;

$usuarioLogado = Auth::usuario();
$rota = rota_atual();

// Itens do menu por perfil. "ativo" destaca a página atual (cor da marca + linha embaixo).
$menu = [];
if ($usuarioLogado !== null) {
    $menu[] = ['Painel', 'painel/index', str_starts_with($rota, 'painel/')];
    $menu[] = ['Obrigações', 'obrigacoes/index', str_starts_with($rota, 'obrigacoes/') && $rota !== 'obrigacoes/cobranca'];
    if ($usuarioLogado['perfil'] === Auth::CONTADOR) {
        $menu[] = ['Cobrança', 'obrigacoes/cobranca', $rota === 'obrigacoes/cobranca'];
        $menu[] = ['Empresas', 'empresas/index', str_starts_with($rota, 'empresas/')];
        $menu[] = ['Avaliações', 'avaliacoes/index', str_starts_with($rota, 'avaliacoes/')];
    } else {
        $menu[] = ['Avaliar', 'avaliacoes/criar', str_starts_with($rota, 'avaliacoes/')];
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($titulo) ? $titulo . ' · Konta' : 'Konta') ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
    <!-- Bootstrap 5.3.3 servido do próprio Konta (public/assets/vendor): funciona sem internet.
         O integrity confere que o arquivo é idêntico ao oficial publicado no CDN. -->
    <link rel="stylesheet"
          href="<?= e(asset('assets/vendor/bootstrap/bootstrap.min.css')) ?>"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH">
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="bg-body-tertiary">
<?php if ($usuarioLogado !== null): ?>
    <nav class="navbar navbar-expand-md navbar-konta mb-4">
        <div class="container">
            <a class="navbar-brand" href="<?= e(url()) ?>" aria-label="Konta, ir para o painel"><?= logo_konta() ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                    aria-controls="menu" aria-expanded="false" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="menu">
                <ul class="navbar-nav me-auto ms-md-3">
                    <?php foreach ($menu as [$rotulo, $destino, $ativo]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $ativo ? ' active' : '' ?>" href="<?= e(url($destino)) ?>"
                               <?= $ativo ? 'aria-current="page"' : '' ?>><?= e($rotulo) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex align-items-center gap-2 py-2 py-md-0">
                    <span class="small">
                        <?= e($usuarioLogado['nome']) ?>
                        <span class="badge selo-perfil ms-1"><?= e($usuarioLogado['perfil']) ?></span>
                    </span>
                    <form method="post" action="<?= e(url('auth/logout')) ?>" class="m-0">
                        <?= csrf_campo() ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm">Sair</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>

<main class="container pb-5<?= $usuarioLogado === null ? ' pt-4 pt-md-5' : '' ?>">
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
