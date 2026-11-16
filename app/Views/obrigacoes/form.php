<?php
/**
 * Formulário único para criar e editar (com $id = edição).
 * @var array<string, string> $dados
 * @var array<string, string> $erros
 * @var int|null              $id
 * @var string                $titulo
 * @var array<int, string>    $empresas
 */
use App\Models\Obrigacao;

$editando = $id !== null;
?>
<h1 class="h3 mb-3"><?= e($titulo) ?></h1>

<?php if ($erros !== []): ?>
    <div class="alert alert-danger py-2">Corrija os campos destacados.</div>
<?php endif; ?>

<form method="post" action="<?= e(url($editando ? 'obrigacoes/atualizar' : 'obrigacoes/salvar')) ?>"
      class="card card-body shadow-sm" novalidate>
    <?= csrf_campo() ?>
    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="empresa_id" class="form-label">Empresa *</label>
            <select id="empresa_id" name="empresa_id" required class="form-select<?= classe_invalido($erros, 'empresa_id') ?>">
                <option value="">Selecione…</option>
                <?php foreach ($empresas as $empresaId => $nome): ?>
                    <option value="<?= e($empresaId) ?>"<?= $dados['empresa_id'] === (string) $empresaId ? ' selected' : '' ?>><?= e($nome) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erro_campo($erros, 'empresa_id') ?>
        </div>

        <div class="col-md-3">
            <label for="tipo" class="form-label">Tipo *</label>
            <select id="tipo" name="tipo" required class="form-select<?= classe_invalido($erros, 'tipo') ?>">
                <option value="">Selecione…</option>
                <?php foreach (Obrigacao::TIPOS as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>"<?= $dados['tipo'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erro_campo($erros, 'tipo') ?>
        </div>

        <div class="col-md-3">
            <label for="competencia" class="form-label">Competência *</label>
            <input type="month" id="competencia" name="competencia" required
                   class="form-control<?= classe_invalido($erros, 'competencia') ?>"
                   value="<?= e($dados['competencia']) ?>">
            <?= erro_campo($erros, 'competencia') ?>
            <div class="form-text">Mês a que a guia se refere.</div>
        </div>

        <div class="col-md-3">
            <label for="valor" class="form-label">Valor (R$) *</label>
            <input type="text" id="valor" name="valor" inputmode="decimal" required placeholder="0,00"
                   class="form-control<?= classe_invalido($erros, 'valor') ?>"
                   value="<?= e($dados['valor']) ?>">
            <?= erro_campo($erros, 'valor') ?>
            <div class="form-text">Use 0 para declarações.</div>
        </div>

        <div class="col-md-3">
            <label for="vencimento" class="form-label">Vencimento *</label>
            <input type="date" id="vencimento" name="vencimento" required
                   class="form-control<?= classe_invalido($erros, 'vencimento') ?>"
                   value="<?= e($dados['vencimento']) ?>">
            <?= erro_campo($erros, 'vencimento') ?>
        </div>

        <div class="col-md-6">
            <label for="observacao" class="form-label">Observação</label>
            <textarea id="observacao" name="observacao" rows="2" maxlength="500"
                      class="form-control<?= classe_invalido($erros, 'observacao') ?>"><?= e($dados['observacao']) ?></textarea>
            <?= erro_campo($erros, 'observacao') ?>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Salvar alterações' : 'Cadastrar' ?></button>
        <a class="btn btn-outline-secondary"
           href="<?= e($editando ? url('obrigacoes/ver', ['id' => $id]) : url('obrigacoes/index')) ?>">Cancelar</a>
    </div>
</form>
