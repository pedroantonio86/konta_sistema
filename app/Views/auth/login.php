<?php
/**
 * Tela de login (dentro de layout_login.php). Só visual: os campos (email,
 * senha), o CSRF, a validação e as mensagens são os mesmos de antes.
 * Tela grande: texto à esquerda, cartão de login à direita.
 * Abaixo de lg: texto em cima, cartão logo abaixo, com largura total.
 *
 * @var array<string, string> $dados
 * @var array<string, string> $erros
 * @var string|null           $erroGeral
 */
?>
<div class="row align-items-center g-5">
    <div class="col-lg-7 text-white">
        <h1 class="login-titulo mb-3">Nenhuma guia esquecida.</h1>
        <p class="login-subtitulo mb-4">
            Prazos, guias e comprovantes do escritório e dos seus clientes em um só lugar.
        </p>
        <ul class="list-unstyled login-lista mb-0">
            <li><?= icone('check-circle-fill') ?><span><strong>Alerta</strong> antes de cada vencimento</span></li>
            <li><?= icone('check-circle-fill') ?><span>Cobrança pelo <strong>WhatsApp em 1 clique</strong></span></li>
            <li><?= icone('check-circle-fill') ?><span><strong>Comprovantes</strong> guardados na guia certa</span></li>
            <li><?= icone('check-circle-fill') ?><span>Painel com <strong>tudo o que está pendente</strong></span></li>
        </ul>
    </div>

    <div class="col-lg-5">
        <div class="login-cartao">
            <h2 class="login-cartao-titulo h4 mb-1">Entrar no Konta</h2>
            <p class="text-muted small mb-4">Use o e-mail e a senha que o escritório enviou.</p>

            <?php foreach (flash_consumir() as $aviso): // ex.: "Você saiu do sistema." ?>
                <div class="alert alert-<?= e($aviso['tipo']) ?> alert-dismissible fade show py-2" role="alert">
                    <?= e($aviso['mensagem']) ?>
                    <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endforeach; ?>

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

                <button type="submit" class="btn btn-primary w-100 rounded-pill">Entrar</button>
            </form>
        </div>
    </div>
</div>
