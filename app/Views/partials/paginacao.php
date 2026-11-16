<?php
/**
 * Navegação entre páginas, mantendo os filtros da busca nos links.
 * @var \App\Core\Paginacao      $paginacao
 * @var string                   $rota     ex.: 'empresas/index'
 * @var array<string, string>    $filtros  ex.: ['busca' => 'pão']
 */
$link = static fn (int $pagina): string => url($rota, $filtros + ['pagina' => $pagina]);
?>
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 mt-3">
    <small class="text-muted">
        Mostrando <?= e($paginacao->primeiroItem()) ?>–<?= e($paginacao->ultimoItem()) ?>
        de <?= e($paginacao->total) ?>
    </small>

    <?php if ($paginacao->totalPaginas > 1): ?>
        <nav aria-label="Paginação">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $paginacao->temAnterior() ? '' : ' disabled' ?>">
                    <?php if ($paginacao->temAnterior()): ?>
                        <a class="page-link" href="<?= e($link($paginacao->pagina - 1)) ?>">Anterior</a>
                    <?php else: ?>
                        <span class="page-link">Anterior</span>
                    <?php endif; ?>
                </li>
                <?php foreach ($paginacao->paginasVisiveis() as $numero): ?>
                    <li class="page-item<?= $numero === $paginacao->pagina ? ' active' : '' ?>">
                        <a class="page-link" href="<?= e($link($numero)) ?>"
                           <?= $numero === $paginacao->pagina ? 'aria-current="page"' : '' ?>><?= e($numero) ?></a>
                    </li>
                <?php endforeach; ?>
                <li class="page-item<?= $paginacao->temProxima() ? '' : ' disabled' ?>">
                    <?php if ($paginacao->temProxima()): ?>
                        <a class="page-link" href="<?= e($link($paginacao->pagina + 1)) ?>">Próxima</a>
                    <?php else: ?>
                        <span class="page-link">Próxima</span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
