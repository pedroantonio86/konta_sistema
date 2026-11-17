<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Gera CSV que abre direto no Excel em português.
 *
 * - Separador ";" : no Excel brasileiro a vírgula é o separador decimal,
 *   então ele espera ";" entre as colunas.
 * - BOM UTF-8 no início: sem ele o Excel lê como Windows-1252 e "Pão"
 *   vira "PÃ£o".
 * - Proteção contra CSV injection: uma célula que começa com = + - @
 *   (ou tabulação / retorno) seria executada como FÓRMULA pelo Excel
 *   (ex.: =HYPERLINK(...) numa observação digitada por alguém). Um
 *   apóstrofo na frente faz o Excel tratá-la como texto.
 */
final class Planilha
{
    private const BOM = "\xEF\xBB\xBF";
    private const INICIO_DE_FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /** Neutraliza um valor que o Excel interpretaria como fórmula. */
    public static function celula(string|int|float|null $valor): string
    {
        $texto = (string) $valor;

        if ($texto !== '' && in_array($texto[0], self::INICIO_DE_FORMULA, true)) {
            return "'" . $texto;
        }

        return $texto;
    }

    /**
     * Escreve o CSV completo (BOM + cabeçalho + linhas) num recurso aberto.
     *
     * @param resource                              $saida   ex.: fopen('php://output', 'w')
     * @param list<string>                          $cabecalho
     * @param iterable<list<string|int|float|null>> $linhas
     */
    public static function escrever($saida, array $cabecalho, iterable $linhas): void
    {
        fwrite($saida, self::BOM);
        self::linha($saida, $cabecalho);

        foreach ($linhas as $linha) {
            self::linha($saida, $linha);
        }
    }

    /**
     * @param resource                      $saida
     * @param list<string|int|float|null>   $valores
     */
    private static function linha($saida, array $valores): void
    {
        // escape '' = sem caractere de escape extra (aspas viram "" como manda o padrão CSV).
        fputcsv($saida, array_map([self::class, 'celula'], $valores), ';', '"', '', "\r\n");
    }
}
