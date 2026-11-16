<?php
if(!isset($campaigns,$write)){http_response_code(403);exit;}
$aq=is_string($_GET['archivio_q']??null)?trim($_GET['archivio_q']):'';
$ast=is_string($_GET['stato']??null)?$_GET['stato']:'';
$archive=array_values(array_filter($campaigns,fn($r)=>($aq===''||dc_contains($r['Oggetto'],$aq))&&($ast===''||$r['StatoEtichetta']===$ast)));
usort($archive,fn($a,$b)=>strcmp((string)($b['CreatoIl']??''),(string)($a['CreatoIl']??''))?:((int)$b['ID']<=>(int)$a['ID']));
$stateCounts=array_fill_keys(['Bozza','Elaborata','Parzialmente inviata','Inviata','Da verificare'],0);
foreach($archive as $campaign)$stateCounts[$campaign['StatoEtichetta']]++;
$pending=array_values(array_filter($archive,fn($r)=>$r['StatoEtichetta']!=='Inviata'));
$selected=null;$selectedId=(int)($_GET['id']??0);
foreach($archive as $campaign)if((int)$campaign['ID']===$selectedId){$selected=$campaign;break;}
if(!$selected)$selected=$archive[0]??null;
$pages=max(1,(int)ceil(count($archive)/20));
$defaultPage=$selected?intdiv((int)(array_search($selected,$archive,true)?:0),20)+1:1;
$page=max(1,min($pages,(int)($_GET['archivio_pagina']??$defaultPage)));
$visible=array_slice($archive,($page-1)*20,20);
?>
<section class="email-card email-archive-filter">
<form method="get" class="email-row">
<input type="hidden" name="tab" value="registro">
<label>Cerca oggetto<input name="archivio_q" value="<?=em_h($aq)?>" maxlength="250"></label>
<label>Stato<select name="stato"><option value="">Tutti</option><?php foreach(['Bozza','Elaborata','Parzialmente inviata','Inviata','Da verificare'] as $st):?><option value="<?=em_h($st)?>" <?=$st===$ast?'selected':''?>><?=em_h($st)?></option><?php endforeach;?></select></label>
<button>Cerca mail</button><a class="email-view-message" href="/invio_email.php?tab=componi">Nuovo messaggio</a>
</form>
<p class="email-archive-status" role="status">Stati presenti: <?php $parts=[];foreach($stateCounts as $state=>$count)if($count)$parts[]=em_h($state).' ('.(int)$count.')';echo $parts?implode(' · ',$parts):'nessuna mail salvata';?>. <?php if($pending):?>Sono presenti <?=count($pending)?> mail in uno stato diverso da “Inviata”.<?php else:?>Tutte le mail sono in stato “Inviata”.<?php endif;?></p>
</section>
<?php if($selected):
 $prepared=$selected['Stato']==='preparato';
 $config=$prepared?em_config($p,(int)$selected['ConfigID']):null;
 $canPrepare=$write&&!$prepared;
 $canSend=$write&&$prepared&&(int)$selected['Inviate']<(int)$selected['Destinatari']&&$config&&em_campaign_sendable($p,(int)$selected['ID'],$config);
 $canResults=$selected['StatoEtichetta']==='Inviata'&&!empty($selected['ConfermaLettura']);
 $nextAction=['Bozza'=>'Prepara','Elaborata'=>'Invia','Parzialmente inviata'=>'Invia','Da verificare'=>'Verifica esiti'][$selected['StatoEtichetta']]??null;
