<?php
/**
 * Faixa no topo para o cliente: guias vencidas e vencendo em breve.
 * Cada número é um link para a lista já filtrada.
 * @var array{vencidas: int, a_vencer: int} $avisoCliente
 */
use App\Services\Semaforo;

$vencidas = $avisoCliente['vencidas'];
$aVencer = $avisoCliente['a_vencer'];

$partes = [];
if ($vencidas > 0) {
    $partes[] = '<a class="alert-link" href="' . e(url('obrigacoes/index', ['situacao' => Semaforo::VENCIDA])) . '">'
        . e($vencidas) . ' guia(s) vencida(s)</a>';
}
if ($aVencer > 0) {
    $partes[] = '<a class="alert-link" href="' . e(url('obrigacoes/index', ['situacao' => Semaforo::ALERTA])) . '">'
        . e($aVencer) . ($vencidas > 0 ? '' : ' guia(s)') . ' vencendo nos próximos ' . e(Semaforo::DIAS_ALERTA) . ' dias</a>';
}
?>
<div class="alert alert-<?= $vencidas > 0 ? 'danger' : 'warning' ?> d-flex align-items-center gap-2" role="alert">
    <span class="me-auto">Você tem <?= implode(' e ', $partes) /* partes já escapadas acima */ ?>.</span>
    <a class="btn btn-sm btn-light" href="<?= e(url('obrigacoes/agenda')) ?>"
       title="Baixa um arquivo .ics com todas as guias pendentes, com lembrete 3 dias antes">Adicionar à agenda</a>
    <form method="post" action="<?= e(url('avisos/fechar')) ?>" class="m-0">
        <?= csrf_campo() ?>
        <input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
        <button type="submit" class="btn-close" aria-label="Fechar aviso até o próximo login"></button>
    </form>
</div>
