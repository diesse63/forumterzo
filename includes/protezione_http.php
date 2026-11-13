<?php
declare(strict_types=1);
function protection_status_code(string $line): int {
 return preg_match('~^HTTP/(?:1[.][01]|2(?:[.]0)?|3(?:[.]0)?) ([0-9]{3})(?:[ \\r\\n]|$)~',$line,$m)===1?(int)$m[1]:0;
}
function protection_transports(): array {
 $available=[];
 if(function_exists('curl_init')&&function_exists('curl_exec'))$available[]='curl';
 if(function_exists('stream_socket_client')&&in_array('tls',stream_get_transports(),true))$available[]='tls';
 if(function_exists('fopen')&&filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)&&in_array('https',stream_get_wrappers(),true))$available[]='https';
 return $available;
}
function protection_transport(): string {
 $available=protection_transports();
 if(!$available)throw new RuntimeException('Verifica HTTPS non disponibile: abilitare cURL oppure OpenSSL con stream HTTPS/TLS.');
 return $available[0];
}
function protection_http_status(string $url): int {
 $attempts=[];return protection_probe($url,$attempts);
}
function protection_probe(string $url,array &$attempts): int {
 $attempts=[];$last=0;
 foreach(protection_transports() as $transport){
  $code=protection_http_status_using($url,$transport);
  $attempts[]=$transport.': '.($code===0?'nessuna risposta HTTPS valida':'HTTP '.$code);
  if($code===403)return 403;
  $last=$code;
 }
 if(!$attempts)protection_transport();
 return $last;
}
function protection_require_403(string $url,string $label): void {
 $attempts=[];
 if(protection_probe($url,$attempts)===403)return;
 throw new RuntimeException('Protezione '.$label.' non verificata ('.implode('; ',$attempts).'). Occorre HTTP 403. Se non arriva alcuna risposta, verificare le connessioni HTTPS in uscita nel pannello Altervista. Se arriva HTTP 200, verificare il file .htaccess. Nessuna chiave o dato è stato scritto.');
}
function protection_http_status_using(string $url,string $transport): int {
 $p=parse_url($url);
 if(!$p||($p['scheme']??'')!=='https'||($p['host']??'')!=='poimanager.altervista.org'||isset($p['port'])||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||!preg_match('~^/(?:private|importazione_sqlite/data)/prova_[a-f0-9]{32}[.]txt$~D',$p['path']??''))throw new RuntimeException('Indirizzo di verifica non consentito.');
 if($transport==='curl'){
  $c=curl_init($url);
  curl_setopt_array($c,[CURLOPT_HTTPGET=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk): int { return strlen($chunk); },CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'']);
  try{$ok=curl_exec($c);return $ok===false?0:(int)curl_getinfo($c,CURLINFO_HTTP_CODE);}finally{curl_close($c);}
 }
 $ssl=['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>'poimanager.altervista.org','SNI_enabled'=>true];
 if($transport==='tls'){
  $context=stream_context_create(['ssl'=>$ssl]);
  $socket=@stream_socket_client('tls://poimanager.altervista.org:443',$errno,$errstr,5,STREAM_CLIENT_CONNECT,$context);
  if(!$socket)return 0;
  try {
   stream_set_timeout($socket,10);
   $request="GET ".$p['path']." HTTP/1.1\r\nHost: poimanager.altervista.org\r\nConnection: close\r\n\r\n";
   $offset=0;
   while($offset<strlen($request)){
    $written=fwrite($socket,substr($request,$offset));
    if($written===false||$written===0)return 0;
    $offset+=$written;
   }
   $line=fgets($socket,4096);
   return $line===false?0:protection_status_code($line);
  }finally{fclose($socket);}
 }
 $context=stream_context_create(['ssl'=>$ssl,'http'=>['method'=>'GET','timeout'=>10,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0]]);
 $stream=@fopen($url,'rb',false,$context);
 if(!$stream)return 0;
 try{
  $meta=stream_get_meta_data($stream);$headers=$meta['wrapper_data']??[];
  return protection_status_code($headers[0]??'');
 }finally{fclose($stream);}
}
