<?php if(!isset($entity,$openForm,$can)){http_response_code(403);exit;} ?>
<div class="scheda-grid">
<?php foreach(['Mail'=>'Indirizzi email / PEC','Telefono'=>'Recapiti telefonici'] as $table=>$title):$save=$table==='Mail'?'mail_save':'phone_save';$delete=$table==='Mail'?'mail_delete':'phone_delete';$field=$table==='Mail'?'Indirizzo':'Telefono';?>
<section class="scheda-card" id="sezione-<?=uh(strtolower($table))?>"><h2><?=uh($title)?></h2><ul class="scheda-list">
<?php foreach($entity[$table] as $r):$owner=$r['TipoSoggetto']==='ASSOCIAZIONE'?'ente':'persona:'.$r['IDSoggetto'];?>
<li><strong><?=uh($r[$field])?></strong><small><?=uh($ownerNames[$owner]??'Persona collegata')?><?php if((int)$r['Predefinito'])echo ' · ★ Predefinito';if($table==='Mail'&&(int)$r['PEC'])echo ' · PEC';?></small>
<?php if($can($save)):?><details><summary>Modifica recapito</summary><?php $openForm($save,['contact_id'=>$r['ID'],'owner'=>$owner]); ?>
<label><?= $table==='Mail'?'Email':'Telefono' ?><input name="value" type="<?=$table==='Mail'?'email':'tel'?>" required maxlength="<?=$table==='Mail'?'254':'50'?>" value="<?=uh($r[$field])?>"></label>
<?php if($table==='Mail'):?><label><input type="checkbox" name="pec" value="1" <?=(int)$r['PEC']?'checked':''?>> PEC</label><?php endif;?>
<label><input type="checkbox" name="preferred" value="1" <?=(int)$r['Predefinito']?'checked':''?>> Predefinito per il titolare</label><button>Salva recapito</button></form></details><?php endif;?>
<?php if($can($delete)):$openForm($delete,['contact_id'=>$r['ID']],'data-confirm="Eliminare il recapito e le eventuali iscrizioni alle liste email?"');?><button class="scheda-danger">Elimina recapito</button></form><?php endif;?></li>
<?php endforeach;if(!$entity[$table]):?><li>Nessun recapito registrato.</li><?php endif;?></ul>
<?php if($can($save)):$openForm($save,[],'data-new-contact="'.uh($table).'"'); ?>
<h3>Aggiungi <?= $table==='Mail'?'email':'telefono' ?></h3><label>Titolare<select name="owner"><?php foreach($ownerNames as $key=>$label):?><option value="<?=uh($key)?>"><?=uh($label)?></option><?php endforeach;?></select></label>
<label><?= $table==='Mail'?'Indirizzo email':'Numero telefonico' ?><input name="value" type="<?=$table==='Mail'?'email':'tel'?>" required maxlength="<?=$table==='Mail'?'254':'50'?>"></label>
<?php if($table==='Mail'):?><label><input type="checkbox" name="pec" value="1"> PEC</label><?php endif;?><label><input type="checkbox" name="preferred" value="1"> Predefinito per il titolare</label><button>Aggiungi <?= $table==='Mail'?'email':'telefono' ?></button></form><?php endif;?>
<?php if($cesvot[$table]):?><details class="scheda-source"><summary>Cesvot: <?=count($cesvot[$table])?> recapiti mancanti</summary><p>Confronto per denominazione esatta. Seleziona i recapiti da aggiungere all’ente.</p>
<?php if($can('cesvot_import',['table'=>$table])):$openForm('cesvot_import',['table'=>$table],'data-confirm="Importare i recapiti Cesvot selezionati?"');foreach($cesvot[$table] as $candidate):?><label><input type="checkbox" name="source_ids[]" value="<?=(int)$candidate['ID']?>"> <?=uh($candidate['value'])?></label><?php endforeach;?><button>Importa recapiti selezionati</button></form><?php else:foreach($cesvot[$table] as $candidate):?><p><?=uh($candidate['value'])?></p><?php endforeach;endif;?></details><?php else:?><p class="scheda-hint">Cesvot: nessun recapito aggiuntivo rilevato. Il confronto richiede dati caricati in CesvotMail / CesvotTelefono.</p><?php endif;?>
</section><?php endforeach;?>
<?php foreach(['LR'=>'Legali rappresentanti','AS'=>'Altri soggetti'] as $type=>$label):?>
<section class="scheda-card" id="persone-<?=uh($type)?>"><h2><?=uh($label)?></h2><ul class="scheda-list">
<?php $people=array_filter($entity['Persone'],fn($r)=>$r['Tipo']===$type);foreach($people as $person):?>
<li><strong><?=uh($person['Nome'])?></strong><?php foreach($entity['Mail'] as $mail)if($mail['TipoSoggetto']===($type==='LR'?'REFERENTE':'ALTRO')&&(int)$mail['IDSoggetto']===(int)$person['ID']):?><small><?=uh($mail['Indirizzo'])?></small><?php endif;?>
<?php if($can('person_save')):?><details><summary>Modifica persona</summary><?php $openForm('person_save',['person_id'=>$person['ID'],'type'=>$type]);?><label>Nome e cognome<input name="name" required maxlength="768" value="<?=uh($person['Nome'])?>"></label><button>Salva persona</button></form></details><?php endif;?>
<?php if($can('person_delete')):$openForm('person_delete',['person_id'=>$person['ID']],'data-confirm="Eliminare la persona, i suoi recapiti e le relative iscrizioni alle liste?"');?><button class="scheda-danger">Elimina persona</button></form><?php endif;?></li>
<?php endforeach;if(!$people):?><li>Nessuna persona collegata.</li><?php endif;?></ul>
<?php if($can('person_save')):$openForm('person_save',['type'=>$type],'data-new-person="'.uh($type).'"');?><h3>Aggiungi persona</h3><label>Nome e cognome<input name="name" required maxlength="768"></label>
<?php if($can('person_save',['email'=>'abilita'])):?><label>Email personale (facoltativa)<input name="email" type="email" maxlength="254"></label><?php endif;?><button>Aggiungi persona</button></form><?php endif;?></section><?php endforeach;?>
</div>
<section class="scheda-card scheda-runts"><h2>Confronto RUNTS</h2>
<p class="scheda-runts-status"><?php echo uh(['ON'=>'Allineato con il record RUNTS individuato.','OFF'=>'Sono presenti differenze rispetto al record RUNTS.','MISSING'=>'Nessun record RUNTS univoco individuato.','AMBIGUO'=>'Più record corrispondenti: scegliere esplicitamente un record.'][$runts['status']]);?></p>
<?php if($can('runts_align')):?>
<form method="get" class="scheda-runts-search"><input type="hidden" name="id" value="<?=(int)$id?>"><label>Cerca nel RUNTS caricato<input name="runts_q" minlength="3" maxlength="200" value="<?=uh(is_string($_GET['runts_q']??null)?$_GET['runts_q']:'')?>"></label><button>Cerca RUNTS</button></form>
<?php $candidates=$runtsResults;if($runts['record'])array_unshift($candidates,$runts['record']);$seen=[];foreach($candidates as $candidate):$cf=(string)$candidate['CodiceFiscale'];if(isset($seen[$cf]))continue;$seen[$cf]=true;?>
<details class="scheda-source"><summary><?=uh($candidate['Denominazione']??'')?> · <?=uh($cf)?></summary>
<dl><dt>Denominazione attuale</dt><dd><?=uh($entity['SOGGETTO'])?></dd><dt>Denominazione RUNTS</dt><dd><?=uh($candidate['Denominazione']??'')?></dd><dt>CF attuale</dt><dd><?=uh($entity['CODICEFISCALE']??'')?></dd><dt>CF RUNTS</dt><dd><?=uh($cf)?></dd><dt>Persona proposta</dt><dd><?=uh(trim(($candidate['Cognome']??'').' '.($candidate['Nome']??'')))?></dd></dl>
<?php $openForm('runts_align',['runts_cf'=>$cf],'data-confirm="Confermi la sostituzione di denominazione e codice fiscale con i dati RUNTS mostrati?"'); ?>
<?php if($can('runts_align',['import_person'=>'1'])):?><label><input type="checkbox" name="import_person" value="1"> Aggiungi la persona proposta, senza sostituire le persone esistenti</label><label>Ruolo da assegnare<select name="person_type"><option value="">Scegli esplicitamente</option><option value="LR">Legale rappresentante</option><option value="AS">Altro soggetto</option></select></label><?php endif;?><button>Allinea ai dati RUNTS mostrati</button></form></details>
<?php endforeach;if(!$candidates):?><p>Nessun risultato. La ricerca utilizza esclusivamente la tabella RUNTS già caricata, senza interrogare siti esterni.</p><?php endif;endif;?></section>
<div class="scheda-actions"><a href="/anagrafica.php">Chiudi scheda</a><?php if($can('entity_delete')):$openForm('entity_delete',[],'data-confirm="Eliminare definitivamente l’ente, le persone e i recapiti collegati? Gli eventuali vincoli possono impedire l’operazione."');?><button class="scheda-danger">Elimina ente</button></form><?php endif;?></div>
