<?php
declare(strict_types=1);
function em_bridge_url(string $url,bool $redirect=false): void {
 $p=parse_url($url);$host=$redirect?'script.googleusercontent.com':'script.google.com';
 if(!$p||($p['scheme']??'')!=='https'||($p['host']??'')!==$host||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment']))throw new RuntimeException('URL ponte o redirect Google non consentito.');
 if(!$redirect&&(!preg_match('~^/macros/s/[A-Za-z0-9_-]+/exec$~D',$p['path']??'')||isset($p['query'])))throw new RuntimeException('Inserire URL Apps Script pubblico terminante con /exec.');
}
function em_http_once(string $url,string $method,string $body): array {
 em_bridge_url($url,$method==='GET');
 if(function_exists('curl_init')){
  $c=curl_init($url);$headers=[];
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>40,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept-Encoding: identity'],CURLOPT_HEADERFUNCTION=>static function($c,$line)use(&$headers){$headers[]=$line;return strlen($line);}]);
  if($method==='POST')curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
  try{$data=curl_exec($c);if($data===false)throw new RuntimeException('Connessione HTTPS a Google non riuscita. Verificare connessioni server-to-server.');$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);}
  finally{curl_close($c);}
 }elseif(function_exists('fopen')&&filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)){
  $ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false],'http'=>['method'=>$method,'header'=>"Content-Type: application/json\r\nAccept-Encoding: identity\r\n",'content'=>$method==='POST'?$body:'','timeout'=>40,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0]]);
  $stream=@fopen($url,'rb',false,$ctx);if(!$stream)throw new RuntimeException('Connessione HTTPS a Google non riuscita.');
  try{$data=stream_get_contents($stream,1048577);$headers=stream_get_meta_data($stream)['wrapper_data']??[];}finally{fclose($stream);}
  preg_match('~^HTTP/\\S+ (\\d{3})~',$headers[0]??'',$m);$code=(int)($m[1]??0);
 }else{
  if(!function_exists('stream_socket_client'))throw new RuntimeException('Occorre cURL oppure stream HTTPS/TLS.');
  $p=parse_url($url);$host=$p['host'];$ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false,'peer_name'=>$host,'SNI_enabled'=>true]]);
  $socket=@stream_socket_client('tls://'.$host.':443',$errno,$errstr,8,STREAM_CLIENT_CONNECT,$ctx);
  if(!$socket)throw new RuntimeException('Connessione TLS a Google non riuscita.');
  try{
   stream_set_timeout($socket,40);$path=$p['path'].(isset($p['query'])?'?'.$p['query']:'');
   $request=$method.' '.$path." HTTP/1.1\r\nHost: ".$host."\r\nConnection: close\r\nAccept-Encoding: identity\r\nContent-Type: application/json\r\nContent-Length: ".strlen($method==='POST'?$body:'')."\r\n\r\n".($method==='POST'?$body:'');
   $offset=0;while($offset<strlen($request)){$n=fwrite($socket,substr($request,$offset));if(!$n)throw new RuntimeException('Richiesta Google interrotta.');$offset+=$n;}
   $raw=stream_get_contents($socket,1048577);$meta=stream_get_meta_data($socket);
   if($meta['timed_out']||$raw===false)throw new RuntimeException('Risposta Google scaduta.');
  }finally{fclose($socket);}
  $parts=explode("\r\n\r\n",$raw,2);if(count($parts)!==2)throw new RuntimeException('Risposta HTTPS non valida.');
  $headers=explode("\r\n",$parts[0]);$data=$parts[1];preg_match('~^HTTP/\\S+ (\\d{3})~',$headers[0],$m);$code=(int)($m[1]??0);
  if(preg_match('/^Transfer-Encoding:\\s*chunked/im',$parts[0])){
   $decoded='';$pos=0;
   while(true){$e=strpos($data,"\r\n",$pos);if($e===false)throw new RuntimeException('Risposta chunked incompleta.');$hex=explode(';',substr($data,$pos,$e-$pos))[0];if(!ctype_xdigit($hex))throw new RuntimeException('Chunk non valido.');$len=hexdec($hex);$pos=$e+2;if($len===0)break;if($pos+$len+2>strlen($data))throw new RuntimeException('Chunk incompleto.');$decoded.=substr($data,$pos,$len);$pos+=$len+2;}$data=$decoded;
  }
 }
 if(strlen((string)$data)>1048576)throw new RuntimeException('Risposta Google troppo lunga.');
 $location='';foreach($headers as $header)if(stripos($header,'Location:')===0)$location=trim(substr($header,9));
 return ['code'=>$code,'location'=>$location,'body'=>(string)$data];
}
function em_bridge_call(array $config,string $secret,array $payload,?callable $http=null): array {
 em_bridge_url($config['URLPonte']);$http??='em_http_once';
 $nonce=bin2hex(random_bytes(16));$payload['nonce']=$nonce;$payload['timestamp']=time();$payload['sender']=$config['Mittente'];
 $encoded=base64_encode(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
 $body=json_encode(['data'=>$encoded,'signature'=>hash_hmac('sha256',$encoded,$secret)],JSON_THROW_ON_ERROR);
 $response=$http($config['URLPonte'],'POST',$body);
 for($i=0;in_array($response['code'],[301,302,303],true)&&$i<3;$i++){
  em_bridge_url($response['location'],true);
  // Dopo il POST non inoltrare credenziali o corpo ai redirect.
  $response=$http($response['location'],'GET','');
 }
 if($response['code']!==200)throw new RuntimeException('Il ponte Google non risponde correttamente. Verificare deployment pubblico, autorizzazioni e whitelist.');
 $envelope=json_decode(preg_replace('/^\\xEF\\xBB\\xBF/','',$response['body']),true);
 if(!is_array($envelope)){
  $plain=trim($response['body']);
  if(str_contains($plain,'Ponte non configurato'))throw new RuntimeException('Il progetto Google risponde, ma BRIDGE_SECRET è assente o non contiene esattamente 64 caratteri esadecimali minuscoli. Verificare Proprietà script nel progetto del deployment.');
  if(str_contains($plain,'Ponte Gestione email:'))throw new RuntimeException('Il ponte ha ricevuto una richiesta GET anziché il POST firmato. Verificare il trasporto e l’URL /exec.');
  if(preg_match('/<\\s*(?:!doctype|html|head|body)/i',$plain))throw new RuntimeException('Google ha restituito una pagina HTML, non la risposta del ponte. Verificare Esegui come Me, accesso Chiunque, autorizzaPonte e deployment aggiornato del progetto corretto.');
  throw new RuntimeException('Risposta Google non JSON: il deployment non sta eseguendo il ponte previsto. Verificare URL /exec e versione distribuita.');
 }
 if(!is_string($envelope['data']??null)||!is_string($envelope['signature']??null))throw new RuntimeException('Il deployment restituisce JSON con un formato diverso dal ponte Gestione email. Aggiornare Codice.gs e distribuire una Nuova versione.');
 if(!preg_match('/^[a-f0-9]{64}$/D',$envelope['signature'])||!hash_equals(hash_hmac('sha256',$envelope['data'],$secret),$envelope['signature']))throw new RuntimeException('Il ponte restituisce JSON ma la firma non coincide: la credenziale salvata su Altervista è diversa da BRIDGE_SECRET del progetto Google, oppure l’URL punta a un altro progetto. Confrontare le impronte della credenziale, senza divulgarne il valore.');

 $raw=base64_decode($envelope['data'],true);$result=$raw===false?null:json_decode($raw,true);
 if(!is_array($result)||($result['nonce']??'')!==$nonce)throw new RuntimeException('Risposta del ponte non corrispondente alla richiesta.');
 if(empty($result['ok']))throw new RuntimeException('Il ponte ha rifiutato la richiesta: '.substr((string)($result['error']??'errore'),0,180));
 if(strtolower((string)($result['sender']??''))!==strtolower($config['Mittente']))throw new RuntimeException('Account Google diverso dal mittente configurato.');
 return $result;
}
