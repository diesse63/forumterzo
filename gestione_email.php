<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('gestione_email','lettura');
require_once __DIR__.'/includes/email.php';
$write=allowed($user,'gestione_email','scrittura');$admin=em_admin($user);
$tab=(string)($_GET['tab']??'componi');if(!in_array($tab,['componi','registro','config'],true))$tab='componi';
if($tab==='config'&&!$admin){http_response_code(403);exit('Configurazione riservata agli amministratori.');}
$message=$_SESSION['email_flash']??'';unset($_SESSION['email_flash']);$id=(int)($_GET['id']??$_POST['id']??0);$p=poimanager_db();
try{dc_active($p);}catch(RuntimeException $maintenance){page_start('Gestione email','Servizio temporaneamente in manutenzione.',['Servizi','Gestione email']);echo '<aside>'.em_h($maintenance->getMessage()).'</aside></main></body></html>';exit;}
if(!em_ready($p)){
 page_start('Gestione email','Messaggi, destinatari e conferme in un archivio dedicato.',['Servizi','Gestione email']);
 echo '<aside>Il modulo email non è ancora installato.</aside>';
 if($admin)echo '<p><a href="/email_installazione.php">Installa le tabelle dedicate</a></p>';
 echo '</main></body></html>';exit;
}
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_access('gestione_email','scrittura');check_csrf();$action=(string)($_POST['action']??'');
  if(in_array($action,['config','ping','testmail'],true)&&!$admin){http_response_code(403);exit('Operazione riservata agli amministratori.');}
  if(in_array($action,['send','testmail'],true))em_consume_send_nonce((string)($_POST['send_nonce']??''));
  if($action==='config'){
   em_save_config($p,$_POST);header('Location: /gestione_email.php?tab=config&salvato=1');exit;
  }elseif($action==='ping'){
   $c=em_config($p);if(!$c)throw new RuntimeException('Salvare prima la configurazione.');
   $r=em_ping($p,$c);$message='Connessione autenticata riuscita. Mittente: '.$r['sender'].' · quota residua: '.(int)$r['quota'].'. Nessuna email inviata.';$tab='config';
  }elseif($action==='testmail'){
   if(!isset($_POST['confirm']))throw new RuntimeException('Confermare l’invio al destinatario di prova.');
   $c=em_config($p);if(!$c)throw new RuntimeException('Configurazione assente.');
   $id=em_test_message($p,$c);$r=em_send_batch($p,$id);$tab='registro';$message='Prova: inviate '.$r['sent'].'. '.$r['notice'];
  }elseif($action==='save'){
   $input=$_POST;if(!isset($_POST['rich']))$input['html']='<p>'.nl2br(em_h((string)($_POST['text']??''))).'</p>';
   $id=em_save_draft($p,$input);header('Location: /gestione_email.php?tab=componi&id='.$id.'&salvato=1');exit;
  }elseif($action==='prepare'){
   if(!isset($_POST['confirm']))throw new RuntimeException('Confermare il blocco di testo e destinatari.');
   $count=em_prepare($p,$id);$message='Campagna preparata per '.$count.' destinatari. Nessuna email spedita: confermare il lotto dal registro.';$tab='registro';
  }elseif($action==='send'){
   if(!isset($_POST['confirm']))throw new RuntimeException('Confermare l’invio del lotto.');
   $r=em_send_batch($p,$id);$message='Inviate nel lotto: '.$r['sent'].'. '.$r['notice'];$tab='registro';
  }elseif($action==='status'){
   $n=em_reconcile($p,$id);$message='Esiti recuperati dal ponte: '.$n.'. Gli esiti ancora incerti non vengono ritentati.';$tab='registro';
  }else throw new RuntimeException('Operazione non valida.');
  $_SESSION['email_flash']=$message;header('Location: /gestione_email.php?tab='.urlencode($tab).'&id='.$id);exit;
 }
}catch(Throwable $e){
 $message=$e instanceof PDOException?'Operazione email non completata. Verificare le tabelle dedicate e riprovare senza duplicare invii con esito incerto.':$e->getMessage();
}
$config=em_config($p);$draft=null;$dest=[];$logs=[];$campaigns=em_campaigns($p);
if($id){$draft=dc_find($p,'email_messaggi',['ID'=>$id]);$dest=dc_rows($p,'email_destinatari','WHERE MessaggioID=? ORDER BY ID',[$id]);}
$frozen=$draft&&$draft['Stato']==='preparato';$editable=$write&&!$frozen;
$contacts=$tab==='componi'?em_contacts($p):[];$selected=array_map('intval',array_column($dest,'MailOrigineID'));
$tipi=$tab==='componi'?dc_rows($p,'Tipologia'):[];usort($tipi,fn($a,$b)=>strnatcasecmp($a['Tipologia'],$b['Tipologia']));
$lists=$tab==='componi'?dc_rows($p,'MailList'):[];usort($lists,fn($a,$b)=>strnatcasecmp($a['Descrizione'],$b['Descrizione']));
$membership=[];if($tab==='componi')foreach($p->query('SELECT IDMail,IDListaMail FROM ListaMail') as $row)$membership[$row['IDMail']][]=(int)$row['IDListaMail'];
if($tab==='registro')$logs=em_logs($p,$id);

