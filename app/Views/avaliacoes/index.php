<?php
/**
 * @var array{media: float, total: int, por_nota: array<int, int>} $resumo
 * @var list<array<string, mixed>>                                 $avaliacoes
 * @var array<int, string>                                         $notas
 */
use App\Models\Avaliacao;

$estrelas = static fn (float $nota): string =>
    str_repeat('★', (int) round($nota)) . str_repeat('☆', 5 - (int) round($nota));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Avaliações</h1>
    <a class="btn btn-outline-primary" href="<?= e(url('avaliacoes/criar')) ?>">Avaliar o sistema</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100 text-center">
            <div class="card-body">
                <div class="small text-muted">Média</div>
                <div class="display-4 fw-bold"><?= e(number_format($resumo['media'], 1, ',', '')) ?></div>
                <div class="text-warning fs-4" aria-hidden="true"><?= e($estrelas($resumo['media'])) ?></div>
                <div class="small text-muted"><?= e($resumo['total']) ?> avaliação(ões)</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <?php foreach ($resumo['por_nota'] as $nota => $quantidade):
                    $percentual = $resumo['total'] > 0 ? (int) round($quantidade / $resumo['total'] * 100) : 0; ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="text-nowrap small" style="width: 7.5rem;"><?= e($nota) ?> ★ <?= e($notas[$nota]) ?></span>
                        <div class="progress flex-grow-1" role="progressbar" aria-label="<?= e($notas[$nota]) ?>"
                             aria-valuenow="<?= e($percentual) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar bg-warning" style="width: <?= e($percentual) ?>%"></div>
                        </div>
                        <span class="small text-muted text-end" style="width: 2rem;"><?= e($quantidade) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($avaliacoes === []): ?>
    <div class="alert alert-light border">Nenhuma avaliação ainda.</div>
<?php else: ?>
    <h2 class="h5">Comentários <small class="text-muted fw-normal">(até <?= e(Avaliacao::LIMITE_LISTA) ?> mais recentes)</small></h2>
    <div class="list-group shadow-sm">
        <?php foreach ($avaliacoes as $a): ?>
            <div class="list-group-item">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <span>
                        <span class="text-warning" title="<?= e($a['nota']) ?> de 5"><?= e($estrelas((float) $a['nota'])) ?></span>
                        <strong class="ms-1"><?= e($a['nome'] ?? 'Usuário removido') ?></strong>
                        <?php if ($a['perfil'] !== null): ?>
                            <span class="badge text-bg-light border"><?= e($a['perfil']) ?></span>
                        <?php endif; ?>
                    </span>
                    <small class="text-muted"><?= e(date('d/m/Y H:i', (int) strtotime($a['criado_em']))) ?></small>
                </div>
                <?php if ($a['comentario'] !== null): ?>
                    <p class="mb-0 mt-1"><?= nl2br(e($a['comentario'])) ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
