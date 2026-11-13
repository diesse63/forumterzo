<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
require_access('importazione','scrittura');
require_once __DIR__.'/importazione_sqlite/check_schema.php';
$map=table_mapping();$table=(string)($_POST['tabella']??$_GET['tabella']??'Associazioni');$message='';$states=[];$actual=[];
try{
 if(!isset($map['tables'][$table]))throw new RuntimeException('Tabella non prevista dalla mappa.');
 $p=db(poimanager_config());$spec=$map['tables'][$table];
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  if(!isset($_POST['conferma']))throw new RuntimeException('Confermare di avere un backup e di avere sospeso le altre scritture.');
  $lock='sqlite_import_'.substr(hash('sha256',(string)$p->query('SELECT DATABASE()')->fetchColumn()),0,32);
  $q=$p->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Altra importazione in corso.');
  try{$done=schema_add_missing_checks($p,$table,$spec);$message=$done?'CHECK aggiunti: '.implode(', ',$done).'. Ripetere l’anteprima dell’importazione.':'Nessun CHECK mancante da aggiungere. Controllare gli eventuali CHECK diversi o non applicati.';}
  finally{$q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
 }
 $states=schema_check_status($p,$table,$spec);$actual=schema_checks($p,$table);
}catch(Throwable $e){
 $message=$e instanceof PDOException?'Operazione schema non completata. Verificare permessi ALTER, dati e CHECK già presenti. Le eventuali aggiunte già riuscite restano applicate: rileggere lo stato prima di riprovare.':$e->getMessage();
}
page_start('Verifica CHECK','Confronta i vincoli MySQL e aggiungi soltanto le regole mancanti, senza modificare i dati.',['Servizi','Importazione SQLite','Verifica CHECK']);
if($message)echo '<aside>'.uh($message).'</aside>';
?>
<p><a href="/importazione_sqlite/index.php">Torna all’importazione</a></p>
<form method="get"><label>Tabella<select name="tabella"><?php foreach($map['tables'] as $name=>$s)if($s['checks'])echo '<option value="'.uh($name).'" '.($name===$table?'selected':'').'>'.uh($name).'</option>';?></select></label><button>Verifica</button></form>
<h2>Regole previste per <?=uh($table)?></h2>
<table><tr><th>Nome previsto</th><th>Regola</th><th>Stato</th><th>Nome effettivo</th></tr>
<?php foreach($states as $name=>$row):?><tr><td><?=uh($name)?></td><td><?=uh($row['expression'])?></td><td><?=uh($row['status'])?></td><td><?=uh($row['actual_name'])?></td></tr><?php endforeach;?></table>
<h2>CHECK presenti in MySQL</h2>
<table><tr><th>Nome</th><th>Regola effettiva</th><th>Applicato</th></tr>
<?php foreach($actual as $row):?><tr><td><?=uh($row['CONSTRAINT_NAME'])?></td><td><?=uh($row['CHECK_CLAUSE'])?></td><td><?=uh($row['ENFORCED'])?></td></tr><?php endforeach;?></table>
<p>I nomi possono essere diversi se le regole sono equivalenti. I CHECK diversi o non applicati non vengono cancellati o sostituiti automaticamente. Le aggiunte richiedono ALTER e sono confermate una per una da MySQL.</p>
<?php if(count(array_filter($states,fn($r)=>$r['status']==='mancante'))):?>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="tabella" value="<?=uh($table)?>">
<label><input type="checkbox" name="conferma" required> Ho un backup e ho sospeso le altre modifiche ai dati.</label><button>Aggiungi soltanto i CHECK mancanti</button></form>
<?php endif;?></main></body></html>
