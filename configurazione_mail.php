<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('gestione_email','lettura');
require_once __DIR__.'/includes/email.php';
if(!em_admin($user)){http_response_code(403);exit('Configurazione riservata agli amministratori.');}
$p=poimanager_db();
try{dc_active($p);}catch(RuntimeException $maintenance){page_start('Configurazione mail','Servizio temporaneamente in manutenzione.',['Servizi','Configurazione mail']);echo '<aside>'.em_h($maintenance->getMessage()).'</aside></main></body></html>';exit;}
if(!em_ready($p)){
 page_start('Configurazione mail','Configura il ponte Google e verifica la connessione.',['Servizi','Configurazione mail']);
 echo '<aside>Il modulo email non è ancora installato.</aside><p><a href="/email_installazione.php">Installa le tabelle dedicate</a></p></main></body></html>';exit;
}
$message=$_SESSION['email_config_flash']??'';unset($_SESSION['email_config_flash']);
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_access('gestione_email','scrittura');check_csrf();
  $action=(string)($_POST['action']??'');
  if($action==='config'){
   em_save_config($p,$_POST);header('Location: /configurazione_mail.php?salvato=1');exit;
  }elseif($action==='ping'){
   $c=em_config($p);if(!$c)throw new RuntimeException('Salvare prima la configurazione.');
   $r=em_ping($p,$c);
   $_SESSION['email_config_flash']='Connessione autenticata riuscita. Mittente: '.$r['sender'].' · quota residua: '.(int)$r['quota'].'. Nessuna email inviata.';
   header('Location: /configurazione_mail.php');exit;
  }else throw new RuntimeException('Operazione non valida.');
 }
}catch(Throwable $e){$message=$e instanceof PDOException?'Configurazione non completata. Verificare le tabelle dedicate e riprovare.':$e->getMessage();}
$config=em_config($p);
$assetVersion=static function(string $path):string{$file=__DIR__.$path;return is_file($file)?substr((@hash_file('sha256',$file)?:'0'),0,12):'0';};
page_start('Configurazione mail','Configura il ponte Google, il mittente e la dimensione dei lotti; verifica la connessione senza inviare email.',['Servizi','Configurazione mail']);
?>
<link rel="stylesheet" href="/assets/email.css?v=<?=em_h($assetVersion('/assets/email.css'))?>">
<?php if($message):?><aside role="status"><?=em_h($message)?></aside><?php endif;?>
<?php if(isset($_GET['salvato'])):?><aside role="status">Salvataggio completato.</aside><?php endif;?>
<?php require __DIR__.'/includes/email_config_form.php';?>
<script src="/assets/email.js?v=<?=em_h($assetVersion('/assets/email.js'))?>" defer></script></main></body></html>
