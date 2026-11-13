<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/includes/database.php';
try {
    $db = poimanager_db();
    $esito = $db->query('SELECT 1')->fetchColumn();
    if ((int) $esito !== 1) {
        throw new RuntimeException('Verifica non riuscita.');
    }
    echo 'Connessione al database riuscita.';
} catch (Throwable $errore) {
    http_response_code(503);
    echo 'Connessione non riuscita. Verificare configurazione, credenziali e disponibilità di PDO MySQL.';
}
