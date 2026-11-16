<?php

declare(strict_types=1);

/**
 * Autoload próprio (PSR-4 simplificado): App\Core\Router -> app/Core/Router.php
 *
 * Por quê não usar o autoload do Composer: o Composer é só dependência de
 * desenvolvimento (PHPUnit). O sistema precisa rodar na hospedagem sem a
 * pasta vendor/, então o carregamento de classes é nosso.
 */
function konta_autoload(string $classe): void
{
    $prefixo = 'App\\';

    if (!str_starts_with($classe, $prefixo)) {
        return; // classe de outro projeto/biblioteca: não é conosco
    }

    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', $relativo) . '.php';

    if (is_file($arquivo)) {
        require $arquivo;
    }
}

spl_autoload_register('konta_autoload');
