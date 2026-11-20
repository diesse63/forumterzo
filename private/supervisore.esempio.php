<?php
if (!defined('POIMANAGER_INTERNAL')) {
    http_response_code(403);
    exit;
}
return [
    'password_hash' => '',
    'versione_app' => '', // Commit o versione del sito distribuito.
    'cartelle_dati' => [], // Cartelle relative che contengono allegati da ripristinare.
    'token_hash' => '',
    'scadenza_utc' => '', // Formato ISO 8601, per esempio 2026-11-21T18:00:00Z.
];
