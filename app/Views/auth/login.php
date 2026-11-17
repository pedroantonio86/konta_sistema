<?php
/**
 * Tela de login. Telas médias ou maiores: duas colunas (marca à esquerda,
 * formulário à direita). Tela pequena: só o formulário, com o logo em cima.
 * Campos e validação são os mesmos de antes; muda só o visual.
 *
 * @var array<string, string> $dados
 * @var array<string, string> $erros
 * @var string|null           $erroGeral
 */
?>
<div class="card shadow-sm overflow-hidden mx-auto login-caixa">
    <div class="row g-0">
        <div class="col-md-5 login-marca d-none d-md-flex flex-column justify-content-between p-5">
            <?= logo_konta(true) ?>
            <div>
                <p class="login-slogan mb-3">Nenhuma guia esquecida.</p>
                <p class="login-apoio mb-0">Prazos, guias e comprovantes do escritório em um só lugar.</p>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card-body p-4 p-md-5">
                <div class="d-md-none mb-4"><?= logo_konta() ?></div>

                <h1 class="h4 mb-1">Entrar no Konta</h1>
                <p class="text-muted small mb-4">Use o e-mail e a senha que o escritório enviou.</p>

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
