<?php if(!isset($data)){http_response_code(403);exit;} ?>
<div class="enti-cards">
<?php foreach($data['rows'] as $row):$entityId=(int)$row['ID'];$names=['ASSOCIAZIONE:'.$entityId=>'Ente'];foreach($row['Persone'] as $person)$names[($person['Tipo']==='LR'?'REFERENTE':'ALTRO').':'.$person['ID']]=$person['Nome'].' ('.$person['Tipo'].')';
$modalLink=function(string $mode,string $label)use($row,$entityId):void{echo '<a class="enti-manage" href="/anagrafica_singola.php?id='.$entityId.'&amp;modale='.uh($mode).'" data-card-modal="'.uh($mode).'" data-entity-id="'.$entityId.'" data-entity-name="'.uh($row['SOGGETTO']).'">'.uh($label).'</a>';}; ?>
<article class="enti-card" data-entity-id="<?=$entityId?>">
<header class="enti-card-header"><div><h2><?=uh($row['SOGGETTO'])?></h2><p class="enti-cf">Codice fiscale / P.IVA: <?=uh($row['CODICEFISCALE']?:'—')?></p><span class="enti-type"><?=uh($row['Tipologia_Nome'])?></span></div>
<div class="enti-card-actions"><?php $modalLink('dati','Dati ente');?><a class="enti-open" href="/anagrafica_singola.php?id=<?=$entityId?>">Apri scheda</a></div></header>
<p class="enti-incarichi">Tavolo: <strong><?=(int)$row['TAVOLO']?'Sì':'No'?></strong> · Direttivo: <strong><?=(int)$row['DIRETTIVO_DELEGAZIONE']?'Sì':'No'?></strong></p>
<?php if(trim((string)($row['COLONNA3']??''))!==''):?><p class="enti-extra"><?=nl2br(uh($row['COLONNA3']))?></p><?php endif;?>
<div class="enti-card-sections">
<?php foreach(['Mail'=>'Email / PEC','Telefono'=>'Recapiti telefonici'] as $table=>$label):?>
<section class="enti-card-section" data-card-section="<?=uh($table)?>"><header><h3><?=uh($label)?></h3><?php $modalLink($table,'Gestisci '.$label);?></header><ul>
<?php foreach($row[$table] as $r):?><li><strong><?=uh($r[$table==='Mail'?'Indirizzo':'Telefono'])?></strong><small><?=uh($names[$r['TipoSoggetto'].':'.$r['IDSoggetto']]??'Persona collegata')?><?php if($table==='Mail'&&(int)$r['PEC'])echo ' · PEC';if((int)$r['Predefinito'])echo ' · ★ Predefinito';?></small></li><?php endforeach;if(!$row[$table]):?><li class="enti-empty">Nessun recapito registrato.</li><?php endif;?></ul></section><?php endforeach;?>
<?php foreach(['LR'=>'Legali rappresentanti','AS'=>'Altri soggetti'] as $type=>$label):$people=array_filter($row['Persone'],fn($person)=>$person['Tipo']===$type);?>
<section class="enti-card-section" data-card-section="<?=uh($type)?>"><header><h3><?=uh($label)?></h3><?php $modalLink($type,'Gestisci '.$label);?></header><ul>
<?php foreach($people as $person):?><li><strong><?=uh($person['Nome'])?></strong><?php foreach(['Mail','Telefono'] as $table)foreach($row[$table] as $r)if($r['TipoSoggetto']===($type==='LR'?'REFERENTE':'ALTRO')&&(int)$r['IDSoggetto']===(int)$person['ID']):?><small><?=uh($r[$table==='Mail'?'Indirizzo':'Telefono'])?><?php if($table==='Mail'&&(int)$r['PEC'])echo ' · PEC';if((int)$r['Predefinito'])echo ' · ★';?></small><?php endif;?></li><?php endforeach;if(!$people):?><li class="enti-empty">Nessuna persona collegata.</li><?php endif;?></ul></section><?php endforeach;?>
</div>
<section class="enti-card-runts" data-card-section="runts"><header><h3>RUNTS</h3><?php $modalLink('runts','Confronto RUNTS');?></header><p><?=uh(['ON'=>'Allineato','OFF'=>'Differenze da verificare','MISSING'=>'Nessun record univoco individuato','AMBIGUO'=>'Più corrispondenze: selezione necessaria'][$row['Runts']['status']])?></p>
<?php if($r=$row['Runts']['record']):?><dl><dt>Denominazione</dt><dd><?=uh($r['Denominazione']??'')?></dd><dt>Codice fiscale</dt><dd><?=uh($r['CodiceFiscale']??'')?></dd><dt>Persona proposta</dt><dd><?=uh(trim(($r['Cognome']??'').' '.($r['Nome']??'')))?></dd><?php if(!empty($r['Provincia'])):?><dt>Provincia</dt><dd><?=uh($r['Provincia'])?></dd><?php endif;?></dl><?php endif;?></section>
</article><?php endforeach;if(!$data['rows']):?><p>Nessun ente trovato.</p><?php endif;?></div>
