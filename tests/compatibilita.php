<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/crypto.php';
require dirname(__DIR__).'/includes/protezione_http.php';
function check_compat(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$chosen=profile_choose_cipher();
if(!function_exists('sodium_crypto_secretbox'))check_compat($chosen==='aes-256-gcm','Fallback cifratura non selezionato');
if(!function_exists('curl_init'))check_compat(in_array(protection_transport(),['tls','https'],true),'Fallback HTTPS non selezionato');
$key=random_bytes(32);$profile=['nome'=>'user','email'=>'prova@example.invalid'];
foreach(['sodium','aes-256-gcm'] as $cipher){
 if(!profile_cipher_available($cipher))continue;
 $a=profile_encrypt($profile,$key,$cipher);$b=profile_encrypt($profile,$key,$cipher);
 check_compat($a!==$b,'Nonce riutilizzato');check_compat(profile_decrypt($a,$key)===$profile,'Decifratura fallita');
 $gcm=str_starts_with($a,'gcm1:');$raw=base64_decode($gcm?substr($a,5):$a);
 foreach([0,15,strlen($raw)-1] as $i){
  $bad=$raw;$bad[$i]=chr(ord($bad[$i])^1);$rejected=false;
  try{profile_decrypt(($gcm?'gcm1:':'').base64_encode($bad),$key);}catch(RuntimeException $e){$rejected=true;}
  check_compat($rejected,'Profilo manomesso accettato');
 }
 $rejected=false;try{profile_decrypt($a,random_bytes(32));}catch(RuntimeException $e){$rejected=true;}
 check_compat($rejected,'Chiave errata accettata');
}
check_compat(protection_status_code("HTTP/1.1 403 Forbidden\r\n")===403,'HTTP 403 non riconosciuto');
foreach(["HTTP/1.1 200 OK\r\n","HTTP/1.1 302 Found\r\n",'garbage'] as $line)check_compat(protection_status_code($line)!==403,'Risposta non protetta accettata');
foreach(['http://poimanager.altervista.org/private/prova_'.str_repeat('a',32).'.txt','https://example.com/private/prova_'.str_repeat('a',32).'.txt','https://poimanager.altervista.org/private/config.php'] as $url){
 $rejected=false;try{protection_http_status($url);}catch(RuntimeException $e){$rejected=true;}check_compat($rejected,'Origine o percorso non autorizzato');
}
echo 'OK: cifratura '.$chosen.', rilevamento HTTPS '.protection_transport().', integrità, chiave errata e risposte HTTP.'."\n";
