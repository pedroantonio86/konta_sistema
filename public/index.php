<?php

declare(strict_types=1);

/**
 * Front controller: ÚNICO ponto de entrada do Konta.
 * Toda requisição passa por aqui -> bootstrap -> Router -> Controller -> View.
 * Por quê: segurança (login, CSRF, erros) fica centralizada num só caminho.
 */

use App\Core\Router;

require __DIR__ . '/../app/bootstrap.php';

$router = new Router(require __DIR__ . '/../app/rotas.php');
$router->despachar($_SERVER['REQUEST_METHOD'], (string) ($_GET['url'] ?? ''));
