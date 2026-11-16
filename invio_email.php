<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('gestione_email','lettura');
require_once __DIR__.'/includes/email.php';
$write=allowed($user,'gestione_email','scrittura');$admin=em_admin($user);
// Compatibilità con i collegamenti e i moduli aperti prima della separazione.
if(($_GET['tab']??'')==='config'||($_SERVER['REQUEST_METHOD']==='POST'&&in_array($_POST['action']??'',['config','ping'],true))){
 if(!$admin){http_response_code(403);exit('Configurazione riservata agli amministratori.');}
 header('Location: /configurazione_mail.php',true,$_SERVER['REQUEST_METHOD']==='POST'?307:302);exit;
}
$tab=(string)($_GET['tab']??'registro');if(!in_array($tab,['componi','registro','esiti'],true))$tab='registro';
if($tab==='registro'&&isset($_GET['esiti']))$tab='esiti';
$message=$_SESSION['email_flash']??'';unset($_SESSION['email_flash']);$id=(int)($_POST['id']??$_GET['id']??0);$p=poimanager_db();
try{dc_active($p);}catch(RuntimeException $maintenance){page_start('Gestione mail','Servizio temporaneamente in manutenzione.',['Servizi','Gestione mail','Gestione mail']);echo '<aside>'.em_h($maintenance->getMessage()).'</aside></main></body></html>';exit;}
if(!em_ready($p)){
 page_start('Gestione mail','Messaggi, destinatari e conferme in un archivio dedicato.',['Servizi','Gestione mail','Gestione mail']);
 echo '<aside>Il modulo email non è ancora installato.</aside>';
 if($admin)echo '<p><a href="/email_installazione.php">Installa le tabelle dedicate</a></p>';
 echo '</main></body></html>';exit;
}
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_access('gestione_email','scrittura');check_csrf();$action=(string)($_POST['action']??'');
  if($action==='send')em_consume_send_nonce((string)($_POST['send_nonce']??''));
  if($action==='save'){
   $input=$_POST;if(!isset($_POST['rich']))$input['html']='<p>'.nl2br(em_h((string)($_POST['text']??''))).'</p>';
   $id=em_save_draft($p,$input);header('Location: /invio_email.php?tab=registro&id='.$id.'&salvato=1');exit;
  }elseif($action==='duplicate'){
   $source=dc_find($p,'email_messaggi',['ID'=>$id]);$subject=(string)($source['Oggetto']??'(Senza oggetto)');$duplicateSubject=trim((string)($_POST['duplicate_subject']??$subject));if($duplicateSubject!==''&&(strlen($duplicateSubject)>250||preg_match('/[\r\n]/',$duplicateSubject)))throw new RuntimeException('Nome della lista non valido: massimo 250 caratteri e nessun ritorno a capo.');$id=em_duplicate($p,$id,isset($_POST['copy_recipients'])&&$_POST['copy_recipients']==='1',$duplicateSubject);$message='Lista “'.$duplicateSubject.'” duplicata come nuova bozza.';$tab='registro';
  }elseif($action==='delete'){
   if(!isset($_POST['confirm']))throw new RuntimeException('Confermare l’eliminazione della mail e del relativo registro.');
   $source=dc_find($p,'email_messaggi',['ID'=>$id]);$subject=(string)($source['Oggetto']??'(Senza oggetto)');em_delete($p,$id,(int)($_POST['version']??0));$id=0;$tab='registro';$message='Lista “'.$subject.'” eliminata dal gestionale. Le email già spedite non sono state ritirate.';
  }elseif($action==='prepare'){
   if(!isset($_POST['confirm']))throw new RuntimeException('Confermare il blocco di testo e destinatari.');
   $count=em_prepare($p,$id);$message='Elaborazione completata: lista preparata per '.$count.' destinatari. Nessuna email è stata ancora spedita.';$tab='registro';
  }elseif($action==='send'){
   if(!isset($_POST['confirm']))throw new RuntimeException('Confermare l’invio del lotto.');
   $r=em_send_batch($p,$id);$message=$r['notice']!==''?'Avviso invio: '.$r['notice']:'Invio completato correttamente.';$message='Inviate nel lotto: '.$r['sent'].'. '.$message;$tab='registro';
  }elseif($action==='status'){
   $n=em_reconcile($p,$id);$message='Esiti recuperati dal ponte: '.$n.'. Gli esiti ancora incerti non vengono ritentati.';$tab='registro';
  }else throw new RuntimeException('Operazione non valida.');
  $_SESSION['email_flash']=$message;header('Location: /invio_email.php?tab='.urlencode($tab).'&id='.$id);exit;
 }
}catch(Throwable $e){
 $message=$e instanceof PDOException?'Operazione email non completata. Verificare le tabelle dedicate e riprovare senza duplicare invii con esito incerto.':$e->getMessage();
}
$config=em_config($p);$draft=null;$dest=[];$logs=[];$campaigns=em_campaigns($p);
if($id){$draft=dc_find($p,'email_messaggi',['ID'=>$id]);$dest=dc_rows($p,'email_destinatari','WHERE MessaggioID=? ORDER BY ID',[$id]);}
$frozen=$draft&&$draft['Stato']==='preparato';$editable=$write;
$contacts=$tab==='componi'?em_address_book($p):[];$selected=array_map('intval',array_column($dest,'MailOrigineID'));
$tipi=$tab==='componi'?dc_rows($p,'Tipologia'):[];usort($tipi,fn($a,$b)=>strnatcasecmp($a['Tipologia'],$b['Tipologia']));
$lists=$tab==='componi'?dc_rows($p,'MailList'):[];usort($lists,fn($a,$b)=>strnatcasecmp($a['Descrizione'],$b['Descrizione']));
if($tab==='registro'||$tab==='esiti')$logs=em_logs($p,$id);

