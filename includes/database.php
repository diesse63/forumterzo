<?php
declare(strict_types=1);
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(403);
    exit;
}
function poimanager_config(): array
{
    static $config = null;
    if ($config !== null) return $config;
    $path = dirname(__DIR__) . '/private/config.php';
    if (!is_file($path)) {
        throw new RuntimeException('Configurazione assente: creare private/config.php dal modello protetto.');
    }
    if (!defined('POIMANAGER_INTERNAL')) define('POIMANAGER_INTERNAL', true);
    $loaded = require $path;
    if (!is_array($loaded)) throw new RuntimeException('Configurazione database non valida.');
    foreach (['dsn', 'user', 'password', 'admin_password', 'private_dir'] as $key) {
        if (!isset($loaded[$key]) || !is_string($loaded[$key])) {
            throw new RuntimeException('Configurazione database incompleta.');
        }
    }
    if (($loaded['private_dir'] ?? '') === '/PERCORSO_NON_PUBBLICO/importazioni_private') {
        $loaded['private_dir'] = dirname(__DIR__) . '/importazione_sqlite/data';
    }
    return $config = $loaded;
}
function poimanager_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $cfg = poimanager_config();
    try {
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
        ]);
        return $pdo;
    } catch (PDOException $error) {
        error_log('POImanager: connessione database non disponibile.');
        throw new RuntimeException('Connessione al database non disponibile. Verificare la configurazione privata.');
    }
}
