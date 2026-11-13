<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/database.php';
function qi(string $s): string { return '`'.str_replace('`','``',$s).'`'; }
function mapping(): array { return json_decode(file_get_contents(__DIR__.'/mappa_v1.json'),true,512,JSON_THROW_ON_ERROR); }
function db(array $cfg): PDO {
 if (!in_array('mysql',PDO::getAvailableDrivers(),true) || !in_array('sqlite',PDO::getAvailableDrivers(),true)) throw new RuntimeException('Occorrono le estensioni PDO MySQL e PDO SQLite.');
 $p=poimanager_db();
 $v=$p->query('SELECT VERSION()')->fetchColumn();
 if (stripos($v,'MariaDB')!==false || version_compare($v,'8.0.16','<')) throw new RuntimeException('Questa versione della procedura richiede MySQL >=8.0.16. Server: '.$v);
 $p->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO'"); $p->exec('SET FOREIGN_KEY_CHECKS=1'); return $p;
}
function normSql(string $s): string { return preg_replace('/\s+/','',trim($s)); }
function canonicalCheck(string $s): string {
 $s=preg_replace("/_[a-zA-Z0-9]+(?=\s*')/",'', $s);
 $s=preg_replace_callback("/'(?:''|[^'])*'/",fn($m)=>'literal'.hash('sha256',$m[0]),$s);
 $s=strtolower($s);
 $s=preg_replace('/cast\s*\(\s*(`[^`]+`)\s+as\s+(?:char\s+charset\s+binary|binary)\s*\)/','binary $1',$s);
 return preg_replace('/[\s`()]/','',$s);
}

