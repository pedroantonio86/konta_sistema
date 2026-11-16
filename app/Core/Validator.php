<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validação no SERVIDOR (a do navegador pode ser desligada pelo usuário).
 *
 * Duas partes:
 * 1. Funções estáticas puras (cnpjValido, normalizarValor...): fáceis de testar.
 * 2. Regras encadeadas para os controllers, com UMA mensagem por campo:
 *
 *    $dados = Validator::limpar($_POST, ['razao_social', 'cnpj']);
 *    $v = (new Validator($dados))->obrigatorio('cnpj')->cnpj('cnpj');
 *    if (!$v->valido()) { ...re-renderiza o form com $v->erros() e $dados... }
 *
 * Regras (exceto obrigatorio) ignoram campo vazio: assim um campo opcional
 * só é validado quando preenchido.
 */
final class Validator
{
    private const ANO_MINIMO = 2000;
    private const ANO_MAXIMO = 2099;
    private const VALOR_MAXIMO_DIGITOS_INTEIROS = 10; // DECIMAL(12,2)

    /** @var array<string, string> campo => primeira mensagem de erro */
    private array $erros = [];

    /** @param array<string, string> $dados normalmente vindo de Validator::limpar() */
    public function __construct(private readonly array $dados)
    {
    }

    // ==================================================================
    // Entrada
    // ==================================================================

    /**
     * Pega só os campos esperados, sem espaços nas pontas.
     * - Campo que não está na lista é descartado: o usuário não consegue
     *   "injetar" um campo extra como perfil=contador (mass assignment).
     * - Valor que não é texto (ex.: email[]=x vira array) vira ''.
     * - Bytes que não são UTF-8 válido viram '?': o banco (utf8mb4) recusaria
     *   o texto e o usuário veria erro 500.
     *
     * @param array<string, mixed> $entrada normalmente $_POST ou $_GET
     * @param list<string>         $campos
     * @return array<string, string>
     */
    public static function limpar(array $entrada, array $campos): array
    {
        $limpo = [];
        foreach ($campos as $campo) {
            $valor = $entrada[$campo] ?? '';
            $limpo[$campo] = is_string($valor) ? trim(mb_scrub($valor, 'UTF-8')) : '';
        }

        return $limpo;
    }

    // ==================================================================
    // Funções puras
    // ==================================================================

    /** ' 12.abc.345/01de-35 ' -> '12ABC34501DE35' */
    public static function normalizarCnpj(string $cnpj): string
    {
        return strtoupper((string) preg_replace('/[\s.\/-]/', '', $cnpj));
    }

    /**
     * CNPJ numérico ou alfanumérico (Receita Federal, a partir de jul/2026).
     * Formato: 12 caracteres [0-9A-Z] + 2 dígitos verificadores.
     * Cálculo do DV (módulo 11): cada caractere vale ord(c) - 48, então
     * '0'..'9' valem 0..9 e 'A'..'Z' valem 17..42. Para CNPJ só com números
     * o resultado é o mesmo do algoritmo tradicional.
     */
    public static function cnpjValido(string $cnpj): bool
    {
        $cnpj = self::normalizarCnpj($cnpj);

        if (!preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj)) {
            return false;
        }

        // 00000000000000, 11111111111111... passam na conta, mas não existem.
        if (preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        $base = substr($cnpj, 0, 12);
        $base .= self::digitoVerificadorCnpj($base);
        $base .= self::digitoVerificadorCnpj($base);

        return $base === $cnpj;
    }

    /** Calcula 1 dígito verificador para 12 (1º DV) ou 13 (2º DV) caracteres. */
    private static function digitoVerificadorCnpj(string $base): string
    {
        $soma = 0;
        $peso = 2;
        for ($i = strlen($base) - 1; $i >= 0; $i--) {
            $soma += (ord($base[$i]) - 48) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1; // pesos 2..9 da direita p/ esquerda
        }

        $resto = $soma % 11;

        return $resto < 2 ? '0' : (string) (11 - $resto);
    }

    public static function emailValido(string $email): bool
    {
        return strlen($email) <= 150 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Data no formato do <input type="date">: AAAA-MM-DD, e que exista no calendário. */
    public static function dataValida(string $data): bool
    {
        return self::confereFormato('Y-m-d', $data);
    }

    /** Competência no formato do <input type="month">: AAAA-MM. */
    public static function competenciaValida(string $competencia): bool
    {
        return self::confereFormato('Y-m', $competencia);
    }

    /**
     * Converte e reconverte: '2026-02-30' vira 02/03 internamente, e a
     * diferença entre entrada e saída revela que a data não existe.
     */
    private static function confereFormato(string $formato, string $valor): bool
    {
        $data = DateTimeImmutable::createFromFormat('!' . $formato, $valor);

        if ($data === false || $data->format($formato) !== $valor) {
            return false;
        }

        $ano = (int) $data->format('Y');

        return $ano >= self::ANO_MINIMO && $ano <= self::ANO_MAXIMO;
    }

    /**
     * Converte o valor digitado para o formato do banco ('1234.56').
     * Aceita '1.234,56', '1234,5', '1234.56', '1234', 'R$ 1.234,56'.
     * Retorna null se inválido ou negativo.
     * Por quê string e não float: float tem erro de arredondamento
     * (0.1 + 0.2 != 0.3), e dinheiro precisa ser exato.
     */
    public static function normalizarValor(string $entrada): ?string
    {
        $valor = str_replace([' ', "\u{00A0}"], '', str_ireplace('R$', '', $entrada));

        if (str_contains($valor, ',')) {
            // Brasileiro: ponto = milhar, vírgula = decimal
            if (!preg_match('/^(\d{1,3}(\.\d{3})*|\d+),\d{1,2}$/', $valor)) {
                return null;
            }
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $valor)) {
            $valor = str_replace('.', '', $valor); // '1.234' = mil duzentos e trinta e quatro
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $valor)) {
            return null;
        }

