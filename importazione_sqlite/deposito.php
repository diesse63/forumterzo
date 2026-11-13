<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/protezione_http.php';
function import_private_dir(string $path): string {
 $dir=realpath($path); $root=realpath($_SERVER['DOCUMENT_ROOT']??'');
 if(!$dir||!is_dir($dir)||!is_writable($dir)||!$root) throw new RuntimeException('Deposito assente, non scrivibile o radice web non verificabile.');
 if($dir!==$root&&!str_starts_with($dir,$root.DIRECTORY_SEPARATOR)) return $dir;
 if($dir!==realpath(__DIR__.'/data')||is_link(__DIR__.'/data')||trim((string)@file_get_contents($dir.'/.htaccess'))!=='Require all denied') throw new RuntimeException('Deposito web non protetto: ripristinare data/.htaccess.');
 protection_transport();
 $name='prova_'.bin2hex(random_bytes(16)).'.txt'; $file=$dir.'/'.$name;
 if(file_put_contents($file,'Verifica non riservata',LOCK_EX)===false) throw new RuntimeException('Verifica deposito impossibile.');
 try {
  protection_require_403('https://poimanager.altervista.org/importazione_sqlite/data/'.$name,'deposito data');
 } finally { @unlink($file); }
 return $dir;
}
