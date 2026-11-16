<?php

declare(strict_types=1);

/**
 * Prepara o ambiente de toda requisição: autoload, configuração, erros,
 * cabeçalhos de segurança e sessão.
 */

use App\Core\Config;
use App\Core\View;

require __DIR__ . '/autoload.php';
require __DIR__ . '/helpers.php';

$arquivoConfig = __DIR__ . '/../config/config.php';
if (!is_file($arquivoConfig)) {
    http_response_code(500);
    exit('Configuração ausente: copie config/config.example.php para config/config.php.');
}
Config::carregar(require $arquivoConfig);

date_default_timezone_set((string) Config::obter('app.timezone', 'America/Fortaleza'));

// --- Erros ---------------------------------------------------------------
// Em produção (debug = false) nada de detalhe técnico vai para a tela:
// caminho de arquivo e SQL ajudariam um atacante. O detalhe vai para o log.
$debug = (bool) Config::obter('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');

// Avisos (warning/notice) viram exceção: um bug não passa despercebido.
set_error_handler(static function (int $nivel, string $mensagem, string $arquivo, int $linha): bool {
    throw new ErrorException($mensagem, 0, $nivel, $arquivo, $linha);
});

set_exception_handler(static function (Throwable $erro) use ($debug): void {
    error_log((string) $erro);
    View::erro(500, $debug ? $erro->getMessage() . ' em ' . $erro->getFile() . ':' . $erro->getLine() : null);
});

// --- Cabeçalhos de segurança ---------------------------------------------
header('X-Content-Type-Options: nosniff');   // navegador não "adivinha" o tipo do arquivo
header('X-Frame-Options: DENY');             // impede abrir o sistema dentro de iframe (clickjacking)
header('Referrer-Policy: same-origin');

// --- Sessão --------------------------------------------------------------
session_name('KONTASESSID');
session_start([
    'cookie_httponly' => true,   // JavaScript não lê o cookie (reduz roubo via XSS)
    'cookie_samesite' => 'Lax',  // cookie não vai em POST vindo de outro site
    'cookie_secure'   => ($_SERVER['HTTPS'] ?? 'off') !== 'off',
    'use_strict_mode' => true,   // recusa ID de sessão inventado pelo cliente
]);