page_start('Gestione email','Seleziona nominativi, prepara messaggi personali e consulta invii e conferme.',['Servizi','Gestione email']);
?>
<link rel="stylesheet" href="/assets/email.css">
<nav class="email-tabs" aria-label="Sezioni gestione email">
<a href="/gestione_email.php?tab=componi" <?=$tab==='componi'?'aria-current="page"':''?>>Destinatari e messaggio</a>
<a href="/gestione_email.php?tab=registro" <?=$tab==='registro'?'aria-current="page"':''?>>Invii e conferme</a>
<?php if($admin):?><a href="/gestione_email.php?tab=config" <?=$tab==='config'?'aria-current="page"':''?>>Configurazione</a><?php endif;?></nav>
<?php if($message):?><aside role="status"><?=em_h($message)?></aside><?php endif;if(isset($_GET['salvato'])):?><aside role="status">Salvataggio completato.</aside><?php endif;?>
<?php if($config):?><p class="email-mode">Configurazione attiva #<?=(int)$config['ID']?> · <?=em_h($config['Modalita'])?> · Mittente <?=em_h($config['Mittente'])?><?php if($config['Modalita']==='test'):?> · Le nuove campagne saranno inviate soltanto a <strong><?=em_h($config['EmailTest'])?></strong><?php endif;?></p><?php else:?><aside>Configurazione mancante: un amministratore deve collegare il ponte Google.</aside><?php endif;?>
<?php if($tab==='componi'):?>
<section class="email-card"><h2>Bozze e messaggi</h2><form method="get" class="email-row"><input type="hidden" name="tab" value="componi"><label>Messaggio<select name="id"><option value="0">Nuovo messaggio</option><?php foreach($campaigns as $m):?><option value="<?=(int)$m['ID']?>" <?=(int)$m['ID']===$id?'selected':''?>>#<?=(int)$m['ID']?> · <?=em_h($m['Oggetto'])?> · <?=em_h($m['Stato'])?></option><?php endforeach;?></select></label><button>Apri</button><a href="/gestione_email.php?tab=componi">Nuovo messaggio</a></form></section>
<?php if($frozen):?><aside>Messaggio preparato: testo e destinatari sono bloccati. <a href="/gestione_email.php?tab=registro&id=<?=$id?>">Apri il registro per inviare</a>. Per modificarlo crea un nuovo messaggio.</aside><?php endif;?>
<form method="post" id="email-compose" data-editable="<?=$editable?'1':'0'?>">
<input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=$id?>"><input type="hidden" name="version" value="<?=(int)($draft['Versione']??0)?>">
<div class="email-grid"><section class="email-card"><h2>Destinatari <span id="email-count"></span></h2>
<label>Cerca nominativo, ente o email<input type="search" id="email-search" placeholder="Cerca…" autocomplete="off"></label>
<label>Tipologia ente<select id="email-type"><option value="">Tutte</option><?php foreach($tipi as $t):?><option value="<?=(int)$t['ID']?>"><?=em_h($t['Tipologia'])?></option><?php endforeach;?></select></label>
<label>Mailing list<select id="email-list"><option value="">Tutte</option><?php foreach($lists as $l):?><option value="<?=(int)$l['ID']?>"><?=em_h($l['Descrizione'])?></option><?php endforeach;?></select></label>
<?php if($editable):?><div class="email-row"><button type="button" id="email-all">Seleziona visibili</button><button type="button" id="email-none">Nessuno</button></div><?php endif;?>
<div class="email-contacts" id="email-contacts">
<?php if($frozen):foreach($dest as $d):?><p><strong><?=em_h($d['Nome'])?></strong><small><?=em_h($d['Email'])?></small></p><?php endforeach;else:foreach($contacts as $c):$valid=$c['EnteID']&&filter_var($c['Indirizzo'],FILTER_VALIDATE_EMAIL);?>
<label class="email-contact" data-search="<?=em_h($c['Nome'].' '.$c['Ente'].' '.$c['Indirizzo'])?>" data-type="<?=(int)$c['ID_TIPOLOGIA']?>" data-lists="<?=em_h(implode(',',$membership[$c['ID']]??[]))?>">
<input type="checkbox" name="mail_ids[]" value="<?=(int)$c['ID']?>" <?=in_array((int)$c['ID'],$selected,true)?'checked':''?> <?=!$editable||!$valid?'disabled':''?>>
<span><strong><?=em_h($c['Nome'])?></strong><small><?=em_h($c['Indirizzo'])?> · <?=em_h($c['TipoSoggetto'])?><?php if((int)$c['PEC'])echo ' · PEC';?></small><small><?=em_h($c['Ente'])?><?=$valid?'':' · Indirizzo non valido o collegamento mancante'?></small></span></label>
<?php endforeach;endif;?></div><p class="email-muted">La rubrica attuale viene solo letta. Indirizzi uguali vengono accorpati nella bozza: prevale il primo nominativo per ID indirizzo. La preparazione congela nome e indirizzo.</p>
</section><section class="email-card"><h2>Componi messaggio</h2>
<label>Oggetto<input name="subject" id="email-subject" maxlength="250" required value="<?=em_h($draft['Oggetto']??'')?>" <?=$editable?'':'disabled'?>></label>
<div id="email-toolbar" class="email-toolbar" hidden>
<button type="button" data-command="bold"><b>G</b></button><button type="button" data-command="italic"><i>C</i></button><button type="button" data-command="underline"><u>S</u></button><button type="button" data-command="insertUnorderedList">• Elenco</button><button type="button" data-command="insertOrderedList">1. Elenco</button><button type="button" id="email-link">Link</button><button type="button" id="email-name">{nome}</button><label>Colore<input type="color" id="email-color" value="#175cd3"></label><button type="button" data-command="removeFormat">Togli formato</button></div>
<div id="email-editor" role="textbox" aria-label="Testo formattato" aria-multiline="true" hidden><?= $draft?em_sanitize($draft['HTML']):'<p>Buongiorno {nome},</p><p></p>' ?></div>
<label id="email-text-label">Testo<textarea name="text" id="email-text" rows="12" maxlength="30000" required <?=$editable?'':'disabled'?>><?=em_h($draft['Testo']??'Buongiorno {nome},')?></textarea></label>
<textarea name="html" id="email-html" hidden><?=em_h($draft['HTML']??'<p>Buongiorno {nome},</p>')?></textarea>
<p class="email-muted">Il segnaposto {nome} sarà sostituito con il nominativo. Il pulsante di conferma viene aggiunto all’invio. Nessuna conferma viene registrata aprendo semplicemente il link.</p>
<div class="email-row"><button type="button" id="email-preview">Anteprima</button><?php if($editable):?><button>Salva bozza e destinatari</button><?php endif;?></div>
</section></div></form>
<?php if($write&&$draft&&!$frozen):?><form method="post" class="email-card"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="prepare"><input type="hidden" name="id" value="<?=$id?>"><h2>Prepara l’invio</h2><p>Salva prima le modifiche. Verranno bloccati il testo e <?=count($dest)?> destinatari della bozza salvata, usando la configurazione attiva. Non viene ancora spedita nessuna email.</p><label><input type="checkbox" name="confirm" required> Confermo testo, destinatari e modalità della configurazione attiva.</label><button>Prepara campagna senza inviare</button></form><?php endif;?>
<dialog id="email-preview-dialog"><h2>Anteprima destinatario</h2><p id="email-preview-who"></p><iframe sandbox="" title="Anteprima email" id="email-preview-frame"></iframe><p>Il pulsante è dimostrativo.</p><button type="button" id="email-preview-close">Chiudi</button></dialog>
<?php elseif($tab==='registro'):?>
<section class="email-card"><h2>Campagne</h2><p>Ogni lotto invia al massimo 20 email, entro la quota restituita da Google. Una conferma volontaria non è una ricevuta automatica di lettura o consegna.</p>
<?php foreach($campaigns as $m):if($m['Stato']!=='preparato')continue;$c=em_config($p,(int)$m['ConfigID']);?>
<article class="email-campaign"><h3>#<?=(int)$m['ID']?> · <?=em_h($m['Oggetto'])?></h3><p>Configurazione #<?=(int)$m['ConfigID']?> · <?=em_h($c['Modalita'])?> · <?=em_h($c['Mittente'])?></p><a href="/gestione_email.php?tab=registro&id=<?=(int)$m['ID']?>">Mostra destinatari e conferme</a>
<?php if($write):?><form method="post"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$m['ID']?>"><input type="hidden" name="action" value="send"><input type="hidden" name="send_nonce" value="<?=em_h(em_send_nonce())?>"><label><input type="checkbox" name="confirm" required> Confermo l’invio del prossimo lotto <?=$c['Modalita']==='test'?'soltanto al destinatario di prova '.em_h($c['EmailTest']):'ai destinatari reali di questa campagna'?>.</label><button>Invia prossimo lotto</button></form>
<form method="post"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$m['ID']?>"><input type="hidden" name="action" value="status"><button>Verifica esiti senza reinviare</button></form><?php endif;?></article><?php endforeach;?></section>
<div class="email-table-scroll"><table><tr><th>Messaggio</th><th>Destinatario</th><th>Indirizzo effettivo</th><th>Stato</th><th>Invio UTC</th><th>Conferma UTC</th></tr>
<?php foreach($logs as $r):?><tr><td><?=em_h($r['Oggetto'])?></td><td><?=em_h($r['Nome'])?><small><?=em_h($r['Email'])?></small></td><td><?=em_h($r['EmailEffettiva'])?></td><td><?=em_h($r['Stato'])?><small><?=em_h($r['Nota'])?></small></td><td><?=em_h($r['InviatoIl']??'—')?></td><td><?=em_h($r['ConfermatoIl']??'—')?></td></tr><?php endforeach;?></table></div>
<?php else:require __DIR__.'/includes/email_config_form.php';endif;?>
<script src="/assets/email.js" defer></script></main></body></html>
