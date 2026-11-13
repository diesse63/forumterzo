<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('associazioni','lettura');
require_once __DIR__.'/includes/anagrafica.php';
$write=allowed($user,'associazioni','scrittura');
$params=['q'=>is_string($_GET['q']??'')?($_GET['q']??''):'','tipo'=>max(0,(int)($_GET['tipo']??0)),'pagina'=>max(1,(int)($_GET['pagina']??1))];
$values=[];$message='';$tipologie=[];$runtsResults=[];$selectedRunts=null;$open=$write&&isset($_GET['nuovo']);$data=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
try{
 $p=poimanager_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_access('associazioni','scrittura');check_csrf();$values=$_POST;$open=true;
  $id=enti_create($p,$_POST);header('Location: /anagrafica_singola.php?id='.$id.'&creato=1');exit;
 }
 $tipologie=enti_tipologie($p);$data=enti_list($p,$params['q'],$params['tipo'],$params['pagina']);$params['pagina']=$data['page'];
 if($write&&$open){
  $term=is_string($_GET['runts_q']??'')?($_GET['runts_q']??''):'';
  if($term!=='')$runtsResults=enti_runts_search($p,$term);
  if(is_string($_GET['runts_cf']??null)){
   $selectedRunts=enti_runts_record($p,$_GET['runts_cf']);
   if($selectedRunts){
    $values=['SOGGETTO'=>$selectedRunts['Denominazione'],'CODICEFISCALE'=>$selectedRunts['CodiceFiscale'],'runts_cf'=>$selectedRunts['CodiceFiscale']];
    foreach($tipologie as $t)if(enti_is_ets($t['Tipologia'])){$values['ID_TIPOLOGIA']=$t['ID'];break;}
   }
  }
 }
}catch(Throwable $e){
 $message=$e instanceof PDOException?(($e->errorInfo[1]??0)===1062?'Denominazione già presente: aprire la scheda dell’ente esistente.':'Operazione non completata. Verificare disponibilità e vincoli del database.'):$e->getMessage();
 if(isset($p)){
  try{$tipologie=enti_tipologie($p);$data=enti_list($p,$params['q'],$params['tipo'],$params['pagina']);}catch(Throwable $ignored){}
 }
}
page_start('Anagrafica enti','Consulta gli enti, cerca le persone collegate e inserisci nuove anagrafiche.',['Anagrafiche','Enti']);
?>
<link rel="stylesheet" href="/assets/anagrafica.css">
<?php if($message):?><aside class="error" role="alert"><?=uh($message)?></aside><?php endif;?>
<div class="enti-toolbar">
<form method="get" class="enti-filters">
<label>Tipologia<select name="tipo"><option value="0">Tutte</option><?php foreach($tipologie as $t):?><option value="<?=(int)$t['ID']?>" <?=$params['tipo']===(int)$t['ID']?'selected':''?>><?=uh($t['Tipologia'])?></option><?php endforeach;?></select></label>
<label>Cerca ente, CF o persona<input type="search" name="q" maxlength="200" value="<?=uh($params['q'])?>" placeholder="Denominazione, codice fiscale, nome…"></label>
<button>Cerca</button><a class="enti-reset" href="/anagrafica.php">Azzera filtri</a>
</form>
<?php if($write):?><a class="enti-primary" href="<?=uh(enti_query($params,['nuovo'=>1]))?>">＋ Nuovo ente</a><?php endif;?>
</div>
<section id="enti-results" aria-live="polite"><p class="enti-count">Totale risultati: <strong><?=(int)$data['total']?></strong></p>
<div class="enti-table-wrap"><table class="enti-table"><thead><tr><th>Soggetto / ente</th><th>Tipologia</th><th>Legali rappresentanti e altri soggetti</th><th>Incarichi</th><th>Azioni</th></tr></thead><tbody>
<?php foreach($data['rows'] as $row):?>
<tr data-entity-url="/anagrafica_singola.php?id=<?=(int)$row['ID']?>">
<td><strong><?=uh($row['SOGGETTO'])?></strong><small class="enti-cf"><?=uh($row['CODICEFISCALE']??'')?></small></td>
<td><span class="enti-type"><?=uh($row['Tipologia_Nome'])?></span></td>
<td><?php foreach($row['Persone'] as $person):?><div class="enti-person"><span class="enti-role"><?= $person['Tipo']==='LR'?'LR':'AS' ?></span> <?=uh($person['Nome'])?></div><?php endforeach;if(!$row['Persone'])echo '—';?></td>
<td><?php if((int)$row['TAVOLO']):?><span class="enti-badge">Tavolo</span><?php endif;if((int)$row['DIRETTIVO_DELEGAZIONE']):?><span class="enti-badge enti-board">Direttivo</span><?php endif;?></td>
<td><a class="enti-open" href="/anagrafica_singola.php?id=<?=(int)$row['ID']?>">Apri <span class="sr-only"><?=uh($row['SOGGETTO'])?></span></a></td></tr>
<?php endforeach;if(!$data['rows']):?><tr><td colspan="5">Nessun ente trovato.</td></tr><?php endif;?></tbody></table></div>
<div class="enti-cards">
<?php foreach($data['rows'] as $row):?><article class="enti-card"><h2><?=uh($row['SOGGETTO'])?></h2><p class="enti-cf"><?=uh($row['CODICEFISCALE']??'')?></p><p><span class="enti-type"><?=uh($row['Tipologia_Nome'])?></span></p>
<?php foreach($row['Persone'] as $person):?><div class="enti-person"><span class="enti-role"><?=uh($person['Tipo'])?></span> <?=uh($person['Nome'])?></div><?php endforeach;?>
<p><?php if((int)$row['TAVOLO']):?><span class="enti-badge">Tavolo</span><?php endif;if((int)$row['DIRETTIVO_DELEGAZIONE']):?><span class="enti-badge enti-board">Direttivo</span><?php endif;?></p>
<a class="enti-open" href="/anagrafica_singola.php?id=<?=(int)$row['ID']?>">Apri scheda</a></article><?php endforeach;if(!$data['rows']):?><p>Nessun ente trovato.</p><?php endif;?></div>
<nav class="enti-pagination" aria-label="Paginazione enti">
<?php if($data['page']>1):?><a href="<?=uh(enti_query($params,['pagina'=>$data['page']-1]))?>">« Precedente</a><?php else:?><span aria-disabled="true">« Precedente</span><?php endif;?>
<span>Pagina <?=(int)$data['page']?> di <?=(int)$data['pages']?></span>
<?php if($data['page']<$data['pages']):?><a href="<?=uh(enti_query($params,['pagina'=>$data['page']+1]))?>">Successiva »</a><?php else:?><span aria-disabled="true">Successiva »</span><?php endif;?></nav>
</section>
<?php if($write): require __DIR__.'/includes/ente_nuovo.php';endif;?>
<script src="/assets/anagrafica.js" defer></script></main></body></html>
