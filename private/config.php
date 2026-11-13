<?php
declare(strict_types=1);
if (!defined('POIMANAGER_INTERNAL')) {
    http_response_code(403);
    exit;
}
// Copiare in config.php, escluso da Git. Password DB vuota come indicato.
return [
    'dsn' => 'mysql:host=localhost;dbname=my_poimanager;charset=utf8mb4',
    'user' => 'poimanager',
    'password' => '6syjMAYwaQ8R',
    'admin_password' => 'Asdfghjkl.123456',
    // Deposito SQLite e log: directory esistente fuori dalla radice web.
    'private_dir' => dirname(__DIR__) . '/importazione_sqlite/data',
    'max_bytes' => 20 * 1024 * 1024,
];
