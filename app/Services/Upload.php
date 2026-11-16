<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use RuntimeException;

/**
 * Upload seguro de comprovantes e documentos.
 *
 * Regras (e o porquê de cada uma):
 * - Só PDF, JPG e PNG, conferidos pelo CONTEÚDO do arquivo (finfo), e não
 *   pela extensão nem pelo tipo informado pelo navegador: os dois podem ser
 *   falsificados (ex.: virus.exe renomeado para comprovante.pdf).
 * - A extensão salva vem do tipo real, nunca do nome enviado.
 * - Nome aleatório (32 caracteres hexadecimais): ninguém adivinha o nome de
 *   um arquivo alheio e nomes iguais não se sobrescrevem.
 * - Gravado em storage/uploads, FORA de public/ e bloqueado por .htaccess:
 *   só sai pela rota documentos/baixar, que confere login e empresa.
 */
final class Upload
{
    public const TAMANHO_MAXIMO = 5 * 1024 * 1024; // 5 MB

    /** Tipo real (MIME) => extensão gravada. */
    public const TIPOS = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
    ];

    private const PASTA = __DIR__ . '/../../storage/uploads/';

    /**
     * @param mixed $arquivo item de $_FILES (ou null se nada foi enviado)
     * @return string|null mensagem de erro, ou null se o arquivo é aceito
     */
    public static function validar(mixed $arquivo): ?string
    {
        // error como array = vários arquivos num campo só (name="arquivo[]"): não aceitamos.
        if (!is_array($arquivo) || !isset($arquivo['error']) || !is_int($arquivo['error'])) {
            return 'Selecione um arquivo.';
        }

        $erroPhp = match ($arquivo['error']) {
            UPLOAD_ERR_OK                           => null,
            UPLOAD_ERR_NO_FILE                      => 'Selecione um arquivo.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo passa de 5 MB.',
            default                                 => 'Falha no envio. Tente novamente.',
        };
        if ($erroPhp !== null) {
            return $erroPhp;
        }

        if ($arquivo['size'] <= 0) {
            return 'O arquivo está vazio.';
        }
        if ($arquivo['size'] > self::TAMANHO_MAXIMO) {
            return 'O arquivo passa de 5 MB.';
        }
        // Garante que o arquivo veio mesmo de um upload HTTP (e não de um caminho forjado).
        if (!is_uploaded_file($arquivo['tmp_name'])) {
            return 'Falha no envio. Tente novamente.';
        }
        if (!isset(self::TIPOS[self::tipoReal($arquivo['tmp_name'])])) {
            return 'Envie apenas PDF, JPG ou PNG.';
        }

        return null;
    }

    /**
     * Move o arquivo (já validado) para storage/uploads com nome aleatório.
     *
     * @param array{name: string, tmp_name: string, size: int} $arquivo
     * @return array{arquivo: string, mime: string, tamanho: int, nome_original: string}
     */
    public static function salvar(array $arquivo): array
    {
        $mime = self::tipoReal($arquivo['tmp_name']);
        $nome = bin2hex(random_bytes(16)) . '.' . self::TIPOS[$mime];

        if (!move_uploaded_file($arquivo['tmp_name'], self::PASTA . $nome)) {
            throw new RuntimeException('Não foi possível gravar o arquivo enviado.');
        }

        return [
            'arquivo'       => $nome,
            'mime'          => $mime,
            'tamanho'       => (int) $arquivo['size'],
            'nome_original' => self::limparNomeOriginal($arquivo['name']),
        ];
    }

    /**
     * Caminho completo de um arquivo salvo. O nome é conferido contra o
     * formato que nós geramos: impede "../../config/config.php" (path traversal).
     */
    public static function caminho(string $arquivo): ?string
    {
        if (!preg_match('/^[0-9a-f]{32}\.(pdf|jpg|png)$/', $arquivo)) {
            return null;
        }

        return is_file(self::PASTA . $arquivo) ? self::PASTA . $arquivo : null;
    }

    public static function apagar(string $arquivo): void
    {
        $caminho = self::caminho($arquivo);

        if ($caminho !== null) {
            unlink($caminho);
        }
    }

    /** Tipo pelo conteúdo ("assinatura" nos primeiros bytes), não pelo nome. */
    private static function tipoReal(string $caminho): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($caminho);
    }

    /** Guardado só para exibir/baixar com o nome que o usuário reconhece. */
    private static function limparNomeOriginal(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', mb_scrub($nome, 'UTF-8')));
        $nome = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $nome);

        return mb_substr($nome !== '' ? $nome : 'arquivo', 0, 255);
    }
}
