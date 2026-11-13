<?php
declare(strict_types=1);
require_once __DIR__.'/tabelle.php';
function schema_checks(PDO $p,string $table): array {
 $q=$p->prepare("SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE,tc.ENFORCED FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME=? AND tc.CONSTRAINT_TYPE='CHECK'");
 $q->execute([$table]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function schema_check_status(PDO $p,string $table,array $spec): array {
 $actual=schema_checks($p,$table);$result=[];
 foreach($spec['checks'] as $name=>$expression){
  $status='mancante';$found='';
  foreach($actual as $row){
   if(canonicalCheck($row['CHECK_CLAUSE'])===canonicalCheck($expression)){
    $status=$row['ENFORCED']==='YES'?'presente':'non applicato';$found=$row['CONSTRAINT_NAME'];
    if($status==='presente')break;
   }
  }
  if($status==='mancante'){
   foreach($actual as $row)if($row['CONSTRAINT_NAME']===$name){$status='nome occupato da CHECK diverso';$found=$name;}
  }
  $result[$name]=['expression'=>$expression,'status'=>$status,'actual_name'=>$found];
 }
 return $result;
}
function schema_add_missing_checks(PDO $p,string $table,array $spec): array {
 // Operazioni DDL: MySQL le conferma individualmente, senza rollback globale.
 $done=[];
 foreach(schema_check_status($p,$table,$spec) as $name=>$row){
  if($row['status']!=='mancante')continue;
  $invalid=(int)$p->query('SELECT COUNT(*) FROM '.qi($table).' WHERE NOT ('.$row['expression'].')')->fetchColumn();
  if($invalid!==0)throw new RuntimeException('CHECK '.$name.' non aggiunto: '.$invalid.' righe esistenti non rispettano la regola. Nessun dato corretto automaticamente.');
  $p->exec('ALTER TABLE '.qi($table).' ADD CONSTRAINT '.qi($name).' CHECK ('.$row['expression'].') ENFORCED');
  $done[]=$name;
 }
 return $done;
}
