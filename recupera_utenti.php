<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/recupero_utenti.php';
require_once __DIR__.'/includes/protezione_http.php';
$message='';$report=null;$done=null;
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$token=(string)($_POST['recovery_token']??'');$private=__DIR__.'/private';
  ru_authorize($private,$token);
  $db=poimanager_db();$report=ru_inspect($db,$private);
  if(($_POST['action']??'')==='recover'){
   if(!isset($_POST['backup_confirm']))throw new RuntimeException('Confermare il backup e la sostituzione degli accessi.');
   password_policy((string)($_POST['new_password']??''));
   if(trim((string)@file_get_contents($private.'/.htaccess'))!=='Require all denied')throw new RuntimeException('Ripristinare private/.htaccess.');
   $name='prova_'.bin2hex(random_bytes(16)).'.txt';$probe=$private.'/'.$name;
   if(file_put_contents($probe,'Verifica non riservata')===false)throw new RuntimeException('Cartella privata non scrivibile.');
   try{protection_require_403('https://poimanager.altervista.org/private/'.$name,'private');}finally{@unlink($probe);}
   $done=ru_recover($db,$private,$token,(string)$_POST['new_password']);
   $_SESSION=[];session_regenerate_id(true);
   $message='Accessi ripristinati. Accedere come admin con la nuova password. Creare nuovamente gli altri utenti e i loro permessi.';
  }elseif(($_POST['action']??'')!=='inspect')throw new RuntimeException('Operazione non valida.');
 }
}catch(Throwable $e){$report=null;$message=$e instanceof PDOException?'Struttura database non compatibile: nessun recupero completato. Non eseguire nuovamente la migrazione SQL.':$e->getMessage();}
page_start('Recupero accessi','Verifica lo stato della cifratura e ripristina gli accessi quando la chiave utenti originale è perduta.',['Servizi','Recupero accessi']);
if($message)echo '<aside role="status">'.uh($message).'</aside>';
if($done){
 echo '<p>Archivi conservati: '.uh($done['archivio_utenti']).' e '.uh($done['archivio_permessi']).'. Enti, recapiti, email e chiavi dei dati non vengono modificati.</p><p>Fare subito una copia protetta del nuovo private/security.php.</p><p><a href="/login.php">Vai all’accesso</a></p>';
}elseif(!is_file(__DIR__.'/private/security.php')){
 if($report)echo '<aside>Utenti da archiviare: '.uh($report['utenti']).' · permessi: '.uh($report['permessi']).' · cifratura dati: '.uh($report['cifratura']).'.</aside>';
 echo '<p>Abilitare la procedura creando private/recupero_utenti.php secondo RECUPERO_ACCESSI.md. La credenziale temporanea è diversa dalla password del gestionale e dal segreto del ponte Gmail.</p>';
 ?>
 <form method="post">
 <input type="hidden" name="csrf" value="<?=uh(csrf())?>">
 <label>Credenziale temporanea di recupero<input type="password" name="recovery_token" required minlength="64" maxlength="64" autocomplete="off"></label>
 <button name="action" value="inspect">Verifica senza modificare</button>
 <label>Nuova password admin (almeno 7 caratteri e uno speciale)<input type="password" name="new_password" autocomplete="new-password" minlength="7" maxlength="72"></label>
 <label><input type="checkbox" name="backup_confirm" value="1"> Ho salvato il backup del database e della cartella private; autorizzo l’archiviazione degli utenti e dei permessi precedenti e la creazione del nuovo amministratore.</label>
 <button name="action" value="recover">Archivia accessi e crea nuovo admin</button>
 </form>
 <?php
}else echo '<p>Chiave utenti presente: recupero disabilitato. <a href="/login.php">Accedi</a>.</p>';
echo '</main></body></html>';
