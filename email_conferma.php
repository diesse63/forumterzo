<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'");
session_name('email_conferma');session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Lax']);session_start();
require_once __DIR__.'/includes/email.php';
$token=is_string($_POST['t']??$_GET['t']??null)?($_POST['t']??$_GET['t']):'';
$_SESSION['nonce']??=bin2hex(random_bytes(32));$valid=preg_match('/^[a-f0-9]{64}$/D',$token)===1;$done=false;$message='';
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!is_string($_POST['nonce']??null)||!hash_equals($_SESSION['nonce'],$_POST['nonce']))throw new RuntimeException('Ricaricare la pagina dal collegamento ricevuto.');
  $done=$valid&&em_confirm(poimanager_db(),$token);$message=$done?'Conferma registrata. Grazie! Puoi chiudere questa pagina.':'Collegamento non valido, scaduto o non utilizzabile.';
 }
}catch(Throwable $e){$message='Conferma temporaneamente non disponibile. Riprova dal collegamento ricevuto.';}
?>
<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conferma lettura · Gestione</title><link rel="stylesheet" href="/assets/layout.css"><link rel="stylesheet" href="/assets/email.css"></head><body>
<header class="app-header"><span class="app-name">Gestione</span><p class="app-context">Conferma di lettura</p></header><main class="page-main email-public"><h1 class="page-title">Conferma di lettura</h1><p class="page-description">Conferma volontariamente di aver letto il messaggio ricevuto.</p>
<?php if($message):?><aside role="status"><?=em_h($message)?></aside><?php endif;?>
<?php if($valid&&!$done):?><p>Premi il pulsante per confermare. Non devi inserire nome o indirizzo email.</p><form method="post"><input type="hidden" name="t" value="<?=em_h($token)?>"><input type="hidden" name="nonce" value="<?=em_h($_SESSION['nonce'])?>"><button>Confermo la lettura</button></form><?php elseif(!$valid):?><p>Collegamento non valido. Apri il link completo presente nell’email.</p><?php endif;?>
</main></body></html>
