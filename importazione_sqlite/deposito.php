<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/protezione_http.php';
/** Risolve il deposito; crea solo la cartella standard data, mai percorsi arbitrari. */
function import_resolve_private_dir(string $path,string $base,string $documentRoot): array {
 $base=realpath($base);
 if(!$base)throw new RuntimeException('Cartella della procedura non verificabile.');
 if(in_array(str_replace('\\','/',trim($path)),['importazione_sqlite/data','./importazione_sqlite/data','data','./data'],true))$path=$base.'/data';
 if(trim($path)==='')throw new RuntimeException('private_dir vuoto: configurare la cartella importazione_sqlite/data in private/config.php.');
 $standard=$base.'/data';
 if(is_link($standard)&&($path===$standard||realpath($path)===realpath($standard)))throw new RuntimeException('Il deposito data non può essere un collegamento simbolico.');
 if($path===$standard&&!file_exists($standard)){
  if(!@mkdir($standard,0755)&&!is_dir($standard))throw new RuntimeException('Cartella importazione_sqlite/data assente e creazione automatica fallita. Crearla con WinSCP.');
 }
 $dir=realpath($path);
 if(!$dir)throw new RuntimeException('Percorso private_dir inesistente. In private/config.php usare: dirname(__DIR__) . "/importazione_sqlite/data".');
 if(!is_dir($dir))throw new RuntimeException('private_dir indica un file: occorre una cartella.');
 if(!is_writable($dir))throw new RuntimeException('La cartella deposito non è scrivibile da PHP. Verificare proprietario e permessi con WinSCP.');
 if($dir===realpath($standard)){
  // Ripristina soltanto la protezione mancante: non sovrascrive regole esistenti.
  $rules=$dir.'/.htaccess';
  if(!file_exists($rules)){
   $handle=@fopen($rules,'x');
   if($handle){try{if(fwrite($handle,"Require all denied\n")!==19)throw new RuntimeException('Scrittura protezione data incompleta.');}finally{fclose($handle);}}
  }
  if(is_link($rules)||trim((string)@file_get_contents($rules))!=='Require all denied')throw new RuntimeException('Protezione data non valida: caricare importazione_sqlite/data/.htaccess dal repository.');
  return [$dir,true];
 }
 $root=$documentRoot!==''?realpath($documentRoot):false;
 if(!$root)throw new RuntimeException('Radice web non verificabile per un deposito personalizzato. Usare la cartella standard importazione_sqlite/data.');
 if($dir===$root||str_starts_with($dir,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Deposito personalizzato dentro lo spazio web non consentito. Usare importazione_sqlite/data protetta.');
 return [$dir,false];
}
function import_private_dir(string $path): string {
 [$dir,$verifyHttp]=import_resolve_private_dir($path,__DIR__,(string)($_SERVER['DOCUMENT_ROOT']??''));
 if(!$verifyHttp)return $dir;
 protection_transport();
 $name='prova_'.bin2hex(random_bytes(16)).'.txt';$file=$dir.'/'.$name;
 if(@file_put_contents($file,'Verifica non riservata',LOCK_EX)===false)throw new RuntimeException('Impossibile scrivere nel deposito data. Verificare permessi e spazio disponibile.');
 try{protection_require_403('https://poimanager.altervista.org/importazione_sqlite/data/'.$name,'deposito data');}
 finally{@unlink($file);}
 return $dir;
}
