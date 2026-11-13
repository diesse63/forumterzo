<?php
declare(strict_types=1);
require dirname(__DIR__).'/importazione_sqlite/deposito.php';
function deposit_check(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$root=sys_get_temp_dir().'/deposito_'.bin2hex(random_bytes(8));mkdir($root);mkdir($root.'/web');mkdir($root.'/web/importazione_sqlite');mkdir($root.'/outside');
$base=$root.'/web/importazione_sqlite';
try{
 [$dir,$http]=import_resolve_private_dir($base.'/data',$base,'');
 deposit_check(is_dir($dir)&&$http,'Cartella standard non creata o verifica HTTP non richiesta');
 deposit_check(file_get_contents($dir.'/.htaccess')==="Require all denied\n",'Protezione non creata');
 foreach(['importazione_sqlite/data','./importazione_sqlite/data','data'] as $path){
  [$found,$verify]=import_resolve_private_dir($path,$base,'');
  deposit_check($found===$dir&&$verify,'Percorso relativo non risolto');
 }
 [$outside,$verify]=import_resolve_private_dir($root.'/outside',$base,$root.'/web');
 deposit_check(!$verify&&$outside===realpath($root.'/outside'),'Deposito esterno non riconosciuto');
 foreach([[$root.'/missing',$root.'/web'],[$root.'/web',$root.'/web'],[$root.'/outside','']] as [$path,$web]){
  $rejected=false;try{import_resolve_private_dir($path,$base,$web);}catch(RuntimeException $e){$rejected=true;}deposit_check($rejected,'Deposito non valido accettato');
 }
 file_put_contents($dir.'/.htaccess','Require all granted');
 $rejected=false;try{import_resolve_private_dir($dir,$base,'');}catch(RuntimeException $e){$rejected=true;}
 deposit_check($rejected,'Protezione aperta accettata');
 deposit_check(file_get_contents($dir.'/.htaccess')==='Require all granted','Regole esistenti sovrascritte');
 echo "OK: creazione deposito protetto, percorsi relativi, radice web assente, deposito esterno e blocco regole non valide.\n";
}finally{
 @unlink($base.'/data/.htaccess');@rmdir($base.'/data');@rmdir($base);@rmdir($root.'/web');@rmdir($root.'/outside');@rmdir($root);
}
