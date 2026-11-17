/*
 * Konta: máscaras de digitação (CNPJ, WhatsApp e valor em R$).
 *
 * Só conforto para quem digita: o servidor continua validando tudo
 * (Validator) e aceita o valor com ou sem máscara. Se o JavaScript
 * estiver desligado, o formulário funciona igual.
 *
 * Uso: <input data-mascara="cnpj|telefone|valor">
 */
(function () {
    'use strict';

    /**
     * CNPJ (numérico ou alfanumérico): 12 posições de letras/dígitos
     * + 2 dígitos verificadores, no formato XX.XXX.XXX/XXXX-XX.
     */
    function formatarCnpj(texto) {
        var bruto = String(texto).toUpperCase().replace(/[^0-9A-Z]/g, '');
        var base = bruto.slice(0, 12);
        var dv = bruto.slice(12).replace(/\D/g, '').slice(0, 2); // DV só aceita dígitos
        var c = base + dv;
        var partes = [c.slice(0, 2), c.slice(2, 5), c.slice(5, 8), c.slice(8, 12), c.slice(12, 14)];
        var saida = partes[0];
        if (c.length > 2) { saida += '.' + partes[1]; }
        if (c.length > 5) { saida += '.' + partes[2]; }
        if (c.length > 8) { saida += '/' + partes[3]; }
        if (c.length > 12) { saida += '-' + partes[4]; }
        return saida;
    }

    /** (86) 3333-4444 (fixo, 10 dígitos) ou (86) 99999-9999 (celular, 11). */
    function formatarTelefone(texto) {
        var d = String(texto).replace(/\D/g, '');
        if (d.length > 11 && d.slice(0, 2) === '55') { d = d.slice(2); } // colou com +55
        d = d.slice(0, 11);
        if (d.length === 0) { return ''; }
        if (d.length <= 2) { return '(' + d; }
        var meio = d.length === 11 ? 5 : 4;
        var numero = d.slice(2);
        if (numero.length <= meio) { return '(' + d.slice(0, 2) + ') ' + numero; }
        return '(' + d.slice(0, 2) + ') ' + numero.slice(0, meio) + '-' + numero.slice(meio);
    }

    /** Separa milhares com ponto: '1234567' -> '1.234.567'. */
    function milhares(inteiro) {
        return inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /**
     * Digitação: os dígitos entram pela direita, como no caixa eletrônico
     * ('1' -> '0,01', '123456' -> '1.234,56'). Assim a vírgula nunca
     * precisa ser digitada e o apagar funciona naturalmente.
     */
    function formatarValorDigitado(texto, apagando) {
        var digitos = String(texto).replace(/\D/g, '');
        var d = digitos.replace(/^0+/, '').slice(0, 12); // DECIMAL(12,2)
        if (d.length === 0) {
            // Só zeros: declaração sem valor ('0,00'). Ao apagar, deixa o campo esvaziar.
            return (digitos.length > 0 && !apagando) ? '0,00' : '';
        }
        while (d.length < 3) { d = '0' + d; }
        return milhares(d.slice(0, -2)) + ',' + d.slice(-2);
    }

    /**
     * Colado de outro lugar ('1.234,56', '1234.5', 'R$ 99'): interpreta
     * como o servidor (Validator::normalizarValor) em vez de tratar tudo
     * como centavos, senão '1234.5' viraria '123,45'.
     */
    function formatarValorColado(texto) {
        var v = String(texto).replace(/R\$/i, '').replace(/\s/g, '');
        var inteiro, centavos;
        if (v.indexOf(',') !== -1) {
            var p = v.split(',');
            inteiro = p[0].replace(/\./g, '');
            centavos = p[1] || '';
        } else if (/^\d{1,3}(\.\d{3})+$/.test(v)) {
            inteiro = v.replace(/\./g, '');
            centavos = '';
        } else {
            var q = v.split('.');
            inteiro = q[0];
            centavos = q[1] || '';
        }
        inteiro = inteiro.replace(/\D/g, '');
        centavos = (centavos.replace(/\D/g, '') + '00').slice(0, 2);
        if (inteiro === '' && centavos === '00') { return formatarValorDigitado(texto); }
        return formatarValorDigitado((inteiro || '0') + centavos);
    }

    var formatadores = {
        cnpj: formatarCnpj,
        telefone: formatarTelefone,
        valor: formatarValorDigitado
    };

    function aplicar(evento) {
        var campo = evento.target;
        var tipo = campo.getAttribute && campo.getAttribute('data-mascara');
        if (!tipo || !formatadores[tipo]) { return; }

        var tipoEntrada = evento.inputType || '';
        var colado = tipoEntrada === 'insertFromPaste' || tipoEntrada === 'insertReplacementText';
        var apagando = tipoEntrada.indexOf('delete') === 0;
        var formatar = (tipo === 'valor' && colado) ? formatarValorColado : formatadores[tipo];
        var novo = formatar(campo.value, apagando);

        if (novo !== campo.value) {
            campo.value = novo;
            campo.setSelectionRange(novo.length, novo.length); // cursor no fim
        }
    }

    // Exporta as funções para os testes no Node; no navegador, liga a máscara.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            formatarCnpj: formatarCnpj,
            formatarTelefone: formatarTelefone,
            formatarValorDigitado: formatarValorDigitado,
            formatarValorColado: formatarValorColado
        };
    } else {
        // Um único ouvinte no documento vale para todos os campos com data-mascara.
        document.addEventListener('input', aplicar);
    }
})();
