<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cálculos da paginação (sem banco, sem HTML: por isso é fácil de testar).
 * Uso: $p = new Paginacao($total, $_GET['pagina'] ?? 1);
 *      SQL: LIMIT $p->porPagina OFFSET $p->offset
 */
final class Paginacao
{
    public const POR_PAGINA = 10;
    private const VIZINHAS = 2; // links de páginas de cada lado da atual

    public readonly int $pagina;
    public readonly int $totalPaginas;
    public readonly int $offset;

    /** @param mixed $paginaPedida vem do $_GET: pode ser texto, array, null... */
    public function __construct(
        public readonly int $total,
        mixed $paginaPedida,
        public readonly int $porPagina = self::POR_PAGINA
    ) {
        $this->totalPaginas = max(1, (int) ceil($total / $porPagina));

        $pagina = filter_var($paginaPedida, FILTER_VALIDATE_INT);
        $pagina = ($pagina === false || $pagina < 1) ? 1 : $pagina;

        $this->pagina = min($pagina, $this->totalPaginas); // ?pagina=99 vira a última
        $this->offset = ($this->pagina - 1) * $porPagina;
    }

    public function temAnterior(): bool
    {
        return $this->pagina > 1;
    }

    public function temProxima(): bool
    {
        return $this->pagina < $this->totalPaginas;
    }

    /** Para o texto "Mostrando 11–20 de 25". */
    public function primeiroItem(): int
    {
        return $this->total === 0 ? 0 : $this->offset + 1;
    }

    public function ultimoItem(): int
    {
        return min($this->offset + $this->porPagina, $this->total);
    }

    /** @return list<int> páginas com link (a atual e até 2 de cada lado) */
    public function paginasVisiveis(): array
    {
        return range(
            max(1, $this->pagina - self::VIZINHAS),
            min($this->totalPaginas, $this->pagina + self::VIZINHAS)
        );
    }
}
