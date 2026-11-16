<?php
/**
 * @var array<string, string> $dados
 * @var array<string, string> $erros
 * @var array<int, string>    $notas
 */
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <h1 class="h3 mb-1">Avaliar o Konta</h1>
        <p class="text-muted">Sua opinião entra no relatório do projeto e orienta as próximas melhorias.</p>

        <form method="post" action="<?= e(url('avaliacoes/salvar')) ?>" class="card card-body shadow-sm" novalidate>
            <?= csrf_campo() ?>

            <fieldset class="mb-3">
                <legend class="form-label fs-6">Que nota você dá para o sistema? *</legend>
                <div class="d-flex flex-wrap gap-2" role="radiogroup">
                    <?php foreach ($notas as $valor => $rotulo): ?>
                        <input type="radio" class="btn-check" name="nota" id="nota-<?= e($valor) ?>"
                               value="<?= e($valor) ?>" autocomplete="off"
                               <?= $dados['nota'] === (string) $valor ? 'checked' : '' ?>>
                        <label class="btn btn-outline-warning text-body" for="nota-<?= e($valor) ?>">
                            <?= str_repeat('★', $valor) ?><br><small><?= e($rotulo) ?></small>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($erros['nota'])): ?>
                    <div class="text-danger small mt-1"><?= e($erros['nota']) ?></div>
                <?php endif; ?>
            </fieldset>

            <div class="mb-3">
                <label for="comentario" class="form-label">O que funcionou bem? O que poderia melhorar?</label>
                <textarea id="comentario" name="comentario" rows="4" maxlength="1000"
                          class="form-control<?= classe_invalido($erros, 'comentario') ?>"><?= e($dados['comentario']) ?></textarea>
                <?= erro_campo($erros, 'comentario') ?>
                <div class="form-text">Opcional, até 1000 caracteres.</div>
            </div>

            <div>
                <button type="submit" class="btn btn-primary">Enviar avaliação</button>
            </div>
        </form>
    </div>
</div>
