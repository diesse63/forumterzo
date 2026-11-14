<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('associazioni','lettura');
require_once __DIR__.'/includes/anagrafica_scheda.php';
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:0;
$message='';$entity=null;$tipologie=[];$runts=['status'=>'MISSING','record'=>null];$cesvot=['Mail'=>[],'Telefono'=>[]];$runtsResults=[];
$can=function(string $action,array $in=[])use($user):bool{foreach(scheda_needed_panels($action,$in) as $panel)if(!allowed($user,$panel,'scrittura'))return false;return true;};
try{
 $p=poimanager_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=(string)($_POST['action']??'');
  foreach(scheda_needed_panels($action,$_POST) as $panel)require_access($panel,'scrittura');
  check_csrf();
  scheda_mutate($p,$id,(string)($_POST['revision']??''),$action,$_POST);
  if($action==='entity_delete'){header('Location: /anagrafica.php?eliminato=1',true,303);exit;}
  header('Location: /anagrafica_singola.php?id='.$id.'&salvato=1',true,303);exit;
 }
}catch(Throwable $e){
 http_response_code($e instanceof PDOException?409:400);
 $message=$e instanceof PDOException?(($e->errorInfo[1]??0)===1062?'Dato già presente: nessuna modifica salvata.':'Operazione annullata. Verificare i collegamenti e i vincoli del database.'):$e->getMessage();
}
try{
 if(isset($p)&&$id>0){
  $entity=enti_detail($p,$id);
  if($entity){
   $tipologie=enti_tipologie($p);$runts=scheda_runts($p,$entity);$cesvot=scheda_cesvot($p,$entity);
   if($can('runts_align')&&is_string($_GET['runts_q']??null))$runtsResults=enti_runts_search($p,$_GET['runts_q']);
  }
 }
}catch(Throwable $e){http_response_code(503);$entity=null;$message=$e instanceof PDOException?'Scheda temporaneamente non disponibile.':$e->getMessage();}
if(!$entity&&$message==='')http_response_code(404);
page_start('Scheda anagrafica','Gestisci dati dell’ente, persone, email e telefoni; confronta RUNTS e recapiti Cesvot.',['Anagrafiche','Enti','Scheda']);
?>
<link rel="stylesheet" href="/assets/anagrafica.css"><link rel="stylesheet" href="/assets/anagrafica_scheda.css">
<p><a href="/anagrafica.php">← Torna all’anagrafica</a> · <a href="/dashboard_anagrafica.php">Gestione anagrafica</a></p>
<div id="scheda-feedback" aria-live="polite"><?php if($message):?><aside class="error" role="alert"><?=uh($message)?></aside><?php elseif(isset($_GET['salvato'])||isset($_GET['creato'])):?><aside role="status" data-save-ok>Ente salvato correttamente. Operazione completata.</aside><?php endif;?></div>
<?php if(!$entity):?><p><?= $message===''?'Ente non trovato.':'Nessun dato modificabile disponibile.' ?></p></main></body></html><?php exit;endif;
$revision=scheda_revision($entity);$etsIds=[];foreach($tipologie as $t)if(enti_is_ets($t['Tipologia']))$etsIds[]=(int)$t['ID'];
$openForm=function(string $action,array $extra=[],string $attributes='')use($id,$revision):void{
 echo '<form method="post" action="/anagrafica_singola.php?id='.$id.'" class="scheda-form" '.$attributes.'><input type="hidden" name="csrf" value="'.uh(csrf()).'"><input type="hidden" name="revision" value="'.uh($revision).'"><input type="hidden" name="action" value="'.uh($action).'">';
 foreach($extra as $key=>$value)echo '<input type="hidden" name="'.uh($key).'" value="'.uh($value).'">';
};
$ownerNames=['ente'=>'Ente'];foreach($entity['Persone'] as $person)$ownerNames['persona:'.$person['ID']]=$person['Nome'].' ('.$person['Tipo'].')';
?>
<div class="scheda-grid">
<section class="scheda-card scheda-main"><h2>Dati principali</h2>
<?php $openForm('entity_save',[],'id="scheda-main-form" data-ets="'.uh(json_encode($etsIds)).'"'); ?>
<fieldset <?=$can('entity_save')?'':'disabled'?>>
<label>Denominazione / Ragione sociale<input name="SOGGETTO" required maxlength="768" value="<?=uh($entity['SOGGETTO'])?>"></label>
<label>Codice fiscale / P.IVA<input name="CODICEFISCALE" maxlength="16" value="<?=uh($entity['CODICEFISCALE']??'')?>"></label>
<label>Tipologia ente<select name="ID_TIPOLOGIA" id="scheda-tipologia"><?php foreach($tipologie as $t):?><option value="<?=(int)$t['ID']?>" <?=(int)$entity['ID_TIPOLOGIA']===(int)$t['ID']?'selected':''?>><?=uh($t['Tipologia'])?></option><?php endforeach;?></select></label>
<div id="scheda-incarichi" <?=in_array((int)$entity['ID_TIPOLOGIA'],$etsIds,true)?'':'hidden'?>>
<label><input type="checkbox" name="TAVOLO" value="1" <?=(int)$entity['TAVOLO']?'checked':''?>> Membro Tavolo</label>
<label><input type="checkbox" name="DIRETTIVO_DELEGAZIONE" value="1" <?=(int)$entity['DIRETTIVO_DELEGAZIONE']?'checked':''?>> Membro Direttivo Delegazione</label></div>
<?php if($can('entity_save')):?><button id="scheda-save-main">Salva dati principali</button><?php endif;?></fieldset></form>
<p class="scheda-hint">I dati principali si salvano con questo pulsante. Persone e recapiti hanno salvataggi indipendenti.</p>
</section>
<section class="scheda-card"><h2>Collegamenti presenti</h2><p>Le persone appartengono a questo ente. Ogni email e telefono indica il proprio titolare.</p><p>Le eliminazioni di recapiti rimuovono anche le relative iscrizioni alle liste email. Le copie storiche delle campagne restano conservate.</p><p>Gli enti presenti in Agorà o collegati dal registro RUNTS non possono essere eliminati.</p></section>
</div>
<div class="scheda-live"><?php require __DIR__.'/includes/anagrafica_scheda_sezioni.php';?></div>
<script src="/assets/anagrafica_scheda.js" defer></script></main></body></html>
