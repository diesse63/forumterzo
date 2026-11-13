<?php
declare(strict_types=1);
if (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }
return [
 'dsn' => 'mysql:host=localhost;dbname=my_poimanager;charset=utf8mb4',
 'user' => 'poimanager',
 'password' => '',
 'admin_password' => 'CAMBIARE_PASSWORD_IMPORTAZIONE',
 'private_dir' => dirname(__DIR__) . '/importazione_sqlite/data',
 'max_bytes' => 20 * 1024 * 1024,
];
