<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Semaforo;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * O "hoje" é sempre passado como parâmetro: o teste dá o mesmo resultado
 * em qualquer dia em que for executado.
 */
final class SemaforoTest extends TestCase
{
    private const HOJE = '2026-11-12';

    private static function hoje(string $data = self::HOJE): DateTimeImmutable
    {
        return new DateTimeImmutable($data);
    }

    public function testPrazoDeAlertaEhDeSeteDias(): void
    {
        $this->assertSame(7, Semaforo::DIAS_ALERTA);
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function casos(): array
    {
        return [
            'paga com vencimento passado' => ['paga', '2026-10-01', Semaforo::PAGA],
            'paga com vencimento futuro'  => ['paga', '2026-12-20', Semaforo::PAGA],
            'venceu há 30 dias'           => ['pendente', '2026-10-13', Semaforo::VENCIDA],
            'venceu ontem'                => ['pendente', '2026-11-11', Semaforo::VENCIDA],
            'vence hoje'                  => ['pendente', '2026-11-12', Semaforo::ALERTA],
            'vence amanhã'                => ['pendente', '2026-11-13', Semaforo::ALERTA],
            'vence em 7 dias (limite)'    => ['pendente', '2026-11-19', Semaforo::ALERTA],
            'vence em 8 dias'             => ['pendente', '2026-11-20', Semaforo::EM_DIA],
            'vence em 2 meses'            => ['pendente', '2027-01-12', Semaforo::EM_DIA],
        ];
    }

    #[DataProvider('casos')]
    public function testCalculaSituacao(string $status, string $vencimento, string $esperado): void
    {
        $this->assertSame($esperado, Semaforo::calcular($status, $vencimento, self::hoje()));
    }

    public function testHorarioDoDiaNaoInfluencia(): void
    {
        $quaseMeiaNoite = self::hoje('2026-11-12 23:59:59');

        $this->assertSame(Semaforo::ALERTA, Semaforo::calcular('pendente', '2026-11-12', $quaseMeiaNoite));
        $this->assertSame(Semaforo::VENCIDA, Semaforo::calcular('pendente', '2026-11-11', $quaseMeiaNoite));
    }

    public function testViradaDeAno(): void
    {
        $hoje = self::hoje('2026-12-28');

        $this->assertSame(Semaforo::ALERTA, Semaforo::calcular('pendente', '2027-01-04', $hoje));
        $this->assertSame(Semaforo::EM_DIA, Semaforo::calcular('pendente', '2027-01-05', $hoje));
    }

    public function testSemHojeInformadoUsaADataAtual(): void
    {
        $this->assertSame(Semaforo::ALERTA, Semaforo::calcular('pendente', date('Y-m-d')));
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function cores(): array
    {
        return [
            'vencida = vermelho' => [Semaforo::VENCIDA, 'danger', 'Vencida'],
            'alerta = amarelo'   => [Semaforo::ALERTA, 'warning', 'Vence em breve'],
            'em dia = verde'     => [Semaforo::EM_DIA, 'success', 'Em dia'],
            'paga = cinza'       => [Semaforo::PAGA, 'secondary', 'Paga'],
        ];
    }

    #[DataProvider('cores')]
    public function testCorERotuloDeCadaSituacao(string $situacao, string $classe, string $rotulo): void
    {
        $this->assertSame($classe, Semaforo::classe($situacao));
        $this->assertSame($rotulo, Semaforo::rotulo($situacao));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function prazos(): array
    {
        return [
            'venceu há 3 dias' => ['2026-11-09', 'venceu há 3 dias'],
            'venceu ontem'     => ['2026-11-11', 'venceu ontem'],
            'vence hoje'       => ['2026-11-12', 'vence hoje'],
            'vence amanhã'     => ['2026-11-13', 'vence amanhã'],
            'vence em 5 dias'  => ['2026-11-17', 'vence em 5 dias'],
        ];
    }

    #[DataProvider('prazos')]
    public function testDescreveOPrazo(string $vencimento, string $esperado): void
    {
        $this->assertSame($esperado, Semaforo::descreverPrazo($vencimento, self::hoje()));
    }

    public function testOpcoesDoFiltroNaOrdemDeUrgencia(): void
    {
        $this->assertSame(
            [Semaforo::VENCIDA, Semaforo::ALERTA, Semaforo::EM_DIA, Semaforo::PAGA],
            array_keys(Semaforo::opcoes())
        );
    }

    /**
     * A mesma regra em SQL (para filtros e painel): usa o MESMO prazo de
     * alerta da versão em PHP, então as duas nunca divergem.
     */
    public function testCondicaoSqlUsaAMesmaRegra(): void
    {
        $this->assertSame("o.status = 'pendente' AND o.vencimento < CURDATE()", Semaforo::condicaoSql(Semaforo::VENCIDA));
        $this->assertSame(
            "o.status = 'pendente' AND o.vencimento BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY",
            Semaforo::condicaoSql(Semaforo::ALERTA)
        );
        $this->assertSame("o.status = 'pendente' AND o.vencimento > CURDATE() + INTERVAL 7 DAY", Semaforo::condicaoSql(Semaforo::EM_DIA));
        $this->assertSame("o.status = 'paga'", Semaforo::condicaoSql(Semaforo::PAGA));
    }

    public function testCondicaoSqlComOutroApelidoDeTabela(): void
    {
        $this->assertSame("obrigacoes.status = 'paga'", Semaforo::condicaoSql(Semaforo::PAGA, 'obrigacoes'));
    }

    public function testCondicaoSqlRecusaSituacaoDesconhecida(): void
    {
        $this->assertNull(Semaforo::condicaoSql("vencida' OR 1=1 --"));
    }
}
