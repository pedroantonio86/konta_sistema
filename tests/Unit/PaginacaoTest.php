<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Paginacao;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginacaoTest extends TestCase
{
    public function testListaVaziaTemUmaPaginaSemNavegacao(): void
    {
        $p = new Paginacao(0, 1);

        $this->assertSame(1, $p->pagina);
        $this->assertSame(1, $p->totalPaginas);
        $this->assertSame(0, $p->offset);
        $this->assertFalse($p->temAnterior());
        $this->assertFalse($p->temProxima());
        $this->assertSame(0, $p->primeiroItem());
        $this->assertSame(0, $p->ultimoItem());
    }

    public function testPrimeiraPagina(): void
    {
        $p = new Paginacao(25, 1);

        $this->assertSame(3, $p->totalPaginas);
        $this->assertSame(0, $p->offset);
        $this->assertSame(10, $p->porPagina);
        $this->assertFalse($p->temAnterior());
        $this->assertTrue($p->temProxima());
        $this->assertSame(1, $p->primeiroItem());
        $this->assertSame(10, $p->ultimoItem());
    }

    public function testUltimaPaginaIncompleta(): void
    {
        $p = new Paginacao(25, 3);

        $this->assertSame(20, $p->offset);
        $this->assertTrue($p->temAnterior());
        $this->assertFalse($p->temProxima());
        $this->assertSame(21, $p->primeiroItem());
        $this->assertSame(25, $p->ultimoItem());
    }

    public function testTotalMultiploExatoNaoCriaPaginaVazia(): void
    {
        $this->assertSame(2, (new Paginacao(20, 1))->totalPaginas);
    }

    public function testQuantidadePorPaginaConfiguravel(): void
    {
        $p = new Paginacao(25, 2, 5);

        $this->assertSame(5, $p->totalPaginas);
        $this->assertSame(5, $p->offset);
    }

    /** @return array<string, array{0: mixed, 1: int}> */
    public static function paginasPedidas(): array
    {
        return [
            'texto numérico (vem do $_GET)' => ['2', 2],
            'acima do total vira a última'  => [99, 3],
            'zero vira 1'                   => [0, 1],
            'negativo vira 1'               => ['-5', 1],
            'texto vira 1'                  => ['abc', 1],
            'decimal vira 1'                => ['2.5', 1],
            'ausente (null) vira 1'         => [null, 1],
            'array (?pagina[]=2) vira 1'    => [['2'], 1],
        ];
    }

    #[DataProvider('paginasPedidas')]
    public function testCorrigePaginaPedida(mixed $pedida, int $esperada): void
    {
        $this->assertSame($esperada, (new Paginacao(25, $pedida))->pagina);
    }

    /** @return array<string, array{0: int, 1: list<int>}> */
    public static function janelas(): array
    {
        return [
            'meio'    => [5, [3, 4, 5, 6, 7]],
            'início'  => [1, [1, 2, 3]],
            'fim'     => [10, [8, 9, 10]],
        ];
    }

    /** @param list<int> $esperadas */
    #[DataProvider('janelas')]
    public function testLinksMostramDuasPaginasDeCadaLado(int $atual, array $esperadas): void
    {
        $this->assertSame($esperadas, (new Paginacao(100, $atual))->paginasVisiveis());
    }
}
