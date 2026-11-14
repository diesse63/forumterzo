<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/protezione_http.php';
$message='';
try {
 if(is_file(__DIR__.'/private/security.php'))throw new RuntimeException('Installazione già completata. Usare Gestione utenti.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$cfg=poimanager_config();
  if(strlen($cfg['admin_password'])<16||str_contains($cfg['admin_password'],'CAMBIARE')||!hash_equals($cfg['admin_password'],(string)($_POST['setup_password']??'')))throw new RuntimeException('Password di installazione non valida.');
  password_policy((string)($_POST['admin_password']??''));password_policy((string)($_POST['user_password']??''));
  $cipher=profile_choose_cipher();
  protection_transport();
  // Verifica che Apache neghi l'accesso prima di scrivere la chiave.
  if(trim((string)file_get_contents(__DIR__.'/private/.htaccess'))!=='Require all denied')throw new RuntimeException('Ripristinare private/.htaccess.');
  $name='prova_'.bin2hex(random_bytes(16)).'.txt';$probe=__DIR__.'/private/'.$name;
  if(file_put_contents($probe,'Verifica non riservata')===false)throw new RuntimeException('Cartella private non scrivibile.');
  try{
   protection_require_403('https://poimanager.altervista.org/private/'.$name,'private');
  }finally{@unlink($probe);}
  $db=poimanager_db();$q=$db->query("SELECT GET_LOCK('poimanager_setup_utenti',0)");
  if((int)$q->fetchColumn()!==1)throw new RuntimeException('Installazione già in corso.');
  $created=false;$file=__DIR__.'/private/security.php';
  try{
   if(is_file($file)||(int)$db->query('SELECT COUNT(*) FROM Utenti')->fetchColumn()!==0)throw new RuntimeException('Utenti già configurati. Installazione bloccata.');
   $dev=isset($_POST['development']);
   $secret=['cipher'=>$cipher,'key'=>base64_encode(random_bytes(32)),'development'=>$dev,'ghost_enabled'=>$dev,'ghost_hash'=>password_hash((string)($_POST['ghost_password']??''),PASSWORD_DEFAULT)];
   if($dev&&($_POST['ghost_password']??'')==='')throw new RuntimeException('Indicare la password ghost per lo sviluppo.');
   $handle=fopen($file,'x');
   if(!$handle)throw new RuntimeException('Impossibile creare security.php.');
   $created=true;
   $bytes="<?php\nif (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }\nreturn ".var_export($secret,true).";\n";
   try {if(fwrite($handle,$bytes)!==strlen($bytes))throw new RuntimeException('Salvataggio chiave incompleto.');}finally{fclose($handle);}
   chmod($file,0600);
   $db->beginTransaction();
   foreach([['admin','admin',(string)$_POST['admin_password']],['user','user',(string)$_POST['user_password']]] as [$name,$role,$pass]){
    $q=$db->prepare('INSERT INTO Utenti (NomeIndice,ProfiloCifrato,PasswordHash,Ruolo,Attivo) VALUES (?,?,?,?,1)');
    $q->execute([user_index($name),user_encrypt(['nome'=>$name,'email'=>'']),password_hash($pass,PASSWORD_DEFAULT),$role]);
   }
   foreach(['utenti'=>'Gestione utenti','importazione'=>'Importazione SQLite','associazioni'=>'Associazioni','soggetti'=>'Soggetti','mail'=>'Email','telefono'=>'Telefoni','maillist'=>'Liste email','agora'=>'Agorà','runts'=>'RUNTS','tipologia'=>'Tipologie','report'=>'Report'] as $code=>$title){
    $q=$db->prepare('INSERT INTO Pannelli (Codice,Titolo) VALUES (?,?)');$q->execute([$code,$title]);
   }
   $db->commit();$created=false;$message='Installazione completata. Accedere da login.php. User non ha ancora permessi: assegnarli da Gestione utenti.';
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($created)@unlink($file);throw $e;}
  finally{$db->query("SELECT RELEASE_LOCK('poimanager_setup_utenti')");}
 }
}catch(Throwable $e){$message=$e instanceof PDOException?'Verificare di avere eseguito utenti/migrazione_v2.sql e che le nuove tabelle siano vuote.':$e->getMessage();}
page_start('Installazione utenti');
try { echo '<aside>Compatibilità: cifratura '.uh(profile_choose_cipher()).' · verifica HTTPS '.uh(protection_transport()).'.</aside>'; }
catch (RuntimeException $dependencyError) { echo '<aside>'.uh($dependencyError->getMessage()).'</aside>'; }
if($message)echo '<aside>'.uh($message).'</aside>';
if(!is_file(__DIR__.'/private/security.php')): ?>
<p>Se gli utenti erano già installati e la chiave originale è perduta, usare <a href="/recupera_utenti.php">Recupero accessi</a>. Non ripetere la migrazione SQL e non rinominare il file di esempio.</p>
<p>Prima eseguire la migrazione SQL in manutenzione. Gli utenti precedenti restano separati e non vengono importati.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?=uh(csrf())?>">
<label>Password di installazione (admin_password in private/config.php)<input type="password" name="setup_password" required></label>
<label>Password iniziale admin<input type="password" name="admin_password" minlength="7" required autocomplete="new-password"></label>
<label>Password iniziale user<input type="password" name="user_password" minlength="7" required autocomplete="new-password"></label>
<label><input type="checkbox" name="development" value="1"> Modalità sviluppo con account ghost fuori dal database</label>
<label>Password ghost, solo sviluppo<input type="password" name="ghost_password" autocomplete="new-password"></label>
<button>Inizializza utenti e chiave di cifratura</button>
</form>
<?php endif; echo '</main></body></html>';
