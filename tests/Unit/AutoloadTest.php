<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Teste de fumaça: garante que o ambiente de testes sobe com o autoload
 * próprio do sistema (o mesmo usado em produção, sem vendor/).
 */
final class AutoloadTest extends TestCase
{
    public function testVersaoMinimaDoPhp(): void
    {
        $this->assertTrue(version_compare(PHP_VERSION, '8.2.0', '>='));
    }

    public function testAutoloadDoSistemaEstaRegistrado(): void
    {
        $this->assertTrue(function_exists('konta_autoload'));
        $this->assertContains('konta_autoload', spl_autoload_functions());
    }

    public function testClasseInexistenteNaoGeraErro(): void
    {
        $this->assertFalse(class_exists('App\\Core\\ClasseQueNaoExiste'));
    }

    public function testIgnoraClassesDeOutrosNamespaces(): void
    {
        $this->assertFalse(class_exists('OutroProjeto\\Qualquer'));
    }

    public function testFusoHorarioDeTeresina(): void
    {
        $this->assertSame('America/Fortaleza', date_default_timezone_get());
    }
}
