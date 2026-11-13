<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
try{
 $u=current_user();if(!$u){header('Location: /login.php');exit;}
 if($u['Ruolo']==='ghost'){http_response_code(403);exit('La credenziale di sviluppo è gestita nel file protetto security.php.');}
 $msg='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$new=(string)($_POST['nuova']??'');password_policy($new);
  if(!hash_equals($new,(string)($_POST['conferma']??'')))throw new RuntimeException('Le nuove password non coincidono.');
  if(!password_verify((string)($_POST['attuale']??''),$u['PasswordHash']))throw new RuntimeException('Password attuale non valida.');
  $q=poimanager_db()->prepare('UPDATE Utenti SET PasswordHash=?,VersioneSessione=VersioneSessione+1 WHERE ID=? AND VersioneSessione=?');
  $q->execute([password_hash($new,PASSWORD_DEFAULT),$u['ID'],$u['VersioneSessione']]);
  unset($_SESSION['account']);header('Location: /login.php');exit;
 }
}catch(Throwable $e){$msg=$e instanceof PDOException?'Operazione non disponibile.':$e->getMessage();}
page_start('Cambia password');if(!empty($msg))echo '<aside>'.uh($msg).'</aside>';
?>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>">
<label>Password attuale<input name="attuale" type="password" required autocomplete="current-password"></label>
<label>Nuova password<input name="nuova" type="password" minlength="7" required autocomplete="new-password"></label>
<label>Conferma<input name="conferma" type="password" required autocomplete="new-password"></label>
<p>Almeno 7 caratteri e uno speciale. Nome ed email resteranno leggibili: il cambio password non modifica la chiave di cifratura.</p>
<button>Salva password</button></form></main></body></html>
