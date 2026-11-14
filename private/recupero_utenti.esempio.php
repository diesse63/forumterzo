<?php
if (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }
// Copiare sul solo server come recupero_utenti.php e inserire un token casuale di 64 caratteri hex.
// Non inserire password del DB, del gestionale o del ponte Gmail.
return ['token' => 'INSERIRE_64_CARATTERI_ESADECIMALI_CASUALI_MINUSCOLI'];