        [$inteiro, $centavos] = array_pad(explode('.', $valor, 2), 2, '');
        $inteiro = ltrim($inteiro, '0');
        $inteiro = $inteiro === '' ? '0' : $inteiro;

        if (strlen($inteiro) > self::VALOR_MAXIMO_DIGITOS_INTEIROS) {
            return null;
        }

        return $inteiro . '.' . str_pad($centavos, 2, '0');
    }

    /** '+55 (86) 99999-0001' -> '86999990001' (DDD + número, sem o 55). */
    public static function normalizarWhatsapp(string $telefone): string
    {
        $digitos = (string) preg_replace('/\D/', '', $telefone);

        if (str_starts_with($digitos, '55') && in_array(strlen($digitos), [12, 13], true)) {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }

    /** DDD + 8 dígitos (fixo) ou DDD + 9 dígitos (celular). */
    public static function whatsappValido(string $telefone): bool
    {
        return (bool) preg_match('/^\d{10,11}$/', self::normalizarWhatsapp($telefone));
    }

    // ==================================================================
    // Regras encadeadas
    // ==================================================================

    public function obrigatorio(string $campo): self
    {
        if ($this->lerCampo($campo) === '') {
            $this->falhar($campo, 'Campo obrigatório.');
        }

        return $this;
    }

    public function tamanho(string $campo, int $minimo, int $maximo): self
    {
        return $this->regra($campo, function (string $valor) use ($minimo, $maximo): ?string {
            $tamanho = mb_strlen($valor, 'UTF-8');

            return ($tamanho < $minimo || $tamanho > $maximo)
                ? ($minimo > 0 ? "Use entre {$minimo} e {$maximo} caracteres." : "Use no máximo {$maximo} caracteres.")
                : null;
        });
    }

    public function cnpj(string $campo): self
    {
        return $this->regra($campo, fn (string $v): ?string => self::cnpjValido($v) ? null : 'CNPJ inválido.');
    }

    public function email(string $campo): self
    {
        return $this->regra($campo, fn (string $v): ?string => self::emailValido($v) ? null : 'E-mail inválido.');
    }

    public function whatsapp(string $campo): self
    {
        return $this->regra(
            $campo,
            fn (string $v): ?string => self::whatsappValido($v) ? null : 'Informe DDD + número (10 ou 11 dígitos).'
        );
    }

    public function data(string $campo): self
    {
        return $this->regra($campo, fn (string $v): ?string => self::dataValida($v) ? null : 'Data inválida.');
    }

    public function competencia(string $campo): self
    {
        return $this->regra(
            $campo,
            fn (string $v): ?string => self::competenciaValida($v) ? null : 'Competência inválida (mês/ano).'
        );
    }

    public function valor(string $campo): self
    {
        return $this->regra(
            $campo,
            fn (string $v): ?string => self::normalizarValor($v) !== null ? null : 'Valor inválido (ex.: 1.234,56).'
        );
    }

    /** @param list<string> $opcoes */
    public function emLista(string $campo, array $opcoes): self
    {
        return $this->regra($campo, fn (string $v): ?string => in_array($v, $opcoes, true) ? null : 'Opção inválida.');
    }

    public function inteiroEntre(string $campo, int $minimo, int $maximo): self
    {
        return $this->regra($campo, function (string $valor) use ($minimo, $maximo): ?string {
            $numero = filter_var($valor, FILTER_VALIDATE_INT);

            return ($numero === false || $numero < $minimo || $numero > $maximo)
                ? "Escolha um número de {$minimo} a {$maximo}."
                : null;
        });
    }

    /** Para regras que dependem do banco (ex.: CNPJ já cadastrado), checadas no controller. */
    public function adicionarErro(string $campo, string $mensagem): self
    {
        $this->falhar($campo, $mensagem);

        return $this;
    }

    public function valido(): bool
    {
        return $this->erros === [];
    }

    /** @return array<string, string> */
    public function erros(): array
    {
        return $this->erros;
    }

    // ==================================================================
    // Internos
    // ==================================================================

    private function lerCampo(string $campo): string
    {
        return trim($this->dados[$campo] ?? '');
    }

    /**
     * Aplica a regra só se o campo estiver preenchido e ainda sem erro
     * (mostramos a primeira mensagem, que é a mais útil para o usuário).
     *
     * @param callable(string): ?string $verificar devolve a mensagem de erro ou null
     */
    private function regra(string $campo, callable $verificar): self
    {
        $valor = $this->lerCampo($campo);

        if ($valor !== '' && !isset($this->erros[$campo])) {
            $mensagem = $verificar($valor);
            if ($mensagem !== null) {
                $this->falhar($campo, $mensagem);
            }
        }

        return $this;
    }

    private function falhar(string $campo, string $mensagem): void
    {
        $this->erros[$campo] ??= $mensagem;
    }
}
