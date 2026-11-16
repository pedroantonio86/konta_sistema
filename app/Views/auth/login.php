<?php
/**
 * @var array<string, string> $dados
 * @var array<string, string> $erros
 * @var string|null           $erroGeral
 */
?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm mt-md-4">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Entrar no Konta</h1>
                <p class="text-muted small mb-4">Guias e obrigações fiscais em um só lugar.</p>

                <?php if ($erroGeral !== null): ?>
                    <div class="alert alert-danger py-2" role="alert"><?= e($erroGeral) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('auth/login')) ?>" novalidate>
                    <?= csrf_campo() ?>

                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" id="email" name="email" autocomplete="username" autofocus
                               class="form-control<?= classe_invalido($erros, 'email') ?>"
                               value="<?= e($dados['email'] ?? '') ?>">
                        <?= erro_campo($erros, 'email') ?>
                    </div>

                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" id="senha" name="senha" autocomplete="current-password"
                               class="form-control<?= classe_invalido($erros, 'senha') ?>">
                        <?= erro_campo($erros, 'senha') ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</div>
