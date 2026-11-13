<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/auth.php';
require_access('importazione','scrittura');
require_once __DIR__.'/tabelle.php';
require_once __DIR__.'/deposito.php';
$map=table_mapping();$message='';$error=false;$report=null;
try{
 $cfg=poimanager_config();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$action=(string)($_POST['action']??'');
  if($action==='remove'){
   if(isset($_SESSION['upload']['path']))@unlink($_SESSION['upload']['path']);
   unset($_SESSION['upload']);$message='File temporaneo eliminato.';
  }elseif($action==='upload'){
   $dir=import_private_dir($cfg['private_dir']);
   if(isset($_SESSION['upload']['path']))@unlink($_SESSION['upload']['path']);
   unset($_SESSION['upload']);
   $u=$_FILES['sqlite']??null;
   if(!$u||$u['error']!==UPLOAD_ERR_OK||$u['size']>$cfg['max_bytes'])throw new RuntimeException('Caricamento fallito o file troppo grande.');
   $path=$dir.'/sqlite_'.bin2hex(random_bytes(20)).'.db';
   if(!move_uploaded_file($u['tmp_name'],$path))throw new RuntimeException('Impossibile acquisire il file.');
   chmod($path,0600);
   try{$s=table_source($path);$inventory=table_inventory($s,$map);$s=null;}
   catch(Throwable $e){@unlink($path);throw $e;}
   $_SESSION['upload']=['path'=>$path,'sha256'=>hash_file('sha256',$path),'map'=>hash_file('sha256',__DIR__.'/mappa_v3.json'),'time'=>time(),'inventory'=>$inventory];
   $message='File letto. Selezionare le tabelle da aggiornare. Nessun dato importato.';
  }elseif(in_array($action,['preview','import'],true)){
   $u=$_SESSION['upload']??null;
   if(!$u||!isset($u['inventory'])||time()-$u['time']>1800||!is_file($u['path']))throw new RuntimeException('File assente o scaduto: caricarlo nuovamente.');
   if(!hash_equals($u['sha256'],hash_file('sha256',$u['path']))||!hash_equals($u['map'],hash_file('sha256',__DIR__.'/mappa_v3.json')))throw new RuntimeException('File o mappa cambiati: ripetere il caricamento.');
   import_private_dir($cfg['private_dir']);$s=table_source($u['path']);$p=db($cfg);
   if($action==='preview'){
    unset($_SESSION['upload']['plan']);$selected=$_POST['tables']??[];
    if(!is_array($selected))throw new RuntimeException('Selezione non valida.');
    $order=table_order($selected,$map,table_inventory($s,$map));
    $p->beginTransaction();
    try{$report=table_plan($p,$s,$map,$order);$fingerprint=table_fingerprint($p,$map,$order);}finally{$p->rollBack();}
    $_SESSION['upload']['plan']=['order'=>$order,'report'=>$report,'fingerprint'=>$fingerprint];
    $_SESSION['upload']['requested']=$selected;
    $message='Anteprima senza scritture. Le dipendenze sono state incluse automaticamente: '.implode(' → ',$order).'.';
   }else{
    $plan=$u['plan']??null;if(!$plan)throw new RuntimeException('Preparare prima l’anteprima.');
    $order=table_order($plan['order'],$map,table_inventory($s,$map));
    $report=table_import($p,$s,$map,$order,$plan['report'],$plan['fingerprint']);
    unset($_SESSION['upload']['plan']);
    $dir=dirname($u['path']);
    $logged=@file_put_contents($dir.'/importazioni.jsonl',json_encode(['date'=>gmdate('c'),'mode'=>'selected_tables_update','mapping'=>$map['version'],'source_sha256'=>$u['sha256'],'order'=>$order,'counts'=>$report,'outcome'=>'COMMIT'],JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX)!==false;
    $message='Importazione completata: nuovi record inseriti, esistenti aggiornati. Nessuna cancellazione.'.($logged?'':' Registro su file non salvato.');
   }
   $s=null;
  }else throw new RuntimeException('Operazione non valida.');
 }
}catch(Throwable $e){
 if(isset($p)&&$p->inTransaction())$p->rollBack();
 $error=true;
 $message=$e instanceof PDOException?'Operazione bloccata da un vincolo o errore database (SQLSTATE '.$e->getCode().'). Tutte le modifiche di questo import sono annullate. Verificare chiavi univoche e riferimenti.':$e->getMessage();
}
// Aggiorna anche gli inventari salvati in sessione con la vecchia interfaccia.
if(isset($_SESSION['upload']['inventory'])&&is_file($_SESSION['upload']['path']??'')){
 try{$inventorySource=table_source($_SESSION['upload']['path']);$_SESSION['upload']['inventory']=table_inventory($inventorySource,$map);$inventorySource=null;}
 catch(Throwable $inventoryError){$error=true;$message='Impossibile aggiornare l’elenco delle tabelle: ricaricare il file SQLite.';}
}
page_start('Importazione SQLite','Seleziona le tabelle, verifica l’anteprima e inserisci o aggiorna i dati rispettando i vincoli.',['Servizi','Importazione SQLite']);
if($message)echo '<aside'.($error?' class="error"':'').'>'.uh($message).'</aside>';
?>
<p>Mappatura <?=uh($map['version'])?>. Eseguire un backup e sospendere le altre modifiche ai dati prima di importare. Gli utenti e i permessi del sito restano invariati.</p>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="upload">
<label>Copia SQLite aggiornata<input type="file" name="sqlite" accept=".db,.sqlite,.sqlite3" required></label>
<button>1. Leggi file e mostra tabelle</button>
</form>
<?php $upload=$_SESSION['upload']??null;if(isset($upload['inventory'])): ?>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="preview">
<table><tr><th>Importa</th><th>Tabella</th><th>Righe SQLite</th><th>Disponibilità</th><th>Collegamenti in uscita</th><th>Collegamenti in ingresso</th></tr>
<?php foreach($upload['inventory'] as $t=>$entry): ?>
<tr><td><input type="checkbox" name="tables[]" value="<?=uh($t)?>" aria-label="Importa <?=uh($t)?>" <?=$entry['enabled']?'':'disabled'?> <?=in_array($t,$upload['requested']??[],true)?'checked':''?>></td><td><?=uh($t)?></td><td><?=$entry['count']===null?'—':(int)$entry['count']?></td><td><?=uh($entry['reason']?:'Presente nel file · Inserimento e aggiornamento')?></td>
<td><?php if($entry['outgoing']):?><ul><?php foreach($entry['outgoing'] as $relation):?><li><?=uh($relation)?></li><?php endforeach;?></ul><?php else:?>Nessun collegamento dichiarato<?php endif;?></td>
<td><?php if($entry['incoming']):?><ul><?php foreach($entry['incoming'] as $relation):?><li><?=uh($relation)?></li><?php endforeach;?></ul><?php else:?>Nessun collegamento dichiarato<?php endif;?></td></tr>
<?php endforeach;?></table>
<p>L’elenco include le tabelle presenti in SQLite e tutte quelle previste dalla mappa MySQL. Una tabella assente, come Soggetti nella variante con Referenti e AltriSoggetti, è visibile ma non selezionabile. I collegamenti distinguono FK SQLite, FK MySQL previste e vincoli logici applicati. Le FK MySQL mostrate descrivono il contratto della mappa; lo schema reale viene verificato nell’anteprima.</p>
<p>Vengono aggiunte automaticamente le tabelle necessarie per i riferimenti, nell’ordine corretto. Le tabelle non mappate restano escluse; una dipendenza assente o incompatibile blocca la selezione.</p>
<button>2. Prepara anteprima delle tabelle selezionate</button></form>
<?php $rows=$report??($upload['plan']['report']??null);if($rows): ?>
<table><tr><th>Ordine / tabella</th><th>Nuovi</th><th>Aggiornati</th><th>Invariati</th></tr>
<?php foreach($rows as $t=>$counts):?><tr><td><?=uh($t)?></td><td><?=(int)$counts['nuovi']?></td><td><?=(int)$counts['aggiornati']?></td><td><?=(int)$counts['invariati']?></td></tr><?php endforeach;?></table>
<?php endif;if(isset($upload['plan'])): ?>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="import">
<p>Confermando verranno sovrascritti i valori dei record con la stessa chiave nelle tabelle elencate, comprese le dipendenze aggiunte.</p><button>3. Conferma inserimenti e aggiornamenti</button></form>
<?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="remove"><button>Elimina file temporaneo</button></form>
<?php endif; ?></main></body></html>
