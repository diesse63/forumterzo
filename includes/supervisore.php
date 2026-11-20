<?php
declare(strict_types=1);

function supervisore_config(): array
{
    $file = dirname(__DIR__) . '/private/supervisore.php';
    if (!is_file($file)) {
        throw new RuntimeException('Funzione non configurata.');
    }
    $config = require $file;
    if (!is_array($config)
        || !is_string($config['password_hash'] ?? null)
        || !is_string($config['token_hash'] ?? null)
        || preg_match('/^[a-f0-9]{64}$/D', $config['token_hash']) !== 1) {
        throw new RuntimeException('Configurazione supervisore non valida.');
    }
    return $config;
}

function supervisore_https(): void
{
    if (empty($_SERVER['HTTPS']) || strtolower((string) $_SERVER['HTTPS']) === 'off') {
        http_response_code(403);
        exit('È richiesto HTTPS.');
    }
}

function supervisore_password(string $password, array $config): bool
{
    return strlen($password) >= 20 && strlen($password) <= 72
        && password_verify($password, $config['password_hash']);
}
