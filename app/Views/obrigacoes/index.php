<?php
/**
 * @var array{busca: string, empresa_id: ?int, tipo: string, situacao: string, competencia: string} $filtros
 * @var list<array<string, mixed>> $obrigacoes
 * @var \App\Core\Paginacao        $paginacao
 * @var array<int, string>         $empresas
 * @var array<string, string>      $filtrosLink
 * @var bool                       $ehContador
 */
use App\Models\Obrigacao;
use App\Services\Semaforo;

$temFiltro = $filtrosLink !== [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0"><?= $ehContador ? 'Obrigações' : 'Minhas obrigações' ?></h1>
    <div class="d-flex gap-2">
        <?php if ($obrigacoes !== []): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url('obrigacoes/exportar', $filtrosLink)) ?>"
               title="Baixa em CSV o que está filtrado, sem paginação">Exportar planilha</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('obrigacoes/agenda', $filtrosLink)) ?>"
               title="Baixa um arquivo .ics com os vencimentos pendentes filtrados, com lembrete 3 dias antes">Adicionar à agenda</a>
        <?php endif; ?>
        <?php if ($ehContador): ?>
            <a class="btn btn-primary" href="<?= e(url('obrigacoes/criar')) ?>">+ Nova obrigação</a>
        <?php endif; ?>
    </div>
</div>

<form method="get" action="<?= e(url()) ?>" class="card card-body shadow-sm mb-3" role="search">
    <input type="hidden" name="url" value="obrigacoes/index">
    <div class="row g-2 align-items-end">
        <div class="col-md-4 col-lg-3">
            <label for="f-busca" class="form-label small mb-1">Busca</label>
            <input type="search" id="f-busca" name="busca" class="form-control form-control-sm"
                   value="<?= e($filtros['busca']) ?>" placeholder="Empresa ou observação">
        </div>
        <?php if ($ehContador): ?>
            <div class="col-6 col-md-4 col-lg-2">
                <label for="f-empresa" class="form-label small mb-1">Empresa</label>
                <select id="f-empresa" name="empresa_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($empresas as $id => $nome): ?>
                        <option value="<?= e($id) ?>"<?= $filtros['empresa_id'] === $id ? ' selected' : '' ?>><?= e($nome) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="f-situacao" class="form-label small mb-1">Situação</label>
            <select id="f-situacao" name="situacao" class="form-select form-select-sm">
                <option value="">Todas</option>
                <?php foreach (Semaforo::opcoes() as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>"<?= $filtros['situacao'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="f-tipo" class="form-label small mb-1">Tipo</label>
            <select id="f-tipo" name="tipo" class="form-select form-select-sm">
                <option value="">Todos</option>
                <?php foreach (Obrigacao::TIPOS as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>"<?= $filtros['tipo'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label for="f-competencia" class="form-label small mb-1">Competência</label>
            <input type="month" id="f-competencia" name="competencia" class="form-control form-control-sm"
                   value="<?= e($filtros['competencia']) ?>">
        </div>
        <div class="col-md-4 col-lg-1 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filtrar</button>
        </div>
    </div>
    <?php if ($temFiltro): ?>
        <div class="mt-2"><a class="small" href="<?= e(url('obrigacoes/index')) ?>">Limpar filtros</a></div>
    <?php endif; ?>
</form>

<?php if ($obrigacoes === []): ?>
    <div class="alert alert-light border">
        <?= $temFiltro ? 'Nenhuma obrigação encontrada com esses filtros.' : 'Nenhuma obrigação cadastrada ainda.' ?>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white border">
            <thead class="table-light">
            <tr>
                <th>Situação</th>
                <?php if ($ehContador): ?><th>Empresa</th><?php endif; ?>
                <th>Guia</th>
                <th class="text-end">Valor</th>
                <th>Vencimento</th>
                <th class="text-end">Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($obrigacoes as $o):
                $situacao = Semaforo::calcular($o['status'], $o['vencimento']); ?>
                <tr class="semaforo-<?= e($situacao) ?>">
                    <td><?= selo_semaforo($situacao) ?></td>
                    <?php if ($ehContador): ?><td><?= e($o['nome_fantasia'] ?? $o['razao_social']) ?></td><?php endif; ?>
                    <td class="text-nowrap">
                        <a href="<?= e(url('obrigacoes/ver', ['id' => $o['id']])) ?>" class="fw-semibold text-decoration-none">
                            <?= e(Obrigacao::TIPOS[$o['tipo']]) ?> <?= e(competencia_br($o['competencia'])) ?>
                        </a>
                    </td>
                    <td class="text-end text-nowrap"><?= e(moeda($o['valor'])) ?></td>
                    <td class="text-nowrap">
                        <?= e(data_br($o['vencimento'])) ?>
                        <?php if ($situacao !== Semaforo::PAGA): ?>
                            <div class="small text-muted"><?= e(Semaforo::descreverPrazo($o['vencimento'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('obrigacoes/ver', ['id' => $o['id']])) ?>">Ver</a>
                        <?php if ($ehContador): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('obrigacoes/editar', ['id' => $o['id']])) ?>">Editar</a>
                            <?php if ($situacao !== Semaforo::PAGA): ?>
                                <?= botao_whatsapp($o, 'sm') ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php $rota = 'obrigacoes/index'; $filtros = $filtrosLink; require __DIR__ . '/../partials/paginacao.php'; ?>