$assetVersion=static function(string $path):string{$file=__DIR__.$path;return is_file($file)?substr((@hash_file('sha256',$file)?:'0'),0,12):'0';};
$headingExtra=$config?'(Configurazione attiva #'.(int)$config['ID'].' · Mittente '.$config['Mittente'].'. Gli invii sono diretti agli indirizzi selezionati nell’anagrafica.)':'';
$pageTitle=$tab==='esiti'?'Verifica esiti':'Gestione mail';$pageDescription=$tab==='esiti'?'Consulta gli esiti di invio e le conferme di ricezione.':'Seleziona nominativi, prepara messaggi personali e consulta invii e conferme.';
page_start($pageTitle,$pageDescription,['Servizi','Gestione mail',$pageTitle],$headingExtra);
?>
<link rel="stylesheet" href="/assets/email.css?v=<?=em_h($assetVersion('/assets/email.css'))?>">
<?php if($message):?><aside role="status"><?=em_h($message)?></aside><?php endif;if(isset($_GET['salvato'])):?><aside role="status">Salvataggio completato.</aside><?php endif;?>
<?php if($config):?><?php if($config['Modalita']!=='produzione'):?><aside>Salvare una nuova configurazione prima di preparare nuove campagne.</aside><?php endif;else:?><aside>Configurazione mancante: un amministratore deve collegare il ponte Google.</aside><?php endif;?>
<?php if($tab==='componi'):?>
<?php require __DIR__.'/includes/email_compose.php';?>
<?php elseif($tab==='esiti'):?>
<?php require __DIR__.'/includes/email_esiti.php';?>
<?php elseif($tab==='registro'):?>
<?php require __DIR__.'/includes/email_archivio.php';?>
<?php endif;?>
<script src="/assets/email_destinatari.js?v=<?=em_h($assetVersion('/assets/email_destinatari.js'))?>" defer></script>
<script src="/assets/email.js?v=<?=em_h($assetVersion('/assets/email.js'))?>" defer></script></main></body></html>




