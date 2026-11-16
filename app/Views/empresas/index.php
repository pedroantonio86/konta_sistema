<?php
/**
 * @var string                            $busca
 * @var list<array<string, mixed>>        $empresas
 * @var \App\Core\Paginacao               $paginacao
 * @var array<string, string>             $filtros
 */
use App\Models\Empresa;
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Empresas</h1>
    <a class="btn btn-primary" href="<?= e(url('empresas/criar')) ?>">+ Nova empresa</a>
</div>

<form method="get" action="<?= e(url()) ?>" class="row g-2 mb-3" role="search">
    <input type="hidden" name="url" value="empresas/index">
    <div class="col-sm-8 col-md-6">
        <input type="search" name="busca" class="form-control" value="<?= e($busca) ?>"
               placeholder="Razão social, nome fantasia ou CNPJ" aria-label="Buscar empresas">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Buscar</button>
        <?php if ($busca !== ''): ?>
            <a class="btn btn-link" href="<?= e(url('empresas/index')) ?>">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<?php if ($empresas === []): ?>
    <div class="alert alert-light border">
        <?= $busca === '' ? 'Nenhuma empresa cadastrada ainda.' : 'Nenhuma empresa encontrada para "' . e($busca) . '".' ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white border">
            <thead class="table-light">
            <tr>
                <th>Empresa</th>
                <th>CNPJ</th>
                <th class="d-none d-md-table-cell">Regime</th>
                <th class="d-none d-lg-table-cell">WhatsApp</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $empresa): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('empresas/ver', ['id' => $empresa['id']])) ?>" class="fw-semibold text-decoration-none">
                            <?= e($empresa['razao_social']) ?>
                        </a>
                        <?php if ($empresa['nome_fantasia'] !== null): ?>
                            <div class="small text-muted"><?= e($empresa['nome_fantasia']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= e(cnpj_formatado($empresa['cnpj'])) ?></td>
                    <td class="d-none d-md-table-cell"><?= e(Empresa::REGIMES[$empresa['regime']] ?? $empresa['regime']) ?></td>
                    <td class="d-none d-lg-table-cell text-nowrap"><?= e(telefone_formatado($empresa['whatsapp']) ?: '—') ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('empresas/ver', ['id' => $empresa['id']])) ?>">Ver</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('empresas/editar', ['id' => $empresa['id']])) ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php $rota = 'empresas/index'; require __DIR__ . '/../partials/paginacao.php'; ?>
