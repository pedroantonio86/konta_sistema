<?php
/**
 * @var list<array{empresa: array<string, mixed>, guias: list<array<string, mixed>>, total: float, vencidas: int, ultima: ?string}> $grupos
 * @var float $total
 */
use App\Models\Obrigacao;
use App\Services\Semaforo;
use App\Services\WhatsAppLink;
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0">Central de cobrança</h1>
        <span class="text-muted">
            Guias vencidas ou vencendo em até <?= e(Semaforo::DIAS_ALERTA) ?> dias, por empresa
        </span>
    </div>
    <?php if ($grupos !== []): ?>
        <div class="text-end">
            <div class="small text-muted"><?= e(count($grupos)) ?> empresa(s) a cobrar</div>
            <div class="fs-4 fw-bold"><?= e(moeda($total)) ?></div>
        </div>
    <?php endif; ?>
</div>

<?php if ($grupos === []): ?>
    <div class="alert alert-light border">Nenhuma guia vencida ou vencendo em breve. Nada a cobrar hoje.</div>
<?php endif; ?>

<?php foreach ($grupos as $g):
    $empresa = $g['empresa'];
    $temWhatsapp = WhatsAppLink::numero($empresa['whatsapp']) !== null; ?>
    <div class="card shadow-sm mb-3 <?= $g['vencidas'] > 0 ? 'semaforo-vencida' : 'semaforo-alerta' ?>">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <a class="fw-semibold text-decoration-none" href="<?= e(url('empresas/ver', ['id' => $empresa['empresa_id']])) ?>">
                    <?= e($empresa['nome_fantasia'] ?? $empresa['razao_social']) ?>
                </a>
                <div class="small text-muted">
                    <?= e(count($g['guias'])) ?> guia(s)<?= $g['vencidas'] > 0 ? ', ' . e($g['vencidas']) . ' vencida(s)' : '' ?>
                    · <?= e(cobrado_ha($g['ultima'])) ?>
                </div>
            </div>
            <span class="fw-bold"><?= e(moeda($g['total'])) ?></span>
            <?php if ($temWhatsapp): ?>
                <form method="post" action="<?= e(url('obrigacoes/cobrar')) ?>" target="_blank" class="m-0">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="empresa_id" value="<?= e($empresa['empresa_id']) ?>">
                    <button type="submit" class="btn btn-success btn-sm">Cobrar no WhatsApp</button>
                </form>
            <?php else: ?>
                <span class="d-inline-block" tabindex="0" title="Empresa sem WhatsApp válido cadastrado">
                    <button type="button" class="btn btn-success btn-sm" disabled>Cobrar no WhatsApp</button>
                </span>
            <?php endif; ?>
        </div>
        <ul class="list-group list-group-flush">
            <?php foreach ($g['guias'] as $guia):
                $situacao = Semaforo::calcular($guia['status'], $guia['vencimento']); ?>
                <li class="list-group-item d-flex flex-wrap align-items-center gap-2">
                    <?= selo_semaforo($situacao) ?>
                    <a class="text-decoration-none me-auto" href="<?= e(url('obrigacoes/ver', ['id' => $guia['id']])) ?>">
                        <?= e(Obrigacao::TIPOS[$guia['tipo']]) ?> <?= e(competencia_br($guia['competencia'])) ?>
                    </a>
                    <span class="small text-muted"><?= e(data_br($guia['vencimento'])) ?> · <?= e(Semaforo::descreverPrazo($guia['vencimento'])) ?></span>
                    <span class="text-nowrap" style="min-width: 7rem; text-align: right;"><?= e(moeda($guia['valor'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endforeach; ?>

<?php if ($grupos !== []): ?>
    <p class="small text-muted">
        O botão abre o WhatsApp com uma mensagem listando todas as guias da empresa e registra a cobrança.
        Recarregue a página para ver o "cobrado hoje" atualizado.
    </p>
<?php endif; ?>
