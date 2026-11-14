<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('associazioni','lettura');
require_once __DIR__.'/includes/anagrafica_cards.php';
$write=allowed($user,'associazioni','scrittura');
$params=['q'=>is_string($_GET['q']??'')?($_GET['q']??''):'','tipo'=>max(0,(int)($_GET['tipo']??0)),'pagina'=>max(1,(int)($_GET['pagina']??1))];
$values=[];$message='';$tipologie=[];$runtsResults=[];$selectedRunts=null;$open=$write&&isset($_GET['nuovo']);$data=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
try{
 $p=poimanager_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  require_access('associazioni','scrittura');check_csrf();$values=$_POST;$open=true;
  $id=enti_create($p,$_POST);header('Location: /anagrafica_singola.php?id='.$id.'&creato=1');exit;
 }
 $tipologie=enti_tipologie($p);$data=enti_cards_data($p,enti_list($p,$params['q'],$params['tipo'],$params['pagina']));$params['pagina']=$data['page'];
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
  try{$tipologie=enti_tipologie($p);$data=enti_cards_data($p,enti_list($p,$params['q'],$params['tipo'],$params['pagina']));}catch(Throwable $ignored){}
 }
}
page_start('Anagrafica enti','Consulta gli enti, cerca le persone collegate e inserisci nuove anagrafiche.',['Anagrafiche','Anagrafica']);
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
<section id="enti-results" data-page="<?=(int)$data['page']?>" aria-live="polite"><p class="enti-count">Totale risultati: <strong><?=(int)$data['total']?></strong></p>
<?php require __DIR__.'/includes/anagrafica_cards_view.php';?>
<nav class="enti-pagination" aria-label="Paginazione enti">
<?php if($data['page']>1):?><a href="<?=uh(enti_query($params,['pagina'=>$data['page']-1]))?>">« Precedente</a><?php else:?><span aria-disabled="true">« Precedente</span><?php endif;?>
<span>Pagina <?=(int)$data['page']?> di <?=(int)$data['pages']?></span>
<?php if($data['page']<$data['pages']):?><a href="<?=uh(enti_query($params,['pagina'=>$data['page']+1]))?>">Successiva »</a><?php else:?><span aria-disabled="true">Successiva »</span><?php endif;?></nav>
</section>
<?php if($write): require __DIR__.'/includes/ente_nuovo.php';endif;?>
<dialog id="enti-manage-dialog" class="enti-manage-dialog" aria-labelledby="enti-manage-title"><header><h2 id="enti-manage-title">Gestione ente</h2><button type="button" id="enti-manage-close" aria-label="Chiudi gestione">×</button></header><p id="enti-manage-status" role="status"></p><iframe id="enti-manage-frame" title="Gestione anagrafica" src="about:blank"></iframe></dialog>
<script src="/assets/anagrafica_modali.js" defer></script>
<script src="/assets/anagrafica.js" defer></script></main></body></html>

