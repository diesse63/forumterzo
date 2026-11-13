<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_access('associazioni','scrittura');
require_once __DIR__.'/includes/anagrafica.php';
header('Content-Type: application/json; charset=utf-8');
try{
 if(!is_string($_GET['q']??''))throw new RuntimeException('Ricerca non valida.');
 echo json_encode(enti_runts_search(poimanager_db(),$_GET['q']??''),JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){http_response_code(503);echo json_encode(['error'=>'Ricerca RUNTS temporaneamente non disponibile.']);}
