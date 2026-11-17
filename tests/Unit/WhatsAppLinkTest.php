<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WhatsAppLink;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WhatsAppLinkTest extends TestCase
{
    private const HOJE = '2026-11-13';

    /** Linha de obrigação como vem do banco (com dados da empresa). */
    private static function obrigacao(array $alterar = []): array
    {
        return $alterar + [
            'razao_social'  => 'Padaria Pão Quente Ltda',
            'nome_fantasia' => 'Pão Quente',
            'whatsapp'      => '86999990001',
            'tipo'          => 'DAS',
            'competencia'   => '2026-10-01',
            'valor'         => '1234.56',
            'vencimento'    => '2026-11-20',
        ];
    }

    private static function hoje(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::HOJE);
    }

    // ------------------------------------------------------------------
    // Número
    // ------------------------------------------------------------------

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function numeros(): array
    {
        return [
            'celular com máscara'    => ['(86) 99999-0001', '5586999990001'],
            'já com +55'             => ['+55 86 99999-0001', '5586999990001'],
            'fixo (10 dígitos)'      => ['8632210000', '558632210000'],
            'sem DDD'                => ['99999-0001', null],
            'vazio'                  => ['', null],
            'nulo (empresa sem número)' => [null, null],
        ];
    }

    #[DataProvider('numeros')]
    public function testNormalizaNumeroComCodigoDoBrasil(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, WhatsAppLink::numero($entrada));
    }

    // ------------------------------------------------------------------
    // Mensagem
    // ------------------------------------------------------------------

    public function testMensagemTemTodosOsDadosDaGuia(): void
    {
        $mensagem = WhatsAppLink::mensagem(self::obrigacao(), self::hoje());

        $this->assertStringContainsString('Pão Quente', $mensagem);
        $this->assertStringContainsString('DAS', $mensagem);
        $this->assertStringContainsString('10/2026', $mensagem);
        $this->assertStringContainsString('R$ 1.234,56', $mensagem);
        $this->assertStringContainsString('20/11/2026', $mensagem);
        $this->assertStringContainsString('vence em 7 dias', $mensagem);
    }

    public function testUsaRazaoSocialQuandoNaoHaNomeFantasia(): void
    {
        $mensagem = WhatsAppLink::mensagem(self::obrigacao(['nome_fantasia' => null]), self::hoje());

        $this->assertStringContainsString('Padaria Pão Quente Ltda', $mensagem);
    }

    public function testMensagemDeGuiaVencidaAvisaHaQuantosDias(): void
    {
        $mensagem = WhatsAppLink::mensagem(self::obrigacao(['vencimento' => '2026-11-10']), self::hoje());

        $this->assertStringContainsString('venceu há 3 dias', $mensagem);
    }

    public function testDeclaracaoSemValorNaoMostraRZero(): void
    {
        $mensagem = WhatsAppLink::mensagem(self::obrigacao(['tipo' => 'DCTFWEB', 'valor' => '0.00']), self::hoje());

        $this->assertStringNotContainsString('R$ 0,00', $mensagem);
        $this->assertStringContainsString('sem valor a pagar', $mensagem);
    }

    public function testMostraONomeAmigavelDoTipo(): void
    {
        $mensagem = WhatsAppLink::mensagem(self::obrigacao(['tipo' => 'INSS']), self::hoje());

        $this->assertStringContainsString('INSS (GPS)', $mensagem);
    }

    // ------------------------------------------------------------------
    // Link
    // ------------------------------------------------------------------

    public function testLinkApontaParaOWaMeComONumero(): void
    {
        $link = WhatsAppLink::paraObrigacao(self::obrigacao(), self::hoje());

        $this->assertStringStartsWith('https://wa.me/5586999990001?text=', $link);
    }

    public function testTextoCodificadoVoltaIgualAMensagem(): void
    {
        $link = WhatsAppLink::paraObrigacao(self::obrigacao(), self::hoje());
        $texto = substr($link, strlen('https://wa.me/5586999990001?text='));

        $this->assertSame(WhatsAppLink::mensagem(self::obrigacao(), self::hoje()), rawurldecode($texto));
    }

    public function testCodificaEspacoAcentoEComercialEQuebraDeLinha(): void
    {
        $link = WhatsAppLink::paraObrigacao(
            self::obrigacao(['nome_fantasia' => 'Pão & Cia']),
            self::hoje()
        );

        $this->assertStringContainsString('%20', $link);       // espaço (não "+")
        $this->assertStringNotContainsString('+', $link);
        $this->assertStringContainsString('P%C3%A3o', $link);  // "ã" em UTF-8
        $this->assertStringContainsString('%26', $link);       // "&" não quebra a URL
        $this->assertStringContainsString('%0A', $link);       // quebra de linha
    }

    // ------------------------------------------------------------------
    // Resumo por empresa (ranking do painel)
    // ------------------------------------------------------------------

    /** @return array<string, mixed> empresa como vem do ranking */
    private static function empresa(array $alterar = []): array
    {
        return $alterar + ['razao_social' => 'Padaria Pão Quente Ltda', 'nome_fantasia' => 'Pão Quente', 'whatsapp' => '86999990001'];
    }

    public function testResumoTemEmpresaQuantidadeETotal(): void
    {
        $mensagem = WhatsAppLink::mensagemResumo(self::empresa(), 3, '9213.55');

        $this->assertStringContainsString('*Pão Quente*', $mensagem);
        $this->assertStringContainsString('3 guias vencidas', $mensagem);
        $this->assertStringContainsString('R$ 9.213,55', $mensagem);
    }

    public function testResumoNoSingularParaUmaGuia(): void
    {
        $this->assertStringContainsString('1 guia vencida,', WhatsAppLink::mensagemResumo(self::empresa(), 1, '387.40'));
    }

    public function testLinkDoResumoCodificaAMensagem(): void
    {
        $link = WhatsAppLink::paraResumo(self::empresa(), 2, '100.00');
        $prefixo = 'https://wa.me/5586999990001?text=';

        $this->assertStringStartsWith($prefixo, $link);
        $this->assertSame(WhatsAppLink::mensagemResumo(self::empresa(), 2, '100.00'), rawurldecode(substr($link, strlen($prefixo))));
    }

    public function testResumoSemNumeroNaoGeraLink(): void
    {
        $this->assertNull(WhatsAppLink::paraResumo(self::empresa(['whatsapp' => null]), 2, '100.00'));
    }

    public function testSemNumeroValidoNaoGeraLink(): void
    {
        $this->assertNull(WhatsAppLink::paraObrigacao(self::obrigacao(['whatsapp' => null]), self::hoje()));
        $this->assertNull(WhatsAppLink::paraObrigacao(self::obrigacao(['whatsapp' => '123']), self::hoje()));
    }
}
