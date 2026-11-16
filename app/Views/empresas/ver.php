<?php
/**
 * @var array<string, mixed> $empresa
 * @var int                  $obrigacoes total de obrigações da empresa
 * @var list<array<string, mixed>> $usuarios   logins de cliente desta empresa
 * @var array<string, string>      $acesso     valores do formulário de novo acesso
 * @var array<string, string>      $errosAcesso
 */
use App\Models\Empresa;
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('empresas/index')) ?>">Empresas</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($empresa['nome_fantasia'] ?? $empresa['razao_social']) ?></li>
    </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h3 mb-0"><?= e($empresa['razao_social']) ?></h1>
        <span class="text-muted"><?= e(cnpj_formatado($empresa['cnpj'])) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="<?= e(url('empresas/editar', ['id' => $empresa['id']])) ?>">Editar</a>
        <form method="post" action="<?= e(url('empresas/excluir')) ?>" class="m-0"
              onsubmit="return confirm('Excluir esta empresa e os logins de cliente dela?');">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= e($empresa['id']) ?>">
            <button type="submit" class="btn btn-outline-danger">Excluir</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Nome fantasia</dt>
            <dd class="col-sm-9"><?= e($empresa['nome_fantasia'] ?? '—') ?></dd>

            <dt class="col-sm-3">Regime</dt>
            <dd class="col-sm-9"><?= e(Empresa::REGIMES[$empresa['regime']] ?? $empresa['regime']) ?></dd>

            <dt class="col-sm-3">E-mail</dt>
            <dd class="col-sm-9"><?= e($empresa['email'] ?? '—') ?></dd>

            <dt class="col-sm-3">WhatsApp</dt>
            <dd class="col-sm-9"><?= e(telefone_formatado($empresa['whatsapp']) ?: '—') ?></dd>

            <dt class="col-sm-3">Obrigações</dt>
            <dd class="col-sm-9 mb-0">
                <?= e($obrigacoes) ?> cadastrada(s)
                <?php if ($obrigacoes > 0): ?>
                    · <a href="<?= e(url('obrigacoes/index', ['empresa_id' => $empresa['id']])) ?>">ver obrigações</a>
                <?php endif; ?>
                · <a href="<?= e(url('obrigacoes/criar', ['empresa_id' => $empresa['id']])) ?>">+ nova obrigação</a>
            </dd>
        </dl>
    </div>
</div>

<div class="card shadow-sm" id="acesso">
    <div class="card-body">
        <h2 class="h5">Acesso do cliente</h2>
        <p class="text-muted small">Quem tem login aqui vê só as obrigações desta empresa e pode enviar comprovantes.</p>

        <?php if ($usuarios === []): ?>
            <p class="small">Nenhum login criado ainda.</p>
        <?php else: ?>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($usuarios as $usuario): ?>
                    <li class="list-group-item px-0 d-flex justify-content-between">
                        <span><?= e($usuario['nome']) ?> <span class="text-muted">· <?= e($usuario['email']) ?></span></span>
                        <small class="text-muted">desde <?= e(data_br($usuario['criado_em'])) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="<?= e(url('empresas/acesso')) ?>#acesso" class="row g-2 align-items-start" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="empresa_id" value="<?= e($empresa['id']) ?>">
            <div class="col-md-4">
                <label for="acesso-nome" class="form-label small mb-1">Nome</label>
                <input type="text" id="acesso-nome" name="nome" maxlength="100" required
                       class="form-control<?= classe_invalido($errosAcesso, 'nome') ?>" value="<?= e($acesso['nome']) ?>">
                <?= erro_campo($errosAcesso, 'nome') ?>
            </div>
            <div class="col-md-4">
                <label for="acesso-email" class="form-label small mb-1">E-mail</label>
                <input type="email" id="acesso-email" name="email" maxlength="150" required autocomplete="off"
                       class="form-control<?= classe_invalido($errosAcesso, 'email') ?>" value="<?= e($acesso['email']) ?>">
                <?= erro_campo($errosAcesso, 'email') ?>
            </div>
            <div class="col-md-3">
                <label for="acesso-senha" class="form-label small mb-1">Senha inicial</label>
                <input type="password" id="acesso-senha" name="senha" minlength="8" required autocomplete="new-password"
                       class="form-control<?= classe_invalido($errosAcesso, 'senha') ?>">
                <?= erro_campo($errosAcesso, 'senha') ?>
                <div class="form-text">Mínimo de 8 caracteres.</div>
            </div>
            <div class="col-md-1 d-grid" style="margin-top: 1.9rem;">
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form>
    </div>
</div>
