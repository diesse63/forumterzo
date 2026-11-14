<?php
declare(strict_types=1);
if(!isset($personType)||!in_array($personType,['LR','AS'],true)){http_response_code(403);exit;}
require_once __DIR__.'/auth.php';
$user=require_access('associazioni','lettura');require_access('soggetti','lettura');
require_once __DIR__.'/anagrafica.php';
$query=is_string($_GET['q']??null)?trim($_GET['q']):'';$rows=[];$entities=[];$error='';
try{
 if(strlen($query)>200)throw new RuntimeException('Ricerca troppo lunga.');
 $p=poimanager_db();foreach(dc_iter($p,'Associazioni') as $r)$entities[$r['ID']]=$r['SOGGETTO'];
 $mails=[];foreach(dc_iter($p,'Mail') as $r)if($r['TipoSoggetto']===($personType==='LR'?'REFERENTE':'ALTRO'))$mails[$r['IDSoggetto']][]=$r['Indirizzo'];
 foreach(dc_iter($p,'Soggetti') as $r)if($r['Tipo']===$personType){$r['Ente']=$entities[$r['ID_Associazione']]??'';$r['Emails']=$mails[$r['ID']]??[];if($query===''||dc_contains($r['Nome'].' '.$r['Ente'].' '.implode(' ',$r['Emails']),$query))$rows[]=$r;}
 usort($rows,fn($a,$b)=>strnatcasecmp($a['Nome'],$b['Nome'])?:((int)$a['ID']<=>(int)$b['ID']));
}catch(Throwable $e){http_response_code(503);$rows=[];$entities=[];$error=$e instanceof PDOException?'Archivio temporaneamente non disponibile.':$e->getMessage();}
$title=$personType==='LR'?'Referenti':'Altri soggetti';$total=count($rows);$pages=max(1,(int)ceil($total/50));$page=max(1,min($pages,(int)($_GET['pagina']??1)));$rows=array_slice($rows,($page-1)*50,50);
page_start($title,'Cerca le persone e apri la scheda dell’ente per gestire nominativi e recapiti.',['Anagrafiche',$title]);
?>
<link rel="stylesheet" href="/assets/anagrafica.css"><link rel="stylesheet" href="/assets/anagrafica_scheda.css">
<p><a href="/dashboard_anagrafica.php">← Gestione anagrafica</a></p>
<?php if($error):?><aside role="alert"><?=uh($error)?></aside><?php endif;?>
<form method="get" class="enti-filters"><label>Cerca nome, ente o email<input name="q" maxlength="200" value="<?=uh($query)?>"></label><button>Cerca</button></form>
<p>Risultati: <?=$total?></p><div class="enti-table-wrap"><table><thead><tr><th>Nome</th><th>Email</th><th>Ente</th><th>Azione</th></tr></thead><tbody>
<?php foreach($rows as $r):?><tr><td><?=uh($r['Nome'])?></td><td><?=uh(implode(', ',$r['Emails']))?></td><td><?=uh($r['Ente'])?></td><td><a href="/anagrafica_singola.php?id=<?=(int)$r['ID_Associazione']?>#persone-<?=uh($personType)?>">Apri scheda ente</a></td></tr><?php endforeach;if(!$rows):?><tr><td colspan="4">Nessuna persona trovata.</td></tr><?php endif;?></tbody></table></div>
<nav class="enti-pagination" aria-label="Paginazione persone"><?php if($page>1):?><a href="?<?=uh(http_build_query(['q'=>$query,'pagina'=>$page-1]))?>">« Precedente</a><?php else:?><span>« Precedente</span><?php endif;?><span>Pagina <?=$page?> di <?=$pages?></span><?php if($page<$pages):?><a href="?<?=uh(http_build_query(['q'=>$query,'pagina'=>$page+1]))?>">Successiva »</a><?php else:?><span>Successiva »</span><?php endif;?></nav>
<?php if(allowed($user,'soggetti','scrittura')):?>
<details class="scheda-card"><summary>Aggiungi una persona a un ente</summary><p>Seleziona l’ente, poi usa “Aggiungi persona” nella scheda.</p><form method="get" action="/anagrafica_singola.php"><label>Ente<select name="id" required><option value="">Seleziona</option><?php asort($entities,SORT_NATURAL|SORT_FLAG_CASE);foreach($entities as $entityId=>$name):?><option value="<?=(int)$entityId?>"><?=uh($name)?></option><?php endforeach;?></select></label><button>Apri ente</button></form></details><?php endif;?></main></body></html>
