<?php
/**
 * @var array<string, mixed> $obrigacao (com razao_social, nome_fantasia, whatsapp da empresa)
 * @var string               $situacao  calculada pelo Semaforo
 * @var string               $titulo
 * @var bool                 $ehContador
 * @var list<array<string, mixed>> $documentos
 * @var list<array<string, mixed>> $historico  mais recentes primeiro
 */
use App\Models\Obrigacao;
use App\Services\Semaforo;
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('obrigacoes/index')) ?>">Obrigações</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($titulo) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($titulo) ?> <?= selo_semaforo($situacao) ?></h1>
        <?php if ($ehContador): ?>
            <a class="text-muted" href="<?= e(url('empresas/ver', ['id' => $obrigacao['empresa_id']])) ?>">
                <?= e($obrigacao['razao_social']) ?>
            </a>
        <?php else: ?>
            <span class="text-muted"><?= e($obrigacao['razao_social']) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($ehContador): ?>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($situacao !== Semaforo::PAGA): ?>
                <?= botao_whatsapp($obrigacao) ?>
            <?php endif; ?>
            <a class="btn btn-outline-primary" href="<?= e(url('obrigacoes/editar', ['id' => $obrigacao['id']])) ?>"><?= icone('pencil') ?>Editar</a>
            <form method="post" action="<?= e(url('obrigacoes/excluir')) ?>" class="m-0"
                  onsubmit="return confirm('Excluir esta obrigação e os documentos anexados?');">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($obrigacao['id']) ?>">
                <button type="submit" class="btn btn-outline-danger"><?= icone('trash') ?>Excluir</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-4 semaforo-<?= e($situacao) ?>">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Tipo</dt>
            <dd class="col-sm-9"><?= e(Obrigacao::TIPOS[$obrigacao['tipo']]) ?></dd>

            <dt class="col-sm-3">Competência</dt>
            <dd class="col-sm-9"><?= e(competencia_br($obrigacao['competencia'])) ?></dd>

            <dt class="col-sm-3">Valor</dt>
            <dd class="col-sm-9 fw-semibold"><?= e(moeda($obrigacao['valor'])) ?></dd>

            <dt class="col-sm-3">Vencimento</dt>
            <dd class="col-sm-9">
                <?= e(data_br($obrigacao['vencimento'])) ?>
                <?php if ($situacao === Semaforo::PAGA): ?>
                    <span class="text-muted">· paga em <?= e(data_br($obrigacao['pago_em'])) ?></span>
                <?php else: ?>
                    <span class="text-<?= e(Semaforo::classe($situacao)) ?>-emphasis">· <?= e(Semaforo::descreverPrazo($obrigacao['vencimento'])) ?></span>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-3">Observação</dt>
            <dd class="col-sm-9 mb-0"><?= nl2br(e($obrigacao['observacao'] ?? '—')) ?></dd>
        </dl>
    </div>

    <?php if ($situacao !== Semaforo::PAGA): ?>
        <div class="card-footer bg-white">
            <form method="post" action="<?= e(url('obrigacoes/pagar')) ?>" class="m-0"
                  onsubmit="return confirm('Confirmar que esta guia foi paga?');">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($obrigacao['id']) ?>">
                <button type="submit" class="btn btn-primary"><?= icone('check2-circle') ?>Marcar como paga</button>
                <span class="small text-muted ms-2">Anexe o comprovante abaixo para o escritório conferir.</span>
            </form>
        </div>
    <?php elseif ($ehContador): ?>
        <div class="card-footer bg-white">
            <form method="post" action="<?= e(url('obrigacoes/reabrir')) ?>" class="m-0"
                  onsubmit="return confirm('Reabrir esta guia? Ela volta a pendente e o semáforo é recalculado.');">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($obrigacao['id']) ?>">
                <button type="submit" class="btn btn-outline-secondary"><?= icone('arrow-counterclockwise') ?>Reabrir guia</button>
                <span class="small text-muted ms-2">Use se a guia foi marcada como paga por engano.</span>
            </form>
        </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm" id="documentos">
    <div class="card-body">
        <h2 class="h5">Documentos</h2>
        <p class="text-muted small">Comprovantes e arquivos desta guia. PDF, JPG ou PNG de até 5 MB.</p>

        <?php if ($documentos === []): ?>
            <p class="small">Nenhum documento enviado ainda.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($documentos as $doc): ?>
                    <li class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                        <a href="<?= e(url('documentos/baixar', ['id' => $doc['id']])) ?>" class="text-break">
                            <?= icone('file-earmark-text') ?> <?= e($doc['nome_original']) ?>
                        </a>
                        <small class="text-muted">
                            <?= e(tamanho_arquivo($doc['tamanho'])) ?>
                            · <?= e($doc['enviado_por'] ?? 'usuário removido') ?>
                            · <?= e(date('d/m/Y H:i', (int) strtotime($doc['enviado_em']))) ?>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="<?= e(url('documentos/enviar')) ?>" enctype="multipart/form-data"
              class="d-flex flex-wrap gap-2 align-items-center">
            <?= csrf_campo() ?>
            <input type="hidden" name="obrigacao_id" value="<?= e($obrigacao['id']) ?>">
            <input type="file" name="arquivo" required class="form-control w-auto flex-grow-1"
                   accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" aria-label="Arquivo">
            <button type="submit" class="btn btn-primary"><?= icone('paperclip') ?>Enviar documento</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mt-4" id="historico">
    <div class="card-body">
        <h2 class="h5">Histórico</h2>
        <?php if ($historico === []): ?>
            <p class="small text-muted mb-0">Nenhuma ação registrada.</p>
        <?php else: ?>
            <ol class="list-unstyled mb-0 linha-do-tempo">
                <?php foreach ($historico as $h): ?>
                    <li class="acao-<?= e($h['acao']) ?>">
                        <strong><?= e(\App\Models\Historico::ROTULOS[$h['acao']] ?? $h['acao']) ?></strong>
                        por <?= e($h['nome'] ?? 'usuário removido') ?>
                        <?php if ($h['perfil'] !== null): ?><span class="badge text-bg-light border"><?= e($h['perfil']) ?></span><?php endif; ?>
                        em <?= e(date('d/m/Y H:i', (int) strtotime($h['criado_em']))) ?>
                        <?php if ($h['detalhe'] !== null): ?>
                            <div class="small text-muted"><?= e($h['detalhe']) ?></div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>
