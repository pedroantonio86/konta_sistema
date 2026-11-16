<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Csrf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CSRF: um site malicioso consegue fazer o navegador do usuário logado
 * enviar um POST para o Konta, mas não consegue LER o token da sessão.
 * Por isso todo POST sem o token correto é recusado.
 */
final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = []; // cada teste começa com uma "sessão" vazia
    }

    public function testTokenTem64CaracteresHexadecimais(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', Csrf::token());
    }

    public function testTokenEhOMesmoDuranteASessao(): void
    {
        $this->assertSame(Csrf::token(), Csrf::token());
    }

    public function testSessoesDiferentesTemTokensDiferentes(): void
    {
        $primeiro = Csrf::token();
        $_SESSION = [];

        $this->assertNotSame($primeiro, Csrf::token());
    }

    public function testAceitaTokenCorreto(): void
    {
        $this->assertTrue(Csrf::validar(Csrf::token()));
    }

    /** @return array<string, array{0: mixed}> */
    public static function tokensInvalidos(): array
    {
        return [
            'token errado'            => [str_repeat('a', 64)],
            'string vazia'            => [''],
            'ausente (null)'          => [null],
            'array (_csrf[]=x)'       => [['x']],
            'número'                  => [123],
        ];
    }

    #[DataProvider('tokensInvalidos')]
    public function testRecusaTokenInvalido(mixed $enviado): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::validar($enviado));
    }

    public function testRecusaQuandoASessaoNaoTemToken(): void
    {
        // Sem esta regra, hash_equals('', '') seria true e o POST passaria.
        $this->assertFalse(Csrf::validar(''));
        $this->assertFalse(Csrf::validar('qualquer'));
    }

    public function testCampoHiddenContemOToken(): void
    {
        $campo = Csrf::campo();

        $this->assertStringContainsString('type="hidden"', $campo);
        $this->assertStringContainsString('name="' . Csrf::CAMPO . '"', $campo);
        $this->assertStringContainsString('value="' . Csrf::token() . '"', $campo);
    }

    public function testRenovarTrocaOToken(): void
    {
        $antigo = Csrf::token();
        Csrf::renovar();

        $this->assertNotSame($antigo, Csrf::token());
        $this->assertFalse(Csrf::validar($antigo));
    }
}
