<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Semáforo de prazos: a cor de cada obrigação, calculada na hora.
 *
 * Por quê calcular e não gravar "vencida" no banco: a situação muda
 * sozinha à meia-noite. Se fosse gravada, seria preciso um agendador
 * (cron) atualizando todo dia, e no dia em que ele falhasse o sistema
 * mostraria informação errada.
 *
 * A regra existe em PHP (calcular) e em SQL (condicaoSql, para filtros e
 * painel). As duas ficam AQUI, lado a lado, usando a mesma constante.
 */
final class Semaforo
{
    /** Vence em até 7 dias (inclusive hoje) = alerta. Usado também pelo painel. */
    public const DIAS_ALERTA = 7;

    public const VENCIDA = 'vencida';
    public const ALERTA = 'alerta';
    public const EM_DIA = 'em_dia';
    public const PAGA = 'paga';

    /** Em ordem de urgência. classe = cor do Bootstrap. */
    private const SITUACOES = [
        self::VENCIDA => ['rotulo' => 'Vencida', 'classe' => 'danger'],
        self::ALERTA  => ['rotulo' => 'Vence em breve', 'classe' => 'warning'],
        self::EM_DIA  => ['rotulo' => 'Em dia', 'classe' => 'success'],
        self::PAGA    => ['rotulo' => 'Paga', 'classe' => 'secondary'],
    ];

    /**
     * @param string                 $status     'pendente' ou 'paga' (o que está gravado)
     * @param string                 $vencimento 'AAAA-MM-DD'
     * @param DateTimeImmutable|null $hoje       informado nos testes; null = data atual
     */
    public static function calcular(string $status, string $vencimento, ?DateTimeImmutable $hoje = null): string
    {
        if ($status === 'paga') {
            return self::PAGA;
        }

        $dias = self::diasAteVencer($vencimento, $hoje);

        return match (true) {
            $dias < 0                  => self::VENCIDA,
            $dias <= self::DIAS_ALERTA => self::ALERTA,
            default                    => self::EM_DIA,
        };
    }

    /** Negativo = já venceu; 0 = vence hoje. Ignora o horário. */
    public static function diasAteVencer(string $vencimento, ?DateTimeImmutable $hoje = null): int
    {
        $hoje = ($hoje ?? new DateTimeImmutable())->setTime(0, 0);
        $diferenca = $hoje->diff(new DateTimeImmutable($vencimento));

        return $diferenca->invert === 1 ? -$diferenca->days : $diferenca->days;
    }

    /** Texto amigável: "venceu há 3 dias", "vence hoje", "vence em 5 dias". */
    public static function descreverPrazo(string $vencimento, ?DateTimeImmutable $hoje = null): string
    {
        $dias = self::diasAteVencer($vencimento, $hoje);

        return match (true) {
            $dias < -1  => 'venceu há ' . abs($dias) . ' dias',
            $dias === -1 => 'venceu ontem',
            $dias === 0  => 'vence hoje',
            $dias === 1  => 'vence amanhã',
            default      => "vence em {$dias} dias",
        };
    }

    public static function classe(string $situacao): string
    {
        return self::SITUACOES[$situacao]['classe'] ?? 'light';
    }

    public static function rotulo(string $situacao): string
    {
        return self::SITUACOES[$situacao]['rotulo'] ?? $situacao;
    }

    /** @return array<string, string> situação => rótulo (para o filtro da listagem) */
    public static function opcoes(): array
    {
        return array_map(static fn (array $info): string => $info['rotulo'], self::SITUACOES);
    }

    /**
     * A mesma regra em SQL, para WHERE de filtros e contagens do painel.
     * Só monta SQL a partir de valores FIXOS (constantes); situação
     * desconhecida devolve null, então texto do usuário nunca entra no SQL.
     *
     * @param string $tabela apelido da tabela obrigacoes na consulta
     */
    public static function condicaoSql(string $situacao, string $tabela = 'o'): ?string
    {
        if (!preg_match('/^[a-z_]+$/', $tabela)) {
            throw new InvalidArgumentException('Apelido de tabela inválido.');
        }

        $pendente = "{$tabela}.status = 'pendente'";
        $limite = 'CURDATE() + INTERVAL ' . self::DIAS_ALERTA . ' DAY';

        return match ($situacao) {
            self::VENCIDA => "{$pendente} AND {$tabela}.vencimento < CURDATE()",
            self::ALERTA  => "{$pendente} AND {$tabela}.vencimento BETWEEN CURDATE() AND {$limite}",
            self::EM_DIA  => "{$pendente} AND {$tabela}.vencimento > {$limite}",
            self::PAGA    => "{$tabela}.status = 'paga'",
            default       => null,
        };
    }
}
