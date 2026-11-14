<?php
declare(strict_types=1);
require_once __DIR__.'/motore.php';
function table_mapping(): array {return json_decode(file_get_contents(__DIR__.'/mappa_v4.json'),true,512,JSON_THROW_ON_ERROR);}
function table_source(string $file): PDO {
 $head=file_get_contents($file,false,null,0,16);
 if($head!=="SQLite format 3\0")throw new RuntimeException('Il file non è SQLite.');
 $s=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $s->exec('PRAGMA query_only=ON');
 if($s->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN)!==['ok'])throw new RuntimeException('SQLite non integro.');
 return $s;
}
function table_inventory(PDO $s,array $map): array {
 $result=[];
 foreach($s->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $row){
  $t=$row['name'];$spec=$map['tables'][$t]??null;$reason='';
  if(!$spec)$reason=$t==='Utenti'?'Utenti esclusi':'Tabella non mappata';
  elseif(!$spec['unique'])$reason='Nessuna chiave affidabile per aggiornare';
  else{
   $columns=array_column($s->query('PRAGMA table_info('.qi($t).')')->fetchAll(),'name');
   $required=array_unique(array_column($spec['mapping'],'source'));
   $missing=array_diff($required,$columns);
   if(array_intersect(['_Cifrato','_CryptoID'],$columns))$reason='Fonte con colonne di cifratura: usare il database SQLite originale, non una copia dei campi HMAC';
   elseif($missing)$reason='Colonne sorgente mancanti: '.implode(', ',$missing);
  }
  $result[$t]=['present'=>true,'count'=>(int)$s->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn(),'reason'=>$reason,'enabled'=>$reason===''];
 }
 foreach($map['tables'] as $t=>$spec)if(!isset($result[$t]))$result[$t]=['present'=>false,'count'=>null,'reason'=>'Tabella prevista in MySQL ma assente nel file SQLite','enabled'=>false];
 $relations=table_relationships($s,$map);
 foreach($result as $t=>&$entry){
  $entry['outgoing']=[];$entry['incoming']=[];
  foreach($relations as $rel){
   $label=implode(' / ',$rel['origins']);
   if($rel['from_table']===$t)$entry['outgoing'][]=$rel['from_column'].' → '.$rel['to_table'].'.'.$rel['to_column'].' ('.$label.')';
   if($rel['to_table']===$t)$entry['incoming'][]=$rel['from_table'].'.'.$rel['from_column'].' → '.$rel['to_column'].' ('.$label.')';
  }
  if(in_array($t,['Mail','Telefono'],true))$entry['outgoing'][]='TipoSoggetto + IDSoggetto: riferimento polimorfico; destinazione non dichiarata da una FK, da verificare.';
 }
 unset($entry);ksort($result);
 return $result;
}
function table_relationships(PDO $s,array $map): array {
 $edges=[];
 $add=function(string $from,string $column,string $to,string $ref,string $origin)use(&$edges):void{
  $key=json_encode([$from,$column,$to,$ref],JSON_THROW_ON_ERROR);
  if(!isset($edges[$key]))$edges[$key]=['from_table'=>$from,'from_column'=>$column,'to_table'=>$to,'to_column'=>$ref,'origins'=>[]];
  if(!in_array($origin,$edges[$key]['origins'],true))$edges[$key]['origins'][]=$origin;
 };
 foreach($s->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $row){
  $t=$row['name'];
  foreach($s->query('PRAGMA foreign_key_list('.qi($t).')') as $fk)$add($t,(string)$fk['from'],(string)$fk['table'],(string)($fk['to']?:'(chiave primaria)'),'FK SQLite');
 }
 foreach($map['tables'] as $t=>$spec){
  foreach($spec['fk'] as $fk)$add($t,$fk['column'],$fk['table'],$fk['ref'],'FK MySQL prevista');
  foreach($spec['logical_fk']??[] as $fk)$add($t,$fk['column'],$fk['table'],$fk['ref'],'vincolo logico applicato');
 }
 return array_values($edges);
}
function table_order(array $selected,array $map,array $inventory): array {
 if(!$selected)throw new RuntimeException('Selezionare almeno una tabella.');
 $ordered=[];$state=[];
 $visit=function(string $t)use(&$visit,&$ordered,&$state,$map,$inventory):void{
  if(($state[$t]??0)===2)return;
  if(($state[$t]??0)===1)throw new RuntimeException('Dipendenze cicliche: '.$t);
  if(!isset($map['tables'][$t])||empty($inventory[$t]['enabled']))throw new RuntimeException('Tabella non importabile: '.$t.'. '.($inventory[$t]['reason']??'Assente nel file SQLite'));
  $state[$t]=1;
  foreach(array_merge($map['tables'][$t]['fk'],$map['tables'][$t]['logical_fk']??[]) as $fk)$visit($fk['table']);
  $state[$t]=2;$ordered[]=$t;
 };
 foreach($selected as $t){if(!is_string($t))throw new RuntimeException('Selezione non valida.');$visit($t);}
 return $ordered;
}
function table_values(array $row,array $spec): array {
 $values=[];
 foreach($spec['columns'] as $c){
  $rule=$spec['mapping'][$c['name']];$v=transformed($row[$rule['source']],$rule);
  if($v===null&&!$c['nullable'])throw new RuntimeException('NULL non ammesso: '.$c['name']);
  if($v!==null&&$c['type']==='varbinary(768)'&&strlen((string)$v)>768)throw new RuntimeException('Testo troppo lungo: '.$c['name']);
  $values[$c['name']]=$v;
 }
 return $values;
}
function table_identity(array $values,array $spec): array {
 // ID stabile preferito. Le altre chiavi univoche restano vincoli, non identificatori alternativi.
 $keys=$spec['unique'];
 foreach($keys as $key)if($key===['ID']){
  if($values['ID']===null)throw new RuntimeException('ID sorgente assente.');
  return ['ID'=>$values['ID']];
 }
 foreach($keys as $key){
  $valid=true;foreach($key as $column)if($values[$column]===null)$valid=false;
  if($valid)return array_intersect_key($values,array_flip($key));
 }
 throw new RuntimeException('Riga senza chiave univoca valorizzata: aggiornamento ambiguo.');
}
function table_where(array $key): string{return implode(' AND ',array_map(fn($k)=>qi($k).'=?',array_keys($key)));}
function table_find(PDO $p,string $table,array $key): ?array {return dc_find($p,$table,$key);}
function table_equal(array $a,array $b): bool {
 foreach($b as $key=>$value)if(!array_key_exists($key,$a))return false;
 foreach($b as $key=>$value)if(($a[$key]===null)!==($value===null)||($value!==null&&(string)$a[$key]!== (string)$value))return false;
 return true;
}
function table_collision(PDO $p,string $t,array $values,array $spec,array $identity):void {
 foreach($spec['unique'] as $columns){
  $key=array_intersect_key($values,array_flip($columns));
  if(in_array(null,$key,true))continue;
  $found=table_find($p,$t,$key);
  if($found&&!table_equal($found,$identity))throw new RuntimeException('Conflitto di chiave univoca in '.$t.': il valore appartiene a un altro record. Nessun aggiornamento per supposizione.');
 }
}
function table_plan(PDO $p,PDO $s,array $map,array $order): array {
 $subset=$map;$subset['tables']=array_intersect_key($map['tables'],array_flip($order));inspectTarget($p,$subset,false);
 $report=[];
 foreach($order as $t){
  $r=['nuovi'=>0,'aggiornati'=>0,'invariati'=>0];$spec=$map['tables'][$t];$seen=[];
  foreach($s->query('SELECT * FROM '.qi($t)) as $row){
   $values=table_values($row,$spec);$key=table_identity($values,$spec);$hash=hash('sha256',serialize($key));
   if(isset($seen[$hash]))throw new RuntimeException('Chiave ripetuta nel sorgente: '.$t);$seen[$hash]=true;
   table_collision($p,$t,$values,$spec,$key);
   $old=table_find($p,$t,$key);$r[$old===null?'nuovi':(table_equal($old,$values)?'invariati':'aggiornati')]++;
  }
  $report[$t]=$r;
 }
 return $report;
}
function table_fingerprint(PDO $p,array $map,array $order): string {
 $hash=[];foreach($order as $t)$hash[$t]=digestRows($p,$t,$map['tables'][$t]['columns']);
 return hash('sha256',json_encode($hash,JSON_THROW_ON_ERROR));
}
function table_import(PDO $p,PDO $s,array $map,array $order,array $expected,string $fingerprint): array {
 $lock='sqlite_import_'.substr(hash('sha256',(string)$p->query('SELECT DATABASE()')->fetchColumn()),0,32);
 $q=$p->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
 if((int)$q->fetchColumn()!==1)throw new RuntimeException('Altra importazione in corso.');
 try{
  $p->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$p->beginTransaction();
  if(!hash_equals($fingerprint,table_fingerprint($p,$map,$order)))throw new RuntimeException('Destinazione cambiata: ripetere anteprima.');
  $report=table_plan($p,$s,$map,$order);
  if($report!==$expected)throw new RuntimeException('I dati destinazione sono cambiati: ripetere anteprima.');
  foreach($order as $t){
   $spec=$map['tables'][$t];
   foreach($s->query('SELECT * FROM '.qi($t)) as $row){
    $values=table_values($row,$spec);$key=table_identity($values,$spec);$old=table_find($p,$t,$key);
    foreach($spec['logical_fk']??[] as $fk)if($values[$fk['column']]!==null&&!table_find($p,$fk['table'],[$fk['ref']=>$values[$fk['column']]]))throw new RuntimeException('Riferimento mancante in '.$t.' verso '.$fk['table']);
    if($old===null){
     dc_insert($p,$t,$values);
    }elseif(!table_equal($old,$values)){
     $changes=array_diff_key($values,$key);
     if($changes)dc_update($p,$t,$key,$changes);
    }
    if(!table_equal(table_find($p,$t,$key)??[],$values))throw new RuntimeException('Verifica riga fallita: '.$t);
   }
  }
  $p->commit();return $report;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 finally{$q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
