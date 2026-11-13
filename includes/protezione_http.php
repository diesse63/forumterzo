<?php
declare(strict_types=1);
function protection_status_code(string $line): int {
 return preg_match('~^HTTP/1[.][01] ([0-9]{3})(?:[ \\r\\n]|$)~',$line,$m)===1?(int)$m[1]:0;
}
function protection_transport(): string {
 if(function_exists('curl_init')&&function_exists('curl_exec'))return 'curl';
 if(function_exists('stream_socket_client')&&in_array('tls',stream_get_transports(),true))return 'tls';
 if(function_exists('fopen')&&filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)&&in_array('https',stream_get_wrappers(),true))return 'https';
 throw new RuntimeException('Verifica HTTPS non disponibile: abilitare cURL oppure OpenSSL con stream HTTPS/TLS.');
}
function protection_http_status(string $url): int {
 $p=parse_url($url);
 if(!$p||($p['scheme']??'')!=='https'||($p['host']??'')!=='poimanager.altervista.org'||isset($p['port'])||isset($p['user'])||isset($p['pass'])||isset($p['query'])||isset($p['fragment'])||!preg_match('~^/(?:private|importazione_sqlite/data)/prova_[a-f0-9]{32}[.]txt$~D',$p['path']??''))throw new RuntimeException('Indirizzo di verifica non consentito.');
 $transport=protection_transport();
 if($transport==='curl'){
  $c=curl_init($url);
  curl_setopt_array($c,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'']);
  try{$ok=curl_exec($c);return $ok===false?0:(int)curl_getinfo($c,CURLINFO_HTTP_CODE);}finally{curl_close($c);}
 }
 $ssl=['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>'poimanager.altervista.org','SNI_enabled'=>true];
 if($transport==='tls'){
  $context=stream_context_create(['ssl'=>$ssl]);
  $socket=@stream_socket_client('tls://poimanager.altervista.org:443',$errno,$errstr,5,STREAM_CLIENT_CONNECT,$context);
  if(!$socket)return 0;
  try {
   stream_set_timeout($socket,10);
   $request="HEAD ".$p['path']." HTTP/1.1\r\nHost: poimanager.altervista.org\r\nConnection: close\r\n\r\n";
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
 $context=stream_context_create(['ssl'=>$ssl,'http'=>['method'=>'HEAD','timeout'=>10,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0]]);
 $stream=@fopen($url,'rb',false,$context);
 if(!$stream)return 0;
 try{
  $meta=stream_get_meta_data($stream);$headers=$meta['wrapper_data']??[];
  return protection_status_code($headers[0]??'');
 }finally{fclose($stream);}
}
