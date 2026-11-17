<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Obrigacao;
use DateTimeImmutable;

/**
 * Cobrança em 1 clique: monta a mensagem pronta e o link
 * https://wa.me/55NUMERO?text=MENSAGEM, que abre o WhatsApp (app ou Web)
 * com o texto já digitado. O contador só confere e aperta "enviar".
 *
 * Sem API paga e sem guardar nada: é só um link.
 */
final class WhatsAppLink
{
    private const BASE = 'https://wa.me/';

    /**
     * Link de cobrança de uma obrigação (linha do banco com os dados da
     * empresa: razao_social, nome_fantasia, whatsapp).
     *
     * @param array<string, mixed> $obrigacao
     * @return string|null null quando a empresa não tem WhatsApp válido
     */
    public static function paraObrigacao(array $obrigacao, ?DateTimeImmutable $hoje = null): ?string
    {
        $numero = self::numero($obrigacao['whatsapp'] ?? null);

        if ($numero === null) {
            return null;
        }

        // rawurlencode (RFC 3986): espaço vira %20. O urlencode transformaria
        // espaço em "+", que pode aparecer literalmente na mensagem.
        return self::BASE . $numero . '?text=' . rawurlencode(self::mensagem($obrigacao, $hoje));
    }

    /**
     * Link de cobrança de uma EMPRESA (ranking do painel): uma mensagem com
     * o total vencido, em vez de uma por guia.
     *
     * @param array<string, mixed> $empresa razao_social, nome_fantasia, whatsapp
     * @param string               $total   valor vindo do banco ('9213.55')
     */
    public static function paraResumo(array $empresa, int $guias, string $total): ?string
    {
        $numero = self::numero($empresa['whatsapp'] ?? null);

        return $numero === null
            ? null
            : self::BASE . $numero . '?text=' . rawurlencode(self::mensagemResumo($empresa, $guias, $total));
    }

    /** @param array<string, mixed> $empresa */
    public static function mensagemResumo(array $empresa, int $guias, string $total): string
    {
        $nome = $empresa['nome_fantasia'] ?? $empresa['razao_social'];
        $quantidade = $guias === 1 ? '1 guia vencida' : "{$guias} guias vencidas";

        return implode("\n", [
            "Olá, *{$nome}*!",
            "Lembrete do seu escritório de contabilidade: há {$quantidade}, somando R$ "
                . number_format((float) $total, 2, ',', '.') . '.',
            '',
            'Os detalhes estão no Konta. Se já pagou, é só anexar o comprovante lá. Obrigado!',
        ]);
    }

    /**
     * Central de cobrança: UMA mensagem com TODAS as guias pendentes da
     * empresa (vencidas e a vencer) e o total.
     *
     * @param array<string, mixed>       $empresa razao_social, nome_fantasia, whatsapp
     * @param list<array<string, mixed>> $guias   tipo, competencia, valor, vencimento
     */
    public static function paraCobranca(array $empresa, array $guias, ?DateTimeImmutable $hoje = null): ?string
    {
        $numero = self::numero($empresa['whatsapp'] ?? null);

        return $numero === null
            ? null
            : self::BASE . $numero . '?text=' . rawurlencode(self::mensagemCobranca($empresa, $guias, $hoje));
    }

    /**
     * @param array<string, mixed>       $empresa
     * @param list<array<string, mixed>> $guias
     */
    public static function mensagemCobranca(array $empresa, array $guias, ?DateTimeImmutable $hoje = null): string
    {
        $nome = $empresa['nome_fantasia'] ?? $empresa['razao_social'];
        $quantidade = count($guias) === 1 ? '1 guia pendente' : count($guias) . ' guias pendentes';
        $total = 0.0;
        $linhas = [];

        foreach ($guias as $g) {
            $total += (float) $g['valor'];
            $linhas[] = sprintf(
                '• %s %s · %s · vence %s (%s)',
                Obrigacao::TIPOS[$g['tipo']] ?? $g['tipo'],
                date('m/Y', (int) strtotime($g['competencia'])),
                (float) $g['valor'] > 0 ? 'R$ ' . number_format((float) $g['valor'], 2, ',', '.') : 'sem valor a pagar',
                date('d/m/Y', (int) strtotime($g['vencimento'])),
                Semaforo::descreverPrazo($g['vencimento'], $hoje)
            );
        }

        return implode("\n", [
            "Olá, *{$nome}*!",
            "Lembrete do seu escritório de contabilidade: há {$quantidade}:",
            '',
            ...$linhas,
            '',
            '*Total: R$ ' . number_format($total, 2, ',', '.') . '*',
            '',
            'Se já pagou, é só anexar o comprovante no Konta. Obrigado!',
        ]);
    }

    /** '(86) 99999-0001' -> '5586999990001'; inválido ou vazio -> null. */
    public static function numero(?string $telefone): ?string
    {
        if ($telefone === null || !Validator::whatsappValido($telefone)) {
            return null;
        }

        return '55' . Validator::normalizarWhatsapp($telefone);
    }

    /** @param array<string, mixed> $obrigacao */
    public static function mensagem(array $obrigacao, ?DateTimeImmutable $hoje = null): string
    {
        $empresa = $obrigacao['nome_fantasia'] ?? $obrigacao['razao_social'];
        $tipo = Obrigacao::TIPOS[$obrigacao['tipo']] ?? $obrigacao['tipo'];
        $valor = (float) $obrigacao['valor'] > 0
            ? 'R$ ' . number_format((float) $obrigacao['valor'], 2, ',', '.')
            : 'sem valor a pagar (declaração)';
        $prazo = Semaforo::descreverPrazo($obrigacao['vencimento'], $hoje);

        return implode("\n", [
            "Olá, *{$empresa}*!",
            'Lembrete do seu escritório de contabilidade:',
            '',
            "• Guia: {$tipo}",
            '• Competência: ' . date('m/Y', (int) strtotime($obrigacao['competencia'])),
            "• Valor: {$valor}",
            '• Vencimento: ' . date('d/m/Y', (int) strtotime($obrigacao['vencimento'])) . " ({$prazo})",
            '',
            'Se já pagou, é só anexar o comprovante no Konta. Obrigado!',
        ]);
    }
}
