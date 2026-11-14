<?php
declare(strict_types=1);
require_once __DIR__.'/crypto.php';
require_once __DIR__.'/dati.php';
function ru_authorize(string $private,string $token): void {
 if(is_file($private.'/security.php'))throw new RuntimeException('Chiave utenti presente: recupero bloccato. Usare la gestione utenti.');
 $file=$private.'/recupero_utenti.php';
 if(!is_file($file))throw new RuntimeException('Recupero non abilitato: creare la credenziale temporanea nella cartella private seguendo RECUPERO_ACCESSI.md.');
 if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
 $cfg=require $file;$expected=is_array($cfg)?($cfg['token']??''):'';
 if(!is_string($expected)||!preg_match('/^[a-f0-9]{64}$/D',$expected)||!hash_equals($expected,$token))throw new RuntimeException('Credenziale temporanea di recupero non valida.');
}
function ru_inspect(PDO $p,string $private): array {
 if(is_file($private.'/security.php'))throw new RuntimeException('Chiave utenti presente: recupero bloccato.');
 $state=dc_state($p);
 if($state){
  if(!is_file($private.'/dati_security.php'))throw new RuntimeException('Cifratura dati già avviata ma dati_security.php assente. Ripristinare la chiave originale: recupero bloccato, nessun dato modificato.');
  if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
  $keys=require $private.'/dati_security.php';
  if(!is_array($keys)||!profile_cipher_available($keys['cipher']??'')||strlen(base64_decode($keys['key']??'',true)?:'')!==32||strlen(base64_decode($keys['index_key']??'',true)?:'')!==32||!hash_equals((string)$state['ChiaveHash'],hash('sha256',$keys['key'].'|'.$keys['index_key'])))throw new RuntimeException('Chiave dati non corrispondente al database: recupero bloccato.');
 }
 // Rileva anche cifrati residui in assenza del registro di migrazione.
 $q=$p->query("SELECT TABLE_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='_Cifrato'");
 foreach($q->fetchAll(PDO::FETCH_COLUMN) as $table){
  if((int)$p->query('SELECT EXISTS(SELECT 1 FROM '.dc_q($table).' WHERE _Cifrato IS NOT NULL)')->fetchColumn()===1&&!$state)throw new RuntimeException('Dati cifrati presenti senza registro di migrazione. Occorre verificare il backup: recupero bloccato.');
 }
 foreach(['Utenti','UtentiPermessi','AccessiTentativi'] as $table){
  $q=$p->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
  if($q->fetchColumn()!=='InnoDB')throw new RuntimeException('Schema utenti non compatibile. Non ripetere la migrazione SQL.');
 }
 $required=['ID','NomeIndice','ProfiloCifrato','PasswordHash','Ruolo','Attivo','VersioneSessione'];
 if(array_diff($required,array_keys(dc_columns($p,'Utenti',true))))throw new RuntimeException('Schema utenti non compatibile con il recupero.');
 $q=$p->query("SELECT DISTINCT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN ('Utenti','UtentiPermessi') AND NOT (REFERENCED_TABLE_NAME='Utenti' AND TABLE_NAME='UtentiPermessi')");
 if($q->fetchColumn()!==false)throw new RuntimeException('Altre tabelle collegate agli utenti: serve una mappatura specifica, nessun recupero automatico.');
 return ['utenti'=>(int)$p->query('SELECT COUNT(*) FROM Utenti')->fetchColumn(),'permessi'=>(int)$p->query('SELECT COUNT(*) FROM UtentiPermessi')->fetchColumn(),'cifratura'=>$state['Stato']??'non avviata'];
}
function ru_recover(PDO $p,string $private,string $token,string $password): array {
 ru_authorize($private,$token);
 // La pagina applica anche password_policy(); la procedura verifica autonomamente la stessa regola.
 if(strlen($password)>72||preg_match('/^.{7,}$/us',$password)!==1||preg_match('/[^\p{L}\p{N}\s]/u',$password)!==1)throw new RuntimeException('Password: almeno 7 caratteri e uno speciale, massimo 72 byte.');
 $q=$p->query("SELECT GET_LOCK('poimanager_setup_utenti',0)");
 if((int)$q->fetchColumn()!==1)throw new RuntimeException('Installazione o recupero già in corso.');
 $created=false;$commitAttempted=false;$file=$private.'/security.php';
 try{
  ru_authorize($private,$token);$before=ru_inspect($p,$private);$cipher=profile_choose_cipher();
  $suffix=bin2hex(random_bytes(6));$users='Utenti_rec_'.$suffix;$permissions='Permessi_rec_'.$suffix;
  // DDL prima della transazione: CREATE LIKE non copia le chiavi esterne.
  $p->exec('CREATE TABLE '.dc_q($users).' LIKE Utenti');
  $p->exec('CREATE TABLE '.dc_q($permissions).' LIKE UtentiPermessi');
  $p->beginTransaction();
  $p->query('SELECT ID FROM Utenti FOR UPDATE')->fetchAll();
  $p->query('SELECT UtenteID FROM UtentiPermessi FOR UPDATE')->fetchAll();
  $p->exec('INSERT INTO '.dc_q($users).' SELECT * FROM Utenti');
  $p->exec('INSERT INTO '.dc_q($permissions).' SELECT * FROM UtentiPermessi');
  if((int)$p->query('SELECT COUNT(*) FROM '.dc_q($users))->fetchColumn()!==$before['utenti']||(int)$p->query('SELECT COUNT(*) FROM '.dc_q($permissions))->fetchColumn()!==$before['permessi'])throw new RuntimeException('Verifica archivio non riuscita.');
  $key=random_bytes(32);
  $secret=['key'=>base64_encode($key),'cipher'=>$cipher,'development'=>false,'ghost_enabled'=>false,'ghost_hash'=>password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)];
  $p->exec('DELETE FROM UtentiPermessi');
  $p->exec('DELETE FROM Utenti');
  $q=$p->prepare('INSERT INTO Utenti (NomeIndice,ProfiloCifrato,PasswordHash,Ruolo,Attivo,VersioneSessione) VALUES (?,?,?,?,1,?)');
  $q->execute([hash_hmac('sha256','admin',$key),profile_encrypt(['nome'=>'admin','email'=>''],$key,$cipher),password_hash($password,PASSWORD_DEFAULT),'admin',random_int(1000000000,PHP_INT_MAX-1)]);
  // Scrittura esclusiva: nessuna chiave esistente può essere sovrascritta.
  $h=@fopen($file,'x');if(!$h)throw new RuntimeException('Impossibile creare la chiave utenti. Nessun account modificato.');
  $created=true;
  $bytes="<?php\nif (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }\nreturn ".var_export($secret,true).";\n";
  try{
   if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h))throw new RuntimeException('Scrittura chiave utenti incompleta.');
   if(function_exists('fsync')&&!fsync($h))throw new RuntimeException('Salvataggio persistente della chiave non riuscito.');
  }finally{fclose($h);}
  @chmod($file,0600);
  $commitAttempted=true;$p->commit();
  @unlink($private.'/recupero_utenti.php');
  return $before+['archivio_utenti'=>$users,'archivio_permessi'=>$permissions];
 }catch(Throwable $e){
  if($p->inTransaction())$p->rollBack();
  // Se l'esito del COMMIT non è certo, conservare la chiave per la verifica manuale.
  if($created&&!$commitAttempted)@unlink($file);
  if($commitAttempted)throw new RuntimeException('Esito del salvataggio da verificare. Conservare security.php e gli archivi: non ripetere installazione o recupero.',0,$e);
  throw $e;
 }finally{$p->query("SELECT RELEASE_LOCK('poimanager_setup_utenti')");}
}
