<?php

declare(strict_types=1);

/**
 * Modelo de configuração do Konta.
 *
 * Copie este arquivo para config/config.php e ajuste os valores.
 * Por quê: o config.php real guarda a senha do banco, então fica fora do Git
 * (.gitignore). Aqui só ficam itens que mudam de ambiente (local x hospedagem);
 * regras de negócio (ex.: dias de alerta, tamanho máximo de upload) ficam no código.
 */
return [
    'app' => [
        'nome'     => 'Konta',
        // Caminho público até a pasta public/, com barra no final.
        // Local (XAMPP): '/konta_sistema/public/'
        'base_url' => '/konta_sistema/public/',
        // true só no computador de desenvolvimento (mostra erros detalhados).
        // Em produção SEMPRE false, para não expor caminhos e SQL.
        'debug'    => true,
        // Teresina (PI) = UTC-3, sem horário de verão. O mesmo fuso é aplicado
        // na conexão MySQL para date() e CURDATE() concordarem.
        'timezone' => 'America/Fortaleza',
    ],
    'db' => [
        'host'    => '127.0.0.1',
        'porta'   => 3306,
        'nome'    => 'konta',
        'usuario' => 'root',
        'senha'   => '',
        'charset' => 'utf8mb4',
    ],
];
