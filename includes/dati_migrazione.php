<?php
declare(strict_types=1);
require_once __DIR__.'/dati.php';
function dc_prepare_table(PDO $p,string $table):void {
 $spec=dc_spec()[$table];$cols=dc_columns($p,$table);
 if(array_diff($spec['fields'],array_keys($cols)))throw new RuntimeException('Struttura non compatibile con la cifratura: '.$table);
 if($spec['identity']==='_CryptoID'&&!isset($cols['_CryptoID']))$p->exec('ALTER TABLE '.dc_q($table).' ADD COLUMN _CryptoID BIGINT NOT NULL AUTO_INCREMENT UNIQUE');
 if(!isset($cols['_Cifrato']))$p->exec('ALTER TABLE '.dc_q($table).' ADD COLUMN _Cifrato LONGTEXT NULL');
 dc_columns($p,$table,true);
}
function dc_begin(PDO $p):void {
 if($p->inTransaction())throw new RuntimeException('Terminare le transazioni prima della migrazione.');
 $lock='sqlite_import_'.substr(hash('sha256',(string)$p->query('SELECT DATABASE()')->fetchColumn()),0,32);
 $q=$p->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Importazione in corso: riprovare dopo il termine.');
 try{
  $state=dc_state($p);if($state&&$state['Stato']==='pronto')throw new RuntimeException('Cifratura già attiva.');
  if(dc_exists($p,'email_invii')&&(int)$p->query("SELECT COUNT(*) FROM email_invii WHERE Stato='in_corso' AND IniziatoIl>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)")->fetchColumn()>0)throw new RuntimeException('Invii email in corso: attendere il termine prima della migrazione.');
  $available=[];
  foreach(dc_spec() as $table=>$spec){
   if(!dc_exists($p,$table)){if(empty($spec['optional']))throw new RuntimeException('Tabella richiesta assente: '.$table);continue;}
   if(array_diff($spec['fields'],array_keys(dc_columns($p,$table))))throw new RuntimeException('Colonne diverse dal contratto di cifratura: '.$table);
   $available[]=$table;
  }
  $path=dirname(__DIR__).'/private/dati_security.php';
  if(!is_file($path)){
   if($state)throw new RuntimeException('Chiave originale mancante: ripristinarla dal backup, non generarne una nuova.');
   if(trim((string)@file_get_contents(dirname(__DIR__).'/private/.htaccess'))!=='Require all denied')throw new RuntimeException('Ripristinare private/.htaccess.');
   $keys=['key'=>base64_encode(random_bytes(32)),'index_key'=>base64_encode(random_bytes(32)),'cipher'=>profile_choose_cipher(),'version'=>1];
   $file=@fopen($path,'x');if(!$file)throw new RuntimeException('private non scrivibile: chiave non creata.');
   $text="<?php\nif (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }\nreturn ".var_export($keys,true).";\n";
   try{if(fwrite($file,$text)!==strlen($text))throw new RuntimeException('Scrittura chiave incompleta.');}finally{fclose($file);}
   @chmod($path,0600);
  }
  $keys=dc_keys();$hash=hash('sha256',$keys['key'].'|'.$keys['index_key']);
  if($state&&!hash_equals($state['ChiaveHash'],$hash))throw new RuntimeException('Chiave non corrispondente alla migrazione.');
  $p->exec("CREATE TABLE IF NOT EXISTS crypto_migrazione (ID INT PRIMARY KEY,Stato VARCHAR(20) NOT NULL,ChiaveHash CHAR(64) NOT NULL,Versione VARCHAR(20) NOT NULL,Inizio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,Fine DATETIME NULL) ENGINE=InnoDB");
  $p->exec("CREATE TABLE IF NOT EXISTS crypto_tabelle (Nome VARCHAR(64) PRIMARY KEY,Stato VARCHAR(20) NOT NULL,Righe BIGINT NOT NULL,Convertite BIGINT NOT NULL DEFAULT 0) ENGINE=InnoDB");
  if(!$state){$q=$p->prepare("INSERT INTO crypto_migrazione (ID,Stato,ChiaveHash,Versione) VALUES (1,'in_corso',?,'1.0.0')");$q->execute([$hash]);}
  foreach($available as $table){
   dc_prepare_table($p,$table);
   $q=$p->prepare("INSERT IGNORE INTO crypto_tabelle (Nome,Stato,Righe) VALUES (?,'in_corso',?)");$q->execute([$table,(int)$p->query('SELECT COUNT(*) FROM '.dc_q($table))->fetchColumn()]);
  }
 }finally{$q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
function dc_migration_step(PDO $p,int $limit=100):array {
 $state=dc_state($p);if(!$state||$state['Stato']!=='in_corso')throw new RuntimeException('Avviare o riprendere la preparazione della migrazione.');
 $keys=dc_keys();if(!hash_equals($state['ChiaveHash'],hash('sha256',$keys['key'].'|'.$keys['index_key'])))throw new RuntimeException('Chiave errata.');
 $lock=$p->query("SELECT GET_LOCK('dc_migration_step',0)");if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Altro lotto di cifratura in corso.');
 try{
  foreach(dc_spec() as $table=>$spec)if(dc_exists($p,$table)&&!isset(dc_columns($p,$table)['_Cifrato']))throw new RuntimeException('Preparazione incompleta: premere Riprendi preparazione.');
  $work=$p->query("SELECT * FROM crypto_tabelle WHERE Stato<>'pronto' ORDER BY Nome LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if(!$work){
   $p->exec("UPDATE crypto_migrazione SET Stato='pronto',Fine=UTC_TIMESTAMP() WHERE ID=1");return ['done'=>true,'table'=>'','converted'=>0];
  }
  $table=$work['Nome'];$spec=dc_spec()[$table];$pk=$spec['identity'];
  $p->beginTransaction();
  try{
   $rows=$p->query('SELECT * FROM '.dc_q($table).' WHERE _Cifrato IS NULL LIMIT '.max(1,min(500,$limit)).' FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
   foreach($rows as $row){
    $masked=dc_mask($p,$table,$row);$values=[];foreach($spec['fields'] as $field)$values[$field]=$masked[$field];$values['_Cifrato']=dc_seal($table,$row);
    // Decifrare il payload prima della scrittura verifica integrità e identità.
    $recovered=dc_open($table,array_merge($masked,['_Cifrato'=>$values['_Cifrato']]));
    foreach($spec['fields'] as $field)if(($row[$field]===null)!==($recovered[$field]===null)||($row[$field]!==null&&(string)$row[$field]!==$recovered[$field]))throw new RuntimeException('Confronto di migrazione fallito: '.$table);
    $q=$p->prepare('UPDATE '.dc_q($table).' SET '.implode(',',array_map(fn($field)=>dc_q($field).'=?',array_keys($values))).' WHERE '.dc_q($pk).'=? AND _Cifrato IS NULL');$q->execute(array_merge(array_values($values),[$row[$pk]]));
   }
   $q=$p->prepare('UPDATE crypto_tabelle SET Convertite=Convertite+? WHERE Nome=?');$q->execute([count($rows),$table]);$p->commit();
  }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
  if((int)$p->query('SELECT COUNT(*) FROM '.dc_q($table).' WHERE _Cifrato IS NULL')->fetchColumn()===0){
   if((int)$p->query('SELECT COUNT(*) FROM '.dc_q($table))->fetchColumn()!==(int)$work['Righe'])throw new RuntimeException('Conteggio cambiato durante la migrazione: '.$table);
   $q=$p->prepare("UPDATE crypto_tabelle SET Stato='pronto' WHERE Nome=?");$q->execute([$table]);
  }
  return ['done'=>false,'table'=>$table,'converted'=>count($rows)];
 }finally{$p->query("SELECT RELEASE_LOCK('dc_migration_step')");}
}
function dc_verify_table(PDO $p,string $table):int {
 $active=dc_active($p);if(!$active)throw new RuntimeException('Cifratura non ancora attiva.');
 $count=0;$spec=dc_spec()[$table];
 $q=$p->query('SELECT * FROM '.dc_q($table));
 while($row=$q->fetch(PDO::FETCH_ASSOC)){
  $plain=dc_open($table,$row);$masked=dc_mask($p,$table,$plain);
  foreach($spec['fields'] as $field)if($row[$field]!==$masked[$field])throw new RuntimeException('Indice o campo residuo non conforme: '.$table.'.'.$field);
  $count++;
 }
 return $count;
}
