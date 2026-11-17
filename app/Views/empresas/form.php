<?php
/**
 * Formulário único para criar e editar (com $id = edição).
 * @var array<string, string> $dados  valores digitados (mantidos quando há erro)
 * @var array<string, string> $erros
 * @var int|null              $id
 * @var string                $titulo
 */
use App\Models\Empresa;

$editando = $id !== null;
?>
<h1 class="h3 mb-3"><?= e($titulo) ?></h1>

<?php if ($erros !== []): ?>
    <div class="alert alert-danger py-2">Corrija os campos destacados.</div>
<?php endif; ?>

<form method="post" action="<?= e(url($editando ? 'empresas/atualizar' : 'empresas/salvar')) ?>"
      class="card card-body shadow-sm" novalidate>
    <?= csrf_campo() ?>
    <?php if ($editando): ?>
        <input type="hidden" name="id" value="<?= e($id) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-8">
            <label for="razao_social" class="form-label">Razão social *</label>
            <input type="text" id="razao_social" name="razao_social" maxlength="150" required
                   class="form-control<?= classe_invalido($erros, 'razao_social') ?>"
                   value="<?= e($dados['razao_social']) ?>">
            <?= erro_campo($erros, 'razao_social') ?>
        </div>

        <div class="col-md-4">
            <label for="nome_fantasia" class="form-label">Nome fantasia</label>
            <input type="text" id="nome_fantasia" name="nome_fantasia" maxlength="150"
                   class="form-control<?= classe_invalido($erros, 'nome_fantasia') ?>"
                   value="<?= e($dados['nome_fantasia']) ?>">
            <?= erro_campo($erros, 'nome_fantasia') ?>
        </div>

        <div class="col-md-4">
            <label for="cnpj" class="form-label">CNPJ *</label>
            <input type="text" id="cnpj" name="cnpj" maxlength="18" required placeholder="00.000.000/0000-00"
                   data-mascara="cnpj" autocapitalize="characters" spellcheck="false" autocomplete="off"
                   class="form-control<?= classe_invalido($erros, 'cnpj') ?>"
                   value="<?= e($dados['cnpj']) ?>">
            <?= erro_campo($erros, 'cnpj') ?>
            <div class="form-text">Aceita o novo CNPJ alfanumérico.</div>
        </div>

        <div class="col-md-4">
            <label for="regime" class="form-label">Regime tributário *</label>
            <select id="regime" name="regime" required class="form-select<?= classe_invalido($erros, 'regime') ?>">
                <option value="">Selecione…</option>
                <?php foreach (Empresa::REGIMES as $valor => $rotulo): ?>
                    <option value="<?= e($valor) ?>"<?= $dados['regime'] === $valor ? ' selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
            <?= erro_campo($erros, 'regime') ?>
        </div>

        <div class="col-md-4">
            <label for="whatsapp" class="form-label">WhatsApp</label>
            <input type="tel" id="whatsapp" name="whatsapp" maxlength="20" placeholder="(86) 99999-0000"
                   data-mascara="telefone"
                   class="form-control<?= classe_invalido($erros, 'whatsapp') ?>"
                   value="<?= e($dados['whatsapp']) ?>">
            <?= erro_campo($erros, 'whatsapp') ?>
            <div class="form-text">Usado na cobrança em 1 clique.</div>
        </div>

        <div class="col-md-8">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" id="email" name="email" maxlength="150"
                   class="form-control<?= classe_invalido($erros, 'email') ?>"
                   value="<?= e($dados['email']) ?>">
            <?= erro_campo($erros, 'email') ?>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><?= $editando ? 'Salvar alterações' : 'Cadastrar' ?></button>
        <a class="btn btn-outline-secondary"
           href="<?= e($editando ? url('empresas/ver', ['id' => $id]) : url('empresas/index')) ?>">Cancelar</a>
    </div>
</form>
