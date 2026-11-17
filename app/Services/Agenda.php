<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Obrigacao;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Gera o arquivo .ics (iCalendar, RFC 5545) com os vencimentos.
 * Aberto no celular (Android/iPhone) ou no Outlook/Google Agenda, cria um
 * evento de dia inteiro por guia, com lembrete 3 dias antes.
 *
 * Sem banco e sem relógio próprio: recebe as linhas e o "agora", por isso
 * é testável. Detalhes da RFC que o arquivo respeita:
 * - linhas terminadas em CRLF ("\r\n");
 * - texto com \ ; , e quebra de linha escapados;
 * - linhas com mais de 75 bytes "dobradas" (continuação começa com espaço);
 * - UID estável por guia: reimportar atualiza o evento em vez de duplicar.
 */
final class Agenda
{
    private const PRODID = '-//Konta//Vencimentos//PT-BR';
    private const LIMITE_BYTES = 75;
    private const LEMBRETE = '-P3D'; // 3 dias antes do vencimento

    /**
     * @param list<array<string, mixed>> $obrigacoes linhas com tipo, competencia, valor,
     *                                               vencimento, id, razao_social, nome_fantasia
     */
    public static function gerar(array $obrigacoes, DateTimeImmutable $agora): string
    {
        $carimbo = $agora->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');

        $linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Konta',
        ];
        foreach ($obrigacoes as $obrigacao) {
            array_push($linhas, ...self::evento($obrigacao, $carimbo));
        }
        $linhas[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'dobrar'], $linhas)) . "\r\n";
    }

    /** Escapa texto de propriedade (RFC 5545, 3.3.11): \ ; , e quebra de linha. */
    public static function escapar(string $texto): string
    {
        $texto = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $texto);

        return (string) preg_replace('/\r\n|\r|\n/', '\\n', $texto);
    }

    /**
     * Dobra uma linha em pedaços de até 75 BYTES (RFC 5545, 3.1). Cada
     * continuação começa com um espaço, que conta nos 75. Conta caractere
     * a caractere para nunca partir um "ç" (2 bytes em UTF-8) ao meio.
     */
    public static function dobrar(string $linha): string
    {
        if (strlen($linha) <= self::LIMITE_BYTES) {
            return $linha;
        }

        $pedacos = [];
        $atual = '';
        $limite = self::LIMITE_BYTES; // a 1ª linha tem 75; as seguintes, 74 + o espaço

        foreach (mb_str_split($linha, 1, 'UTF-8') as $caractere) {
            if (strlen($atual) + strlen($caractere) > $limite) {
                $pedacos[] = $atual;
                $atual = '';
                $limite = self::LIMITE_BYTES - 1;
            }
            $atual .= $caractere;
        }
        $pedacos[] = $atual;

        return implode("\r\n ", $pedacos);
    }

    /**
     * @param array<string, mixed> $o
     * @return list<string>
     */
    private static function evento(array $o, string $carimbo): array
    {
        $empresa = $o['nome_fantasia'] ?? $o['razao_social'];
        $tipo = Obrigacao::TIPOS[$o['tipo']] ?? $o['tipo'];
        $competencia = date('m/Y', (int) strtotime($o['competencia']));
        $valor = 'R$ ' . number_format((float) $o['valor'], 2, ',', '.');
        $vencimento = new DateTimeImmutable($o['vencimento']);
        $resumo = "{$tipo} {$competencia} - {$empresa} - {$valor}";

        $descricao = implode("\n", [
            'Empresa: ' . $o['razao_social'],
            "Guia: {$tipo}",
            "Competência: {$competencia}",
            "Valor: {$valor}",
            'Se já pagou, marque como paga no Konta.',
        ]);

        return [
            'BEGIN:VEVENT',
            'UID:obrigacao-' . (int) $o['id'] . '@konta',
            "DTSTAMP:{$carimbo}",
            'DTSTART;VALUE=DATE:' . $vencimento->format('Ymd'),
            'DTEND;VALUE=DATE:' . $vencimento->modify('+1 day')->format('Ymd'), // fim é exclusivo
            'SUMMARY:' . self::escapar($resumo),
            'DESCRIPTION:' . self::escapar($descricao),
            'TRANSP:TRANSPARENT', // dia inteiro sem marcar a pessoa como "ocupada"
            'BEGIN:VALARM',
            'ACTION:DISPLAY',
            'DESCRIPTION:' . self::escapar("Vence em 3 dias: {$resumo}"),
            'TRIGGER:' . self::LEMBRETE,
            'END:VALARM',
            'END:VEVENT',
        ];
    }
}
