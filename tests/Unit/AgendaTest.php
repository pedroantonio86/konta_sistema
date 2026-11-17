<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Agenda;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Arquivo .ics (iCalendar, RFC 5545) com os vencimentos: o celular
 * importa e cria um evento por guia, com lembrete 3 dias antes.
 */
final class AgendaTest extends TestCase
{
    /** 17/11/2026 12:00 em Teresina = 15:00 UTC. */
    private static function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-17 12:00:00', new DateTimeZone('America/Fortaleza'));
    }

    /** Linha de obrigação como vem do banco (com dados da empresa). */
    private static function obrigacao(array $alterar = []): array
    {
        return $alterar + [
            'id'            => 7,
            'tipo'          => 'DAS',
            'competencia'   => '2026-10-01',
            'valor'         => '387.40',
            'vencimento'    => '2026-11-20',
            'razao_social'  => 'Padaria Pão Quente Ltda',
            'nome_fantasia' => 'Pão Quente',
        ];
    }

    /** Junta as linhas dobradas de volta (RFC 5545, 3.1: CRLF + espaço = continuação). */
    private static function desdobrar(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }

    // ------------------------------------------------------------------
    // Escape de texto
    // ------------------------------------------------------------------

    public function testEscapaBarraPontoEVirgulaVirgulaEQuebraDeLinha(): void
    {
        $this->assertSame('a\\\\b\\;c\\,d\\ne\\nf', Agenda::escapar("a\\b;c,d\ne\r\nf"));
    }

    public function testTextoSemCaracteresEspeciaisNaoMuda(): void
    {
        $this->assertSame('Pão Quente', Agenda::escapar('Pão Quente'));
    }

    // ------------------------------------------------------------------
    // Estrutura do calendário
    // ------------------------------------------------------------------

    public function testListaVaziaGeraCalendarioValidoSemEventos(): void
    {
        $ics = Agenda::gerar([], self::agora());

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("\r\nVERSION:2.0\r\n", $ics);
        $this->assertStringContainsString("\r\nPRODID:", $ics);
        $this->assertStringContainsString("\r\nX-WR-CALNAME:Konta\r\n", $ics);
        $this->assertStringNotContainsString('BEGIN:VEVENT', $ics);
    }

    public function testTodasAsLinhasTerminamEmCrlf(): void
    {
        $ics = Agenda::gerar([self::obrigacao()], self::agora());

        $this->assertSame(0, preg_match("/(?<!\r)\n/", $ics), 'Há quebra de linha sem \\r');
        $this->assertStringEndsWith("\r\n", $ics);
    }

    public function testUmEventoPorObrigacao(): void
    {
        $ics = Agenda::gerar([self::obrigacao(), self::obrigacao(['id' => 8, 'tipo' => 'FGTS'])], self::agora());

        $this->assertSame(2, substr_count($ics, "BEGIN:VEVENT\r\n"));
        $this->assertSame(2, substr_count($ics, "END:VEVENT\r\n"));
    }

    // ------------------------------------------------------------------
    // Campos do evento
    // ------------------------------------------------------------------

    public function testUidEstavelPorObrigacao(): void
    {
        $ontem = self::agora()->modify('-1 day');

        $this->assertStringContainsString("\r\nUID:obrigacao-7@konta\r\n", Agenda::gerar([self::obrigacao()], self::agora()));
        // Mesmo UID em outra exportação: reimportar ATUALIZA o evento, não duplica.
        $this->assertStringContainsString("\r\nUID:obrigacao-7@konta\r\n", Agenda::gerar([self::obrigacao()], $ontem));
    }

    public function testDatasDoEventoDeDiaInteiro(): void
    {
        $ics = Agenda::gerar([self::obrigacao()], self::agora());

        $this->assertStringContainsString("\r\nDTSTART;VALUE=DATE:20261120\r\n", $ics);
        $this->assertStringContainsString("\r\nDTEND;VALUE=DATE:20261121\r\n", $ics);   // dia seguinte (exclusivo)
        $this->assertStringContainsString("\r\nDTSTAMP:20261117T150000Z\r\n", $ics);    // agora, em UTC
    }

    public function testFimDeAnoViraParaJaneiro(): void
    {
        $ics = Agenda::gerar([self::obrigacao(['vencimento' => '2026-12-31'])], self::agora());

        $this->assertStringContainsString("\r\nDTEND;VALUE=DATE:20270101\r\n", $ics);
    }

    public function testResumoComTipoCompetenciaEmpresaEValor(): void
    {
        $ics = self::desdobrar(Agenda::gerar([self::obrigacao()], self::agora()));

        // A vírgula do valor é escapada, como manda a RFC.
        $this->assertStringContainsString("\r\nSUMMARY:DAS 10/2026 - Pão Quente - R$ 387\\,40\r\n", $ics);
    }

    public function testResumoUsaRazaoSocialSemNomeFantasia(): void
    {
        $ics = self::desdobrar(Agenda::gerar([self::obrigacao(['nome_fantasia' => null])], self::agora()));

        $this->assertStringContainsString('SUMMARY:DAS 10/2026 - Padaria Pão Quente Ltda - R$ 387\\,40', $ics);
    }

    public function testDescricaoComEmpresaCompetenciaEValor(): void
    {
        $ics = self::desdobrar(Agenda::gerar([self::obrigacao(['valor' => '1234.56'])], self::agora()));

        $this->assertMatchesRegularExpression('/\r\nDESCRIPTION:[^\r]*Padaria Pão Quente Ltda/', $ics);
        $this->assertMatchesRegularExpression('/\r\nDESCRIPTION:[^\r]*Competência: 10\/2026/', $ics);
        $this->assertMatchesRegularExpression('/\r\nDESCRIPTION:[^\r]*R\$ 1\.234\\\\,56/', $ics);
        $this->assertMatchesRegularExpression('/\r\nDESCRIPTION:[^\r]*\\\\n/', $ics); // quebras escapadas
    }

    public function testLembreteTresDiasAntes(): void
    {
        $ics = Agenda::gerar([self::obrigacao()], self::agora());

        $this->assertMatchesRegularExpression(
            "/BEGIN:VALARM\r\nACTION:DISPLAY\r\n(?:[^\r]*\r\n)*?TRIGGER:-P3D\r\nEND:VALARM\r\n/",
            $ics
        );
    }

    // ------------------------------------------------------------------
    // Dobra de linhas longas (RFC 5545, 3.1)
    // ------------------------------------------------------------------

    public function testLinhaAteSetentaECincoBytesNaoDobra(): void
    {
        $linha = 'SUMMARY:' . str_repeat('a', 67); // 75 bytes

        $this->assertSame($linha, Agenda::dobrar($linha));
    }

    public function testLinhaLongaDobraEmPedacosDeAteSetentaECincoBytes(): void
    {
        $linha = 'DESCRIPTION:' . str_repeat('x', 200);
        $dobrada = Agenda::dobrar($linha);

        foreach (explode("\r\n", $dobrada) as $i => $pedaco) {
            $this->assertLessThanOrEqual(75, strlen($pedaco));
            if ($i > 0) {
                $this->assertStringStartsWith(' ', $pedaco); // continuação começa com espaço
            }
        }
        $this->assertSame($linha, str_replace("\r\n ", '', $dobrada));
    }

    public function testDobraNaoQuebraCaractereAcentuadoAoMeio(): void
    {
        $linha = 'SUMMARY:' . str_repeat('ção ', 40); // "ç" e "ã" ocupam 2 bytes
        $dobrada = Agenda::dobrar($linha);

        foreach (explode("\r\n", $dobrada) as $pedaco) {
            $this->assertTrue(mb_check_encoding($pedaco, 'UTF-8'), 'Pedaço com UTF-8 quebrado');
            $this->assertLessThanOrEqual(75, strlen($pedaco));
        }
        $this->assertSame($linha, str_replace("\r\n ", '', $dobrada));
    }

    public function testCalendarioGeradoNaoTemLinhaAcimaDeSetentaECincoBytes(): void
    {
        $longa = self::obrigacao(['nome_fantasia' => str_repeat('Empresa Muito Comprida ', 6)]);

        foreach (explode("\r\n", Agenda::gerar([$longa], self::agora())) as $linha) {
            $this->assertLessThanOrEqual(75, strlen($linha));
        }
    }
}