?>
<section class="email-card email-archive-detail">
<h2>#<?=(int)$selected['ID']?> · <?=em_h($selected['Oggetto']!==''?$selected['Oggetto']:'(Senza oggetto)')?> <small class="email-card-status">Stato: <?=em_h($selected['StatoEtichetta'])?><?php if($nextAction):?> · prossima azione <?=em_h($nextAction)?><?php endif;?></small></h2>
<p class="email-archive-date"><?=em_h($selected['UltimoInvio']??$selected['CreatoIl']??'Data non disponibile')?></p>
<p><strong><?=em_h($selected['StatoEtichetta'])?></strong> · Destinatari <?=(int)$selected['Destinatari']?> · Inviate <?=(int)$selected['Inviate']?> · Da verificare <?=(int)$selected['Incerte']?> · Conferma ricezione: <strong><?=!empty($selected['ConfermaLettura'])?'Attiva':'Non attiva'?></strong></p>
<div class="email-archive-actions">
<?php if($canPrepare):?><form method="post" data-confirm-action="Preparare la lista per l’invio? Saranno congelati testo e destinatari."><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="prepare"><input type="hidden" name="id" value="<?=(int)$selected['ID']?>"><input type="hidden" name="confirm" value="1"><button type="submit">Prepara</button></form><?php endif;?>
<?php if($write&&$prepared):?><form method="post"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="send"><input type="hidden" name="id" value="<?=(int)$selected['ID']?>"><input type="hidden" name="send_nonce" value="<?=em_h(em_send_nonce())?>"><input type="hidden" name="confirm" value="1"><button type="submit" <?=$canSend?'':'disabled title="Tutti i destinatari sono già stati inviati o la campagna non è pronta."'?>>Invia</button></form><button type="button" class="email-results-button" <?=$canResults?'':'disabled'?>>Elenco esiti</button><?php endif;?>
<?php if($write):?><form method="post" data-confirm-action="Duplicare questa lista?&#10;&#10;ID: <?=(int)$selected['ID']?>&#10;Data: <?=em_h($selected['CreatoIl']??'Data non disponibile')?>&#10;Oggetto: <?=em_h($selected['Oggetto']!==''?$selected['Oggetto']:'(Senza oggetto)')?>&#10;Stato: <?=em_h($selected['StatoEtichetta'])?>" data-prompt-subject data-confirm-copy-recipients><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?=(int)$selected['ID']?>"><input type="hidden" name="duplicate_subject" value=""><input type="hidden" name="copy_recipients" value="0"><input type="hidden" name="return_tab" value="registro"><button type="submit">Duplica</button></form><form method="post" data-confirm-action="Eliminare definitivamente questa lista?&#10;&#10;ID: <?=(int)$selected['ID']?>&#10;Data: <?=em_h($selected['CreatoIl']??'Data non disponibile')?>&#10;Oggetto: <?=em_h($selected['Oggetto']!==''?$selected['Oggetto']:'(Senza oggetto)')?>&#10;Stato: <?=em_h($selected['StatoEtichetta'])?>&#10;&#10;Saranno eliminati anche destinatari e registro."><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$selected['ID']?>"><input type="hidden" name="version" value="<?=(int)$selected['Versione']?>"><input type="hidden" name="confirm" value="1"><button type="submit" class="email-danger-button">Elimina</button></form><?php endif;?>
<a class="email-view-message" href="/invio_email.php?tab=componi&amp;id=<?=(int)$selected['ID']?>">Visualizza messaggio</a>
</div>
</section>
<?php endif;?>
<section class="email-card email-archive-list"><h2>Liste di invio</h2>
<?php if($visible):?><div class="email-campaign-list" role="list"><?php foreach($visible as $m):?><a id="lista-invio-<?=(int)$m['ID']?>" role="listitem" class="email-campaign-row<?=$selected&&(int)$selected['ID']===(int)$m['ID']?' is-selected':''?>" href="/invio_email.php?tab=registro&amp;archivio_q=<?=urlencode($aq)?>&amp;stato=<?=urlencode($ast)?>&amp;id=<?=(int)$m['ID']?>&amp;archivio_pagina=<?=$page?>"><span><?=em_h($m['UltimoInvio']??$m['CreatoIl']??'Data non disponibile')?></span><strong><?=em_h($m['Oggetto']!==''?$m['Oggetto']:'(Senza oggetto)')?></strong><em><?=!empty($m['ConfermaLettura'])?'Conferma attiva':'Mail normale'?></em><em><?=em_h($m['StatoEtichetta'])?></em></a><?php endforeach;?></div><?php else:?><p>Nessuna mail trovata.</p><?php endif;?>
<nav class="email-row" aria-label="Paginazione archivio mail"><?php if($page>1):?><a href="?<?=em_h(http_build_query(['tab'=>'registro','archivio_q'=>$aq,'stato'=>$ast,'archivio_pagina'=>$page-1]))?>">« Precedente</a><?php endif;?><span>Pagina <?=$page?> di <?=$pages?></span><?php if($page<$pages):?><a href="?<?=em_h(http_build_query(['tab'=>'registro','archivio_q'=>$aq,'stato'=>$ast,'archivio_pagina'=>$page+1]))?>">Successiva »</a><?php endif;?></nav>
</section>
<?php if($selected&&!isset($_GET['id'])):?><div id="email-archive-auto-scroll" data-scroll-to="lista-invio-<?=(int)$selected['ID']?>" hidden></div><?php endif;?>
