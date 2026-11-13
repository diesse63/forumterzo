<dialog id="ente-dialog" class="ente-dialog" aria-labelledby="ente-dialog-title" <?=$open?'open':''?>>
<div class="ente-dialog-heading"><h2 id="ente-dialog-title">Nuovo ente</h2><a href="<?=uh(enti_query($params))?>" class="ente-close" aria-label="Chiudi nuovo ente">×</a></div>
<div class="ente-dialog-body">
<section class="runts-box" aria-labelledby="runts-title"><h3 id="runts-title">Importa da RUNTS</h3><p>Cerca per denominazione o nome della persona. La scelta precompila i campi senza salvare.</p>
<form method="get" id="runts-search"><input type="hidden" name="nuovo" value="1"><label>Cerca nel RUNTS<input type="search" name="runts_q" id="runts-query" value="<?=uh($_GET['runts_q']??'')?>" minlength="3" maxlength="200" autocomplete="off" placeholder="Almeno 3 caratteri"></label><button>Cerca RUNTS</button></form>
<p id="runts-status" role="status" aria-live="polite"></p>
<div id="runts-results" class="runts-results">
<?php foreach($runtsResults as $r):?><a class="runts-result" href="<?=uh(enti_query($params,['nuovo'=>1,'runts_cf'=>$r['CodiceFiscale']]))?>"><strong><?=uh($r['Denominazione'])?></strong><small><?=uh($r['CodiceFiscale'])?> · <?=uh($r['Provincia']??'')?></small><span><?=uh(trim(($r['Cognome']??'').' '.($r['Nome']??'')))?></span></a><?php endforeach;?>
<?php if(isset($_GET['runts_q'])&&!$runtsResults):?><p>Nessun risultato. Se RUNTS non è popolata, procedere con l’inserimento manuale.</p><?php endif;?></div></section>
<form method="post" id="ente-new-form">
<input type="hidden" name="csrf" value="<?=uh(csrf())?>">
<input type="hidden" name="runts_cf" id="ente-runts-cf" value="<?=uh($values['runts_cf']??'')?>">
<div class="ente-form-grid">
<label class="ente-wide">Denominazione ente *<input id="ente-name" name="SOGGETTO" required value="<?=uh($values['SOGGETTO']??'')?>" autocomplete="organization"></label>
<label>Codice fiscale<input id="ente-cf" name="CODICEFISCALE" maxlength="16" value="<?=uh($values['CODICEFISCALE']??'')?>"></label>
<label>Tipologia *<select id="ente-type" name="ID_TIPOLOGIA" required><option value="">Seleziona</option><?php foreach($tipologie as $t):?><option value="<?=(int)$t['ID']?>" data-ets="<?=enti_is_ets($t['Tipologia'])?'1':'0'?>" <?=(int)($values['ID_TIPOLOGIA']??0)===(int)$t['ID']?'selected':''?>><?=uh($t['Tipologia'])?></option><?php endforeach;?></select></label>
</div>
<?php $formEts=false;foreach($tipologie as $t)if((int)$t['ID']===(int)($values['ID_TIPOLOGIA']??0))$formEts=enti_is_ets($t['Tipologia']); ?>
<fieldset id="ente-incarichi" <?=$formEts?'':'hidden disabled'?>><legend>Incarichi associativi</legend>
<label><input type="checkbox" name="TAVOLO" <?=isset($values['TAVOLO'])?'checked':''?>> Membro del Tavolo</label>
<label><input type="checkbox" name="DIRETTIVO_DELEGAZIONE" <?=isset($values['DIRETTIVO_DELEGAZIONE'])?'checked':''?>> Direttivo Delegazione</label></fieldset>
<section id="ente-runts-person" <?=empty($values['runts_cf'])?'hidden':''?>>
<p id="ente-person-info"><?php if($selectedRunts):?>Persona proposta: <?=uh(trim(($selectedRunts['Cognome']??'').' '.($selectedRunts['Nome']??'')))?><?php else:?>Persona rilevata nel record RUNTS selezionato.<?php endif;?></p>
<label><input type="checkbox" name="import_person" id="ente-import-person" <?=isset($values['import_person'])?'checked':''?>> Crea anche la persona collegata</label>
<label>Ruolo della persona<select name="person_type" id="ente-person-type"><option value="">Seleziona un ruolo</option><option value="LR" <?=($values['person_type']??'')==='LR'?'selected':''?>>Legale rappresentante</option><option value="AS" <?=($values['person_type']??'')==='AS'?'selected':''?>>Altro soggetto</option></select></label>
<p>Il nominativo non viene considerato automaticamente legale rappresentante.</p></section>
<div class="ente-form-actions"><a href="<?=uh(enti_query($params))?>">Annulla</a><button>Salva ente</button></div>
</form></div></dialog>
