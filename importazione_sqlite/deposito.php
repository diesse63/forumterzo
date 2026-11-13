<?php
declare(strict_types=1);
function import_private_dir(string $path): string {
 $dir=realpath($path); $root=realpath($_SERVER['DOCUMENT_ROOT']??'');
 if(!$dir||!is_dir($dir)||!is_writable($dir)||!$root) throw new RuntimeException('Deposito assente, non scrivibile o radice web non verificabile.');
 if($dir!==$root&&!str_starts_with($dir,$root.DIRECTORY_SEPARATOR)) return $dir;
 if($dir!==realpath(__DIR__.'/data')||is_link(__DIR__.'/data')||trim((string)@file_get_contents($dir.'/.htaccess'))!=='Require all denied') throw new RuntimeException('Deposito web non protetto: ripristinare data/.htaccess.');
 if(!extension_loaded('curl')) throw new RuntimeException('Serve PHP cURL per verificare la protezione del deposito.');
 $name='prova_'.bin2hex(random_bytes(16)).'.txt'; $file=$dir.'/'.$name;
 if(file_put_contents($file,'Verifica non riservata',LOCK_EX)===false) throw new RuntimeException('Verifica deposito impossibile.');
 try {
  $ch=curl_init('https://poimanager.altervista.org/importazione_sqlite/data/'.$name);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'']);
  $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($body===false||$code!==403) throw new RuntimeException('Deposito non verificato: richiesta risposta HTTP 403. Nessun file acquisito.');
 } finally { @unlink($file); }
 return $dir;
}
