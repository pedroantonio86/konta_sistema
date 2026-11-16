<?php

declare(strict_types=1);

/**
 * Preparação dos testes: usa o MESMO autoload do sistema e o mesmo fuso,
 * para o teste refletir o comportamento real (ex.: cálculo do semáforo).
 */
require __DIR__ . '/../app/autoload.php';

date_default_timezone_set('America/Fortaleza');
