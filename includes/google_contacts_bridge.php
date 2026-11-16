<?php
declare(strict_types=1);
function gcs_config_file():string{return dirname(__DIR__).'/private/google_contacts_bridge.php';}
function gcs_config_load():?array{
 $file=gcs_config_file();if(!is_file($file))return null;
 if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
 $c=require $file;if(!is_array($c)||!is_string($c['url']??null)||!is_string($c['account']??null)||!is_string($c['secret']??null))throw new RuntimeException('Configurazione del ponte contatti non valida.');
 gcs_validate_url($c['url']);if(!filter_var($c['account'],FILTER_VALIDATE_EMAIL)||!preg_match('/^[a-f0-9]{64}$/D',$c['secret']))throw new RuntimeException('Account o credenziale del ponte contatti non validi.');
 return $c;
}
function gcs_validate_url(string $url,bool $redirect=false):void{
 $p=parse_url($url);$host=$redirect?'script.googleusercontent.com':'script.google.com';
 if(!$p||($p['scheme']??'')!=='https'||($p['host']??'')!==$host||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment']))throw new RuntimeException('URL del ponte Google non consentito.');
 if(!$redirect&&(!preg_match('~^/macros/s/[A-Za-z0-9_-]+/exec$~D',$p['path']??'')||isset($p['query'])))throw new RuntimeException('Inserire l’URL /exec del ponte Google autonomo.');
}
function gcs_http(string $url,string $method,string $body=''):array{
 $redirect=str_starts_with($url,'https://script.googleusercontent.com/');gcs_validate_url($url,$redirect);
 if(function_exists('curl_init')){
  $headers=[];$c=curl_init($url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>45,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept-Encoding: identity'],CURLOPT_HEADERFUNCTION=>static function($c,$line)use(&$headers){$headers[]=$line;return strlen($line);}]);
  if($method==='POST')curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
  try{$data=curl_exec($c);if($data===false)throw new RuntimeException('Connessione HTTPS al ponte contatti non riuscita.');$code=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);$location='';foreach($headers as $header)if(stripos($header,'Location:')===0)$location=trim(substr($header,9));}finally{curl_close($c);}
 }else{
  $ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false],'http'=>['method'=>$method,'header'=>"Content-Type: application/json\r\nAccept-Encoding: identity\r\n",'content'=>$method==='POST'?$body:'','timeout'=>45,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0]]);
  $data=@file_get_contents($url,false,$ctx);$headers=$http_response_header??[];if($data===false)throw new RuntimeException('Connessione HTTPS al ponte contatti non riuscita.');
  preg_match('~^HTTP/\\S+ (\\d{3})~',$headers[0]??'',$m);$code=(int)($m[1]??0);$location='';foreach($headers as $h)if(stripos($h,'Location:')===0)$location=trim(substr($h,9));
 }
 if(strlen((string)$data)>1048576)throw new RuntimeException('Risposta del ponte contatti troppo grande.');
 return ['code'=>$code,'location'=>$location,'body'=>(string)$data];
}
function gcs_call(array $config,array $payload):array{
 gcs_validate_url($config['url']);$nonce=bin2hex(random_bytes(16));$payload['nonce']=$nonce;$payload['timestamp']=time();$payload['account']=$config['account'];
 $encoded=base64_encode(json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));$body=json_encode(['data'=>$encoded,'signature'=>hash_hmac('sha256',$encoded,$config['secret'])],JSON_THROW_ON_ERROR);
 $r=gcs_http($config['url'],'POST',$body);
 for($i=0;in_array($r['code'],[301,302,303],true)&&$i<3;$i++){gcs_validate_url($r['location'],true);$r=gcs_http($r['location'],'GET');}
 if($r['code']!==200)throw new RuntimeException('Il ponte Google Contacts non risponde correttamente. Controllare URL e deployment.');
 $env=json_decode($r['body'],true);if(!is_array($env)||!is_string($env['data']??null)||!is_string($env['signature']??null)||!hash_equals(hash_hmac('sha256',$env['data'],$config['secret']),$env['signature']))throw new RuntimeException('Risposta del ponte Google Contacts non valida o firma non corrispondente.');
 $raw=base64_decode($env['data'],true);$out=$raw===false?null:json_decode($raw,true);
 if(!is_array($out)||($out['nonce']??'')!==$nonce)throw new RuntimeException('Risposta del ponte non corrispondente alla richiesta.');
 if(empty($out['ok']))throw new RuntimeException('Google Contacts: '.substr((string)($out['error']??'richiesta rifiutata'),0,220));
 if(strtolower((string)($out['account']??''))!==strtolower($config['account']))throw new RuntimeException('L’account Google del ponte non coincide con quello configurato.');
 return $out;
}
function gcs_people(array $config):array{
 $all=[];$token='';$pages=0;do{$payload=['op'=>'list','personFields'=>'names,emailAddresses,organizations,memberships','includeContactGroups'=>true];if($token!=='')$payload['pageToken']=$token;$r=gcs_call($config,$payload);foreach($r['connections']??[] as $p)if(is_array($p))$all[]=$p;$token=(string)($r['nextPageToken']??'');if(++$pages>100)throw new RuntimeException('Numero di pagine Google eccessivo: confronto interrotto senza scritture.');}while($token!=='');return $all;
}
function gcs_save_config(string $url,string $account,string $secret):void{
 gcs_validate_url($url);$account=strtolower(trim($account));if(!filter_var($account,FILTER_VALIDATE_EMAIL)||strlen($account)>254)throw new RuntimeException('Indirizzo account Google non valido.');
 if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new RuntimeException('Credenziale ponte non valida.');
 $dir=dirname(gcs_config_file());if(trim((string)@file_get_contents($dir.'/.htaccess'))!=='Require all denied')throw new RuntimeException('La cartella private non risulta protetta; ripristinare private/.htaccess.');
 $tmp=tempnam($dir,'.contacts-');if($tmp===false)throw new RuntimeException('Impossibile creare la configurazione privata.');
 $contents="<?php\nif (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }\nreturn ".var_export(['url'=>$url,'account'=>$account,'secret'=>$secret],true).";\n";
 try{if(file_put_contents($tmp,$contents,LOCK_EX)!==strlen($contents))throw new RuntimeException('Scrittura configurazione incompleta.');@chmod($tmp,0600);if(!rename($tmp,gcs_config_file()))throw new RuntimeException('Impossibile attivare la configurazione privata.');}finally{if(is_file($tmp))@unlink($tmp);}
}
