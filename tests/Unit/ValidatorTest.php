<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    // ------------------------------------------------------------------
    // CNPJ (numérico e alfanumérico)
    // ------------------------------------------------------------------

    /** @return array<string, array{0: string}> */
    public static function cnpjsValidos(): array
    {
        return [
            'numérico sem máscara'         => ['11222333000181'],
            'numérico com máscara'         => ['11.222.333/0001-81'],
            'alfanumérico (exemplo da RFB)' => ['12.ABC.345/01DE-35'],
            'alfanumérico minúsculo'       => ['12abc34501de35'],
            'com espaços nas pontas'       => ['  11222333000181 '],
        ];
    }

    #[DataProvider('cnpjsValidos')]
    public function testAceitaCnpjValido(string $cnpj): void
    {
        $this->assertTrue(Validator::cnpjValido($cnpj));
    }

    /** @return array<string, array{0: string}> */
    public static function cnpjsInvalidos(): array
    {
        return [
            'dígito verificador errado'      => ['11222333000182'],
            'DV alfanumérico errado'         => ['12ABC34501DE36'],
            'todos os dígitos iguais'        => ['11111111111111'],
            'só zeros'                       => ['00000000000000'],
            '13 caracteres'                  => ['1122233300018'],
            '15 caracteres'                  => ['112223330001811'],
            'letra no dígito verificador'    => ['12ABC34501DEAB'],
            'caractere especial'             => ['11222333000#81'],
            'vazio'                          => [''],
        ];
    }

    #[DataProvider('cnpjsInvalidos')]
    public function testRecusaCnpjInvalido(string $cnpj): void
    {
        $this->assertFalse(Validator::cnpjValido($cnpj));
    }

    public function testNormalizaCnpjRemovendoMascaraEMaiusculas(): void
    {
        $this->assertSame('12ABC34501DE35', Validator::normalizarCnpj(' 12.abc.345/01de-35 '));
    }

    // ------------------------------------------------------------------
    // E-mail
    // ------------------------------------------------------------------

    public function testAceitaEmailValido(): void
    {
        $this->assertTrue(Validator::emailValido('contato@paoquente.test'));
        $this->assertTrue(Validator::emailValido('ana.souza+konta@escritorio.com.br'));
    }

    /** @return array<string, array{0: string}> */
    public static function emailsInvalidos(): array
    {
        return [
            'sem arroba'   => ['contato.paoquente.test'],
            'com espaço'   => ['ana souza@x.com'],
            'dois arrobas' => ['a@@x.com'],
            'com tag html' => ['<script>@x.com'],
            'vazio'        => [''],
            'longo demais' => [str_repeat('a', 140) . '@exemplo.com'],
        ];
    }

    #[DataProvider('emailsInvalidos')]
    public function testRecusaEmailInvalido(string $email): void
    {
        $this->assertFalse(Validator::emailValido($email));
    }

    // ------------------------------------------------------------------
    // Datas e competência
    // ------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: bool}> */
    public static function datas(): array
    {
        return [
            'data comum'             => ['2026-11-20', true],
            '29/02 em ano bissexto'  => ['2028-02-29', true],
            '30 de fevereiro'        => ['2026-02-30', false],
            '29/02 em ano comum'     => ['2026-02-29', false],
            'mês 13'                 => ['2026-13-01', false],
            'formato brasileiro'     => ['20/11/2026', false],
            'sem zero à esquerda'    => ['2026-1-5', false],
            'ano fora do intervalo'  => ['1999-12-31', false],
            'texto'                  => ['amanhã', false],
            'vazio'                  => ['', false],
        ];
    }

    #[DataProvider('datas')]
    public function testValidaData(string $data, bool $esperado): void
    {
        $this->assertSame($esperado, Validator::dataValida($data));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function competencias(): array
    {
        return [
            'mês válido'        => ['2026-10', true],
            'janeiro'           => ['2027-01', true],
            'mês 13'            => ['2026-13', false],
            'mês 00'            => ['2026-00', false],
            'formato MM/AAAA'   => ['10/2026', false],
            'data completa'     => ['2026-10-01', false],
            'ano inválido'      => ['0202-10', false],
            'vazio'             => ['', false],
        ];
    }

    #[DataProvider('competencias')]
    public function testValidaCompetencia(string $competencia, bool $esperado): void
    {
        $this->assertSame($esperado, Validator::competenciaValida($competencia));
    }

    // ------------------------------------------------------------------
    // Valores em reais
    // ------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: ?string}> */
    public static function valores(): array
    {
        return [
            'formato brasileiro'       => ['1.234,56', '1234.56'],
            'milhões'                  => ['1.234.567,89', '1234567.89'],
            'vírgula sem milhar'       => ['1234,5', '1234.50'],
            'ponto decimal'            => ['1234.56', '1234.56'],
            'inteiro'                  => ['1234', '1234.00'],
            'milhar sem centavos'      => ['1.234', '1234.00'],
            'com R$ e espaços'         => [' R$ 1.234,56 ', '1234.56'],
            'zero'                     => ['0', '0.00'],
            'zero com vírgula'         => ['0,00', '0.00'],
            'centavos'                 => ['0,5', '0.50'],
            'negativo'                 => ['-5', null],
            'texto'                    => ['abc', null],
            'duas vírgulas'            => ['1,2,3', null],
            'três casas decimais'      => ['12,345', null],
            'milhar mal formado'       => ['1.23.4,00', null],
            'vazio'                    => ['', null],
            'acima de DECIMAL(12,2)'   => ['10.000.000.000,00', null],
            'limite de DECIMAL(12,2)'  => ['9.999.999.999,99', '9999999999.99'],
        ];
    }

    #[DataProvider('valores')]
    public function testNormalizaValor(string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Validator::normalizarValor($entrada));
    }

    // ------------------------------------------------------------------
    // WhatsApp
    // ------------------------------------------------------------------

    /** @return array<string, array{0: string, 1: string}> */
    public static function telefones(): array
    {
        return [
            'celular com máscara'     => ['(86) 99999-0001', '86999990001'],
            'com +55'                 => ['+55 86 99999-0001', '86999990001'],
            'fixo com 10 dígitos'     => ['86 3221-0000', '8632210000'],
            'só dígitos'              => ['86999990001', '86999990001'],
        ];
    }

    #[DataProvider('telefones')]
    public function testNormalizaWhatsapp(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, Validator::normalizarWhatsapp($entrada));
    }

    public function testValidaQuantidadeDeDigitosDoWhatsapp(): void
    {
        $this->assertTrue(Validator::whatsappValido('(86) 99999-0001'));
        $this->assertFalse(Validator::whatsappValido('99999-0001'));     // sem DDD
        $this->assertFalse(Validator::whatsappValido('869999900011'));   // 12 dígitos sem 55
    }

    // ------------------------------------------------------------------
    // Regras encadeadas (uso nos controllers)
    // ------------------------------------------------------------------

    public function testCampoObrigatorioVazioOuSoComEspacos(): void
    {
        $v = (new Validator(['nome' => '', 'razao' => '   ']))
            ->obrigatorio('nome')
            ->obrigatorio('razao');

        $this->assertFalse($v->valido());
        $this->assertSame(['nome' => 'Campo obrigatório.', 'razao' => 'Campo obrigatório.'], $v->erros());
    }

    public function testCamposOpcionaisVaziosNaoGeramErro(): void
    {
        $v = (new Validator(['email' => '', 'whatsapp' => '', 'observacao' => '']))
            ->email('email')
            ->whatsapp('whatsapp')
            ->tamanho('observacao', 0, 500);

        $this->assertTrue($v->valido());
        $this->assertSame([], $v->erros());
    }

    public function testGuardaApenasOPrimeiroErroDeCadaCampo(): void
    {
        $v = (new Validator(['cnpj' => '']))
            ->obrigatorio('cnpj')
            ->cnpj('cnpj');

        $this->assertSame(['cnpj' => 'Campo obrigatório.'], $v->erros());
    }

    public function testAcumulaErrosDeCamposDiferentes(): void
    {
        $v = (new Validator([
            'cnpj'       => '11222333000182',
            'email'      => 'sem-arroba',
            'whatsapp'   => '123',
            'vencimento' => '2026-02-30',
            'competencia' => '2026-13',
            'valor'      => '-1',
        ]))
            ->cnpj('cnpj')
            ->email('email')
            ->whatsapp('whatsapp')
            ->data('vencimento')
            ->competencia('competencia')
            ->valor('valor');

        $this->assertSame(
            ['cnpj', 'email', 'whatsapp', 'vencimento', 'competencia', 'valor'],
            array_keys($v->erros())
        );
    }

    public function testTamanhoContaCaracteresAcentuados(): void
    {
        // "Pão" tem 3 caracteres (mas 4 bytes em UTF-8)
        $v = (new Validator(['nome' => 'Pão', 'curto' => 'Al']))
            ->tamanho('nome', 3, 3)
            ->tamanho('curto', 3, 150);

        $this->assertSame(['curto' => 'Use entre 3 e 150 caracteres.'], $v->erros());
    }

    public function testValorPrecisaEstarNaLista(): void
    {
        $v = (new Validator(['regime' => 'SIMPLES', 'tipo' => 'BOLETO']))
            ->emLista('regime', ['MEI', 'SIMPLES'])
            ->emLista('tipo', ['DAS', 'DARF']);

        $this->assertSame(['tipo' => 'Opção inválida.'], $v->erros());
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function notas(): array
    {
        return [
            'mínimo'     => ['1', true],
            'máximo'     => ['5', true],
            'acima'      => ['6', false],
            'zero'       => ['0', false],
            'decimal'    => ['3.5', false],
            'texto'      => ['cinco', false],
        ];
    }

    #[DataProvider('notas')]
    public function testInteiroEntre(string $nota, bool $esperado): void
    {
        $v = (new Validator(['nota' => $nota]))->inteiroEntre('nota', 1, 5);

        $this->assertSame($esperado, $v->valido());
    }

    public function testErroAdicionadoPeloController(): void
    {
        // Ex.: CNPJ já cadastrado (só o banco sabe, então o controller avisa)
        $v = new Validator(['cnpj' => '11222333000181']);
        $v->adicionarErro('cnpj', 'CNPJ já cadastrado.');

        $this->assertFalse($v->valido());
        $this->assertSame('CNPJ já cadastrado.', $v->erros()['cnpj']);
    }

    public function testLimparPegaSoOsCamposEsperadosESemEspacos(): void
    {
        $entrada = [
            'nome'   => '  Padaria  ',
            'email'  => ['injetado'],      // ?email[]=x vira array: tratado como vazio
            'perfil' => 'contador',        // campo extra: ignorado (mass assignment)
        ];

        $this->assertSame(
            ['nome' => 'Padaria', 'email' => '', 'cnpj' => ''],
            Validator::limpar($entrada, ['nome', 'email', 'cnpj'])
        );
    }

    public function testLimparTrocaBytesQueNaoSaoUtf8(): void
    {
        // "Preço" em Latin-1 (ç = byte 0xE7): o MySQL utf8mb4 recusaria e daria erro 500.
        $limpo = Validator::limpar(['nome' => "Pre\xE7o"], ['nome'])['nome'];

        $this->assertTrue(mb_check_encoding($limpo, 'UTF-8'));
        $this->assertSame('Pre?o', $limpo);
    }
}