function transformed($v,array $rule) {
 if ($v===null) return null;
 switch($rule['transform']) {
 case 'identity': return $v;
 case 'integer':
  if (!is_numeric($v) || (string)(int)$v!==(string)$v && (float)(int)$v!==(float)$v) throw new RuntimeException('Valore non convertibile in intero.');
  return (int)$v;
 default: throw new RuntimeException('Trasformazione non implementata: '.$rule['transform']);
 }
}
function source(string $file,array $map): PDO {
 $f=fopen($file,'rb'); $head=fread($f,16); fclose($f);
 if ($head!=="SQLite format 3\0") throw new RuntimeException('Il file non è SQLite.');
 $s=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $s->exec('PRAGMA query_only=ON');
 if ($s->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN)!==['ok']) throw new RuntimeException('SQLite non integro.');
 if ($s->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Vincoli SQLite violati.');
 $actual=$s->query("SELECT name,sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
 $names=array_keys($actual); $expected=array_keys($map['tables']);sort($names);sort($expected);
 if ($names!==$expected) throw new RuntimeException('Tabelle SQLite diverse dalla versione supportata. Aggiornare la mappatura.');
 foreach($map['tables'] as $t=>$spec) if(normSql($actual[$t])!==normSql($spec['source_sql'])) throw new RuntimeException('Struttura SQLite cambiata: '.$t.'. Aggiornare la procedura.');
 return $s;
}
function inspectTarget(PDO $p,array $map): void {
 foreach($map['tables'] as $t=>$spec) {
  $tr=$p->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?');$tr->execute([$t]);
  if((int)$tr->fetchColumn()>0)throw new RuntimeException('Trigger inattesi nella destinazione: '.$t);
  $st=$p->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$st->execute([$t]);
  if ($st->fetchColumn()!=='InnoDB') throw new RuntimeException('Tabella assente o non InnoDB: '.$t);
  $st=$p->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$st->execute([$t]);$cols=$st->fetchAll(PDO::FETCH_ASSOC);
  if(count($cols)!==count($spec['columns'])) throw new RuntimeException('Numero colonne cambiato: '.$t);
  foreach($cols as $i=>$col) {
   $e=$spec['columns'][$i];$typ=preg_replace('/bigint\(\d+\)/','bigint',strtolower($col['COLUMN_TYPE']));
   if ($col['COLUMN_NAME']!==$e['name'] || $typ!==$e['type'] || ($col['IS_NULLABLE']==='YES')!==$e['nullable'] || $col['COLUMN_DEFAULT']!==$e['default'] || (stripos($col['EXTRA'],'auto_increment')!==false)!==$e['auto'] || $col['COLLATION_NAME']!==$e['collation'] || preg_match('/generated/i',$col['EXTRA'])) throw new RuntimeException('Struttura MySQL diversa dalla mappa '.$map['version'].': '.$t.'.'.$e['name']);
  }
  $st=$p->prepare('SELECT INDEX_NAME,COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND NON_UNIQUE=0 ORDER BY INDEX_NAME,SEQ_IN_INDEX');$st->execute([$t]);$idx=[];
  foreach($st as $r)$idx[$r['INDEX_NAME']][]=$r['COLUMN_NAME'];
  $u=array_map(fn($a)=>implode('|',$a),array_values($idx));$e=array_map(fn($a)=>implode('|',$a),$spec['unique']);sort($u);sort($e);
  if($u!==$e)throw new RuntimeException('Vincoli univoci diversi: '.$t);
  $st=$p->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL');$st->execute([$t]);$f=[];
  foreach($st as $r)$f[]=implode('|',array_values($r));$e=array_map(fn($x)=>implode('|',array_values($x)),$spec['fk']);sort($f);sort($e);
  // MySQL espone NO ACTION come RESTRICT in alcune versioni.
  $f=str_replace('NO ACTION','RESTRICT',$f);$e=str_replace('NO ACTION','RESTRICT',$e);
  if($f!==$e)throw new RuntimeException('Chiavi esterne diverse: '.$t);
  $st=$p->prepare("SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE,tc.ENFORCED FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=DATABASE() AND tc.TABLE_NAME=? AND tc.CONSTRAINT_TYPE='CHECK'");$st->execute([$t]);$checks=[];
  foreach($st as $r){if($r['ENFORCED']!=='YES')throw new RuntimeException('CHECK non applicato: '.$t);$checks[$r['CONSTRAINT_NAME']]=canonicalCheck($r['CHECK_CLAUSE']);}
  $e=array_map('canonicalCheck',$spec['checks']);ksort($e);ksort($checks);
  if($checks!==$e)throw new RuntimeException('CHECK assenti o diversi: '.$t.'. Applicare solo i CHECK mancanti o aggiornare la mappa.');
  if((int)$p->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn()!==0)throw new RuntimeException('Importazione bloccata: tabella non vuota '.$t.'. Nessuna sovrascrittura automatica.');
 }
}
function scan(PDO $s,array $map): array {
 $counts=[];
 foreach($map['tables'] as $t=>$spec){
  $n=0;
  foreach($s->query('SELECT * FROM '.qi($t),PDO::FETCH_ASSOC) as $row){
   foreach($spec['columns'] as $c){$v=transformed($row[$spec['mapping'][$c['name']]['source']],$spec['mapping'][$c['name']]);
    if($v===null&&!$c['nullable'])throw new RuntimeException('NULL non ammesso: '.$t.'.'.$c['name']);
    if($v!==null&&$c['type']==='varbinary(768)'&&strlen((string)$v)>768)throw new RuntimeException('Testo troppo lungo: '.$t.'.'.$c['name']);
   }$n++;
  }$counts[$t]=$n;
 }return $counts;
}
function digestRows(PDO $db,string $table,array $cols): string {
 $hashes=[];
 foreach($db->query('SELECT '.implode(',',array_map(fn($c)=>qi($c['name']),$cols)).' FROM '.qi($table),PDO::FETCH_NUM) as $row){
  $v=[];foreach($row as $i=>$x)$v[]=$x===null?null:($cols[$i]['type']==='bigint'?(string)(int)$x:base64_encode((string)$x));
  $hashes[]=hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));
 }sort($hashes);return hash('sha256',implode('',$hashes));
}
function importData(PDO $p,PDO $s,array $map,array $counts): array {
 $lock='sqlite_import_'.substr(hash('sha256',(string)$p->query('SELECT DATABASE()')->fetchColumn()),0,32);
 $st=$p->prepare('SELECT GET_LOCK(?,0)');$st->execute([$lock]);if((int)$st->fetchColumn()!==1)throw new RuntimeException('Altra importazione in corso.');
 try {
  inspectTarget($p,$map);
  // DDL sui soli contatori PRIMA della transazione dati: ALTER causerebbe un commit implicito.
  $sequences=$s->query('SELECT name,seq FROM sqlite_sequence')->fetchAll(PDO::FETCH_KEY_PAIR);
  foreach($map['tables'] as $t=>$spec) foreach($spec['columns'] as $col) if($col['auto']){
   $max=(int)$s->query('SELECT COALESCE(MAX('.qi($spec['mapping'][$col['name']]['source']).'),0) FROM '.qi($t))->fetchColumn();
   $next=max($max,(int)($sequences[$t]??0))+1;
   $p->exec('ALTER TABLE '.qi($t).' AUTO_INCREMENT = '.$next);
  }
  $p->exec('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');$p->beginTransaction();inspectTarget($p,$map);
  foreach($map['tables'] as $t=>$spec){
   $cols=$spec['columns'];$stmt=$p->prepare('INSERT INTO '.qi($t).' ('.implode(',',array_map(fn($x)=>qi($x['name']),$cols)).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');
   foreach($s->query('SELECT * FROM '.qi($t),PDO::FETCH_ASSOC) as $row){
    foreach($cols as $i=>$col){$r=$spec['mapping'][$col['name']];$v=transformed($row[$r['source']],$r);$stmt->bindValue($i+1,$v,$v===null?PDO::PARAM_NULL:($col['type']==='bigint'?PDO::PARAM_INT:PDO::PARAM_STR));}$stmt->execute();
   }
   if((int)$p->query('SELECT COUNT(*) FROM '.qi($t))->fetchColumn()!==$counts[$t] || digestRows($p,$t,$cols)!==digestRows($s,$t,$cols)) throw new RuntimeException('Verifica dati fallita: '.$t);
  }
  $p->commit();return $counts;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 finally{$st=$p->prepare('SELECT RELEASE_LOCK(?)');$st->execute([$lock]);}
}
