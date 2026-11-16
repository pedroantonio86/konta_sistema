<?php
/**
 * @var bool                                $ehContador
 * @var array<string, mixed>|null           $empresa     empresa do cliente (null para o contador)
 * @var array<string, int|string>           $indicadores
 * @var list<array<string, mixed>>          $proximas
 */
use App\Models\Obrigacao;
use App\Models\Painel;
use App\Services\Semaforo;

/**
 * Cada cartão leva para a listagem já filtrada: o número do painel é
 * também um atalho para agir (ex.: cobrar as vencidas).
 */
$cartoes = [
    [
        'titulo' => 'Vencidas',
        'numero' => $indicadores['vencidas'],
        'detalhe' => moeda($indicadores['valor_vencido']),
        'cor'    => Semaforo::classe(Semaforo::VENCIDA),
        'link'   => url('obrigacoes/index', ['situacao' => Semaforo::VENCIDA]),
    ],
    [
        'titulo' => 'Vencem em até ' . Semaforo::DIAS_ALERTA . ' dias',
        'numero' => $indicadores['a_vencer'],
        'detalhe' => moeda($indicadores['valor_a_vencer']),
        'cor'    => Semaforo::classe(Semaforo::ALERTA),
        'link'   => url('obrigacoes/index', ['situacao' => Semaforo::ALERTA]),
    ],
    [
        'titulo' => 'Pagas neste mês',
        'numero' => $indicadores['pagas_mes'],
        'detalhe' => 'guias quitadas',
        'cor'    => Semaforo::classe(Semaforo::PAGA),
        'link'   => url('obrigacoes/index', ['situacao' => Semaforo::PAGA]),
    ],
    [
        'titulo' => 'Total pendente',
        'numero' => moeda($indicadores['total_pendente']),
        'detalhe' => 'soma das guias não pagas',
        'cor'    => 'primary',
        'link'   => url('obrigacoes/index'),
    ],
];
?>
<div class="mb-4">
    <h1 class="h3 mb-0">Painel</h1>
    <span class="text-muted">
        <?= $empresa !== null ? e($empresa['razao_social']) : 'Visão geral do escritório' ?>
        · <?= e(date('d/m/Y')) ?>
    </span>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($cartoes as $cartao): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= e($cartao['link']) ?>" class="card h-100 shadow-sm text-decoration-none border-0 border-start border-4 border-<?= e($cartao['cor']) ?>">
                <div class="card-body">
                    <div class="small text-muted"><?= e($cartao['titulo']) ?></div>
                    <div class="fs-3 fw-bold text-<?= e($cartao['cor']) ?>-emphasis"><?= e($cartao['numero']) ?></div>
                    <div class="small text-muted"><?= e($cartao['detalhe']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Próximas <?= e(Painel::PROXIMAS) ?> a vencer</h2>
        <a class="small" href="<?= e(url('obrigacoes/index')) ?>">ver todas</a>
    </div>
    <?php if ($proximas === []): ?>
        <div class="card-body text-muted">Nenhuma guia pendente a vencer. 🎉</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <tbody>
                <?php foreach ($proximas as $o):
                    $situacao = Semaforo::calcular($o['status'], $o['vencimento']); ?>
                    <tr class="semaforo-<?= e($situacao) ?>">
                        <td class="text-nowrap">
                            <a href="<?= e(url('obrigacoes/ver', ['id' => $o['id']])) ?>" class="fw-semibold text-decoration-none">
                                <?= e(Obrigacao::TIPOS[$o['tipo']]) ?> <?= e(competencia_br($o['competencia'])) ?>
                            </a>
                            <?php if ($ehContador): ?>
                                <div class="small text-muted"><?= e($o['nome_fantasia'] ?? $o['razao_social']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap"><?= e(moeda($o['valor'])) ?></td>
                        <td class="text-nowrap">
                            <?= e(data_br($o['vencimento'])) ?>
                            <div class="small text-muted"><?= e(Semaforo::descreverPrazo($o['vencimento'])) ?></div>
                        </td>
                        <td class="text-end"><?= selo_semaforo($situacao) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
