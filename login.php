<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$error='';
try{
 if(current_user()){header('Location: /pannello.php');exit;}
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();user_login((string)($_POST['nome']??''),(string)($_POST['password']??''));
  header('Location: /pannello.php');exit;
 }
}catch(Throwable $e){$error=$e instanceof PDOException?'Accesso temporaneamente non disponibile.':$e->getMessage();}
page_start('Accesso POImanager');
if($error)echo '<aside>'.uh($error).'</aside>';
?>
<form method="post">
<input type="hidden" name="csrf" value="<?=uh(csrf())?>">
<label>Nome utente<input name="nome" required maxlength="100" autocomplete="username"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<button>Accedi</button>
</form></main></html>
