<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';require_access('associazioni','lettura');
require_once __DIR__.'/includes/anagrafica.php';
try{$id=(int)($_GET['id']??0);$entity=$id>0?enti_detail(poimanager_db(),$id):null;}
catch(Throwable $e){http_response_code(503);$entity=null;$failed=true;}
if(!$entity&&!isset($failed))http_response_code(404);
page_start('Scheda ente','Consulta dati anagrafici, persone collegate e recapiti dell’ente.',['Anagrafiche','Enti','Scheda']);
echo '<link rel="stylesheet" href="/assets/anagrafica.css"><p><a href="/anagrafica.php">← Torna all’anagrafica</a></p>';
if(!$entity){echo '<aside>'.(isset($failed)?'Scheda temporaneamente non disponibile.':'Ente non trovato.').'</aside></main></body></html>';exit;}
if(isset($_GET['creato']))echo '<aside role="status">Ente salvato correttamente.</aside>';
?>
<h2><?=uh($entity['SOGGETTO'])?></h2>
<dl class="ente-detail"><dt>Codice fiscale</dt><dd><?=uh($entity['CODICEFISCALE']?:'—')?></dd><dt>Tipologia</dt><dd><?=uh($entity['Tipologia_Nome'])?></dd><dt>Tavolo</dt><dd><?=(int)$entity['TAVOLO']?'Sì':'No'?></dd><dt>Direttivo Delegazione</dt><dd><?=(int)$entity['DIRETTIVO_DELEGAZIONE']?'Sì':'No'?></dd></dl>
<h2>Persone collegate</h2>
<?php foreach($entity['Persone'] as $person):?><p><strong><?=uh($person['Nome'])?></strong> · <?=$person['Tipo']==='LR'?'Legale rappresentante':'Altro soggetto'?></p><?php endforeach;if(!$entity['Persone'])echo '<p>Nessuna persona collegata.</p>';?>
<?php $names=[];foreach($entity['Persone'] as $person)$names[$person['ID']]=$person['Nome'];
foreach(['Mail'=>'Email','Telefono'=>'Telefoni'] as $table=>$title):?>
<h2><?=uh($title)?></h2>
<?php foreach($entity[$table] as $contact):$owner=$contact['TipoSoggetto']==='ASSOCIAZIONE'?'Ente':($names[$contact['IDSoggetto']]??'Persona collegata'); ?>
<p><strong><?=uh($table==='Mail'?$contact['Indirizzo']:$contact['Telefono'])?></strong> · <?=uh($owner)?> <?php if($table==='Mail'&&(int)$contact['PEC'])echo '· PEC';if((int)$contact['Predefinito'])echo ' · Predefinito';?></p>
<?php endforeach;if(!$entity[$table])echo '<p>Nessun recapito registrato.</p>';endforeach;?>
<p>Questa scheda è in consultazione. La gestione completa di modifiche, recapiti e allineamenti sarà integrata nella fase dedicata alla scheda singola.</p>
</main></body></html>
