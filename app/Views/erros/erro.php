<?php
/**
 * Página de erro única para 403 (inclusive CSRF), 404, 405 e 500.
 * @var int         $codigo
 * @var string      $titulo
 * @var string      $mensagem
 * @var string|null $detalhe  só preenchido com debug = true
 */
?>
<div class="text-center py-5">
    <p class="display-1 fw-bold text-secondary"><?= e($codigo) ?></p>
    <h1 class="h3"><?= e($titulo) ?></h1>
    <p class="text-muted"><?= e($mensagem) ?></p>

    <?php if (!empty($detalhe)): ?>
        <pre class="text-start bg-light border rounded p-3 small mx-auto" style="max-width: 48rem;"><?= e($detalhe) ?></pre>
    <?php endif; ?>

    <a class="btn btn-primary" href="<?= e(url()) ?>">Voltar ao painel</a>
</div>
