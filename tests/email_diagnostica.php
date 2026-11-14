<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/email_http.php';
$c=['URLPonte'=>'https://script.google.com/macros/s/DIAGNOSTICA/exec','Mittente'=>'sender@example.invalid'];$key=str_repeat('a',64);
foreach([
 ['Ponte non configurato.','BRIDGE_SECRET'],
 ['<!doctype html><html><body>Login</body></html>','pagina HTML'],
 ['{"success":true}','formato diverso'],
 ['Ponte Gestione email: richieste POST firmate richieste.','GET anziché'],
 [json_encode(['data'=>base64_encode('{}'),'signature'=>str_repeat('b',64)]),'firma non coincide']
] as [$body,$expected]){
 $error='';try{em_bridge_call($c,$key,['op'=>'ping'],fn($u,$m,$b)=>['code'=>200,'location'=>'','body'=>$body]);}catch(RuntimeException $e){$error=$e->getMessage();}
 if(!str_contains($error,$expected)||str_contains($error,$key))throw new RuntimeException('Diagnostica non precisa o segreto esposto.');
}
echo "OK: risposte HTML, ponte non configurato, JSON diverso e firma errata distinti senza esporre segreti.\n";
