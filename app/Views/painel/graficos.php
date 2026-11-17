<?php
/**
 * Blocos de gráficos do painel (incluído por painel/index.php):
 * 1. barra de situação, 2. ranking de empresas (só contador), 3. vencimentos por semana.
 *
 * @var bool                                                  $ehContador
 * @var array<string, int>                                    $situacoes
 * @var list<array<string, mixed>>                            $ranking
 * @var list<array{rotulo: string, valor: float, guias: int}> $semanas
 */
use App\Models\Painel;
use App\Services\Semaforo;
use App\Services\WhatsAppLink;

$totalGuias = array_sum($situacoes);
// Número dentro do bloco só se couber com folga; senão ele vai para a legenda.
$cabeNoBloco = static fn (int $qtd): bool => $totalGuias > 0 && $qtd / $totalGuias >= 0.06;
$valorSemanas = array_sum(array_column($semanas, 'valor'));
?>

<!-- 1. Barra de situação ------------------------------------------------>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h6">Situação das <?= e($totalGuias) ?> guia(s)</h2>
        <?php if ($totalGuias === 0): ?>
            <?= estado_vazio('inbox', 'Nenhuma obrigação cadastrada ainda.') ?>
        <?php else: ?>
            <div class="progress-stacked barra-situacao" role="img"
                 aria-label="<?= e(implode(', ', array_map(
                     static fn (string $s, int $q): string => Semaforo::rotulo($s) . ': ' . $q,
                     array_keys($situacoes),
                     $situacoes
                 ))) ?>">
                <?php foreach ($situacoes as $situacao => $qtd): if ($qtd === 0) { continue; } ?>
                    <div class="progress" style="width: <?= e(round($qtd / $totalGuias * 100, 2)) ?>%"
                         title="<?= e(Semaforo::rotulo($situacao) . ': ' . $qtd . ' (' . round($qtd / $totalGuias * 100) . '%)') ?>">
                        <div class="progress-bar text-bg-<?= e(Semaforo::classe($situacao)) ?>">
                            <?= $cabeNoBloco($qtd) ? e($qtd) : '' ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <ul class="list-inline small mb-0 mt-2">
                <?php foreach ($situacoes as $situacao => $qtd): ?>
                    <li class="list-inline-item me-3">
                        <span class="legenda-cor bg-<?= e(Semaforo::classe($situacao)) ?>"></span>
                        <a class="text-body text-decoration-none" href="<?= e(url('obrigacoes/index', ['situacao' => $situacao])) ?>">
                            <?= e(Semaforo::rotulo($situacao)) ?><?= $cabeNoBloco($qtd) ? '' : ' (' . e($qtd) . ')' ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- 3. Vencimentos das próximas semanas ------------------------------>
    <div class="<?= $ehContador ? 'col-lg-7' : 'col-12' ?>">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-1">Vencimentos das próximas <?= e(Painel::SEMANAS) ?> semanas</h2>
                <p class="small text-muted"><?= e(moeda($valorSemanas)) ?> pendentes, por semana de vencimento</p>
                <div class="grafico-semanas">
                    <canvas id="grafico-semanas" role="img"
                            aria-label="Valor pendente por semana: <?= e(implode('; ', array_map(
                                static fn (array $s): string => $s['rotulo'] . ' ' . moeda($s['valor']),
                                $semanas
                            ))) ?>"></canvas>
                </div>
                <details class="small mt-2">
                    <summary>Ver os valores em tabela</summary>
                    <table class="table table-sm mb-0 mt-2">
                        <thead><tr><th>Semana</th><th class="text-end">Guias</th><th class="text-end">Valor</th></tr></thead>
                        <tbody>
                        <?php foreach ($semanas as $s): ?>
                            <tr>
                                <td><?= e($s['rotulo']) ?></td>
                                <td class="text-end"><?= e($s['guias']) ?></td>
                                <td class="text-end"><?= e(moeda($s['valor'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            </div>
        </div>
    </div>

    <!-- 2. Empresas com mais pendências (só contador) --------------------->
    <?php if ($ehContador): ?>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <h2 class="h6">Empresas com mais valor vencido</h2>
                        <a class="small" href="<?= e(url('obrigacoes/cobranca')) ?>">Central de cobrança →</a>
                    </div>
                    <?php if ($ranking === []): ?>
                        <?= estado_vazio('check2-circle', 'Nenhuma guia vencida. Tudo em dia!') ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Empresa</th><th class="text-end">Guias</th><th class="text-end">Valor</th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($ranking as $r):
                                    $link = WhatsAppLink::paraResumo($r, $r['guias'], $r['valor']); ?>
                                    <tr>
                                        <td>
                                            <a class="text-decoration-none" href="<?= e(url('obrigacoes/index', ['empresa_id' => $r['id'], 'situacao' => Semaforo::VENCIDA])) ?>">
                                                <?= e($r['nome_fantasia'] ?? $r['razao_social']) ?>
                                            </a>
                                        </td>
                                        <td class="text-end"><?= e($r['guias']) ?></td>
                                        <td class="text-end text-nowrap"><?= e(moeda($r['valor'])) ?></td>
                                        <td class="text-end">
                                            <?php if ($link === null): ?>
                                                <span class="d-inline-block" tabindex="0" title="Empresa sem WhatsApp válido cadastrado">
                                                    <button type="button" class="btn btn-sm btn-primary" disabled><?= icone('whatsapp') ?>Cobrar</button>
                                                </span>
                                            <?php else: ?>
                                                <a class="btn btn-sm btn-primary" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer"><?= icone('whatsapp') ?>Cobrar</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js 4.4.1, cópia local (public/assets/vendor): o painel funciona sem internet. -->
<script src="<?= e(asset('assets/vendor/chartjs/chart.umd.min.js')) ?>"
        integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4"></script>
<script>
(function () {
    // JSON_HEX_*: "<", ">", "&" e aspas viram < etc. Um texto vindo do banco
    // nunca consegue fechar este <script> e injetar código (XSS).
    var semanas = <?= json_encode($semanas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var canvas = document.getElementById('grafico-semanas');
    if (!window.Chart || !canvas) { return; } // sem o Chart.js, a tabela continua disponível

    var moeda = function (v) { return v.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'}); };
    var moedaEixo = function (v) { return v.toLocaleString('pt-BR', {style: 'currency', currency: 'BRL', maximumFractionDigits: 0}); };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: semanas.map(function (s) { return s.rotulo; }),
            datasets: [{
                label: 'Valor pendente',
                data: semanas.map(function (s) { return s.valor; }),
                backgroundColor: '#155E75', // cor da marca (o gráfico mostra valor, não situação)
                borderRadius: {topLeft: 4, topRight: 4},
                borderSkipped: 'bottom',
                maxBarThickness: 24
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {display: false}, // uma série só: o título já diz o que é
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            var guias = semanas[c.dataIndex].guias;
                            return moeda(c.parsed.y) + ' · ' + guias + (guias === 1 ? ' guia' : ' guias');
                        }
                    }
                }
            },
            scales: {
                x: {grid: {display: false}, border: {color: '#c3c2b7'}, ticks: {color: '#52514e'}},
                y: {
                    beginAtZero: true,
                    border: {display: false},
                    grid: {color: '#e1e0d9'},
                    ticks: {color: '#52514e', maxTicksLimit: 5, callback: function (v) { return moedaEixo(v); }}
                }
            }
        }
    });
})();
</script>
