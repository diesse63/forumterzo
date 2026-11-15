<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';require_access('gestione_email','lettura');
require_once __DIR__.'/includes/email.php';
header('Content-Type: application/json; charset=utf-8');
try{echo json_encode(['contacts'=>em_address_book(poimanager_db())],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);}
catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Rubrica non disponibile. Aggiornare prima di confermare i destinatari.']);}
