<?php
declare(strict_types=1);
require_once __DIR__.'/crypto.php';
function dc_spec(): array {static $spec=null;return $spec??=json_decode(file_get_contents(dirname(__DIR__).'/schema/cifratura_v1.json'),true,512,JSON_THROW_ON_ERROR)['tables'];}
function dc_q(string $s):string {if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$s))throw new RuntimeException('Identificatore non valido.');return '`'.$s.'`';}
function dc_exists(PDO $p,string $table):bool {$q=$p->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);return (int)$q->fetchColumn()===1;}
function dc_state(PDO $p):?array {
 if(!dc_exists($p,'crypto_migrazione'))return null;
 $r=$p->query('SELECT * FROM crypto_migrazione WHERE ID=1')->fetch(PDO::FETCH_ASSOC);return $r===false?null:$r;
}
function dc_keys():array {
 static $cached=null;if($cached!==null)return $cached;
 $file=dirname(__DIR__).'/private/dati_security.php';
 if(!is_file($file))throw new RuntimeException('Chiave dei dati non disponibile. Ripristinare il file privato, senza rigenerarlo.');
 if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
 $s=require $file;
 foreach(['key','index_key'] as $key)if(strlen(base64_decode($s[$key]??'',true)?:'')!==32)throw new RuntimeException('Chiave dati non valida.');
 if(!profile_cipher_available($s['cipher']??''))throw new RuntimeException('Motore di cifratura dei dati non disponibile.');
 return $cached=$s;
}
function dc_active(PDO $p):bool {
 $state=dc_state($p);if(!$state)return false;
 if($state['Stato']!=='pronto')throw new RuntimeException('Migrazione della cifratura in corso: funzioni gestionali temporaneamente in manutenzione.');
 $keys=dc_keys();
 if(!hash_equals($state['ChiaveHash'],hash('sha256',$keys['key'].'|'.$keys['index_key'])))throw new RuntimeException('Il file chiave non corrisponde al database. Non rigenerarlo.');
 return true;
}
function dc_index(string $table,string $field,$value):?string {
 if($value===null)return null;
 return hash_hmac('sha256',$table."\0".$field."\0".(string)$value,base64_decode(dc_keys()['index_key'],true));
}
function dc_columns(PDO $p,string $table,bool $refresh=false):array {
 static $cache=[];$id=spl_object_id($p).':'.$table;if(!$refresh&&isset($cache[$id]))return $cache[$id];
 $q=$p->prepare('SELECT COLUMN_NAME,IS_NULLABLE,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$out=[];
 foreach($q as $r)$out[$r['COLUMN_NAME']]=$r;
 return $cache[$id]=$out;
}
function dc_seal(string $table,array $row):string {
 $spec=dc_spec()[$table];$id=$row[$spec['identity']]??null;
 if($id===null)throw new RuntimeException('Identità per cifratura assente: '.$table);
 $values=[];foreach($spec['fields'] as $field){if(!array_key_exists($field,$row))throw new RuntimeException('Campo cifrato assente: '.$table.'.'.$field);$values[$field]=$row[$field]===null?null:base64_encode((string)$row[$field]);}
 $keys=dc_keys();
 return 'dc1:'.profile_encrypt(['version'=>1,'table'=>$table,'id'=>(string)$id,'values'=>$values],base64_decode($keys['key'],true),$keys['cipher']);
}
function dc_open(string $table,array $row):array {
 $spec=dc_spec()[$table]??null;if(!$spec)return $row;
 if(!isset($row['_Cifrato'])||!is_string($row['_Cifrato'])||!str_starts_with($row['_Cifrato'],'dc1:'))throw new RuntimeException('Riga non cifrata o incompleta: '.$table);
 $data=profile_decrypt(substr($row['_Cifrato'],4),base64_decode(dc_keys()['key'],true));
 if(($data['table']??null)!==$table||($data['version']??null)!==1||($data['id']??null)!==(string)($row[$spec['identity']]??''))throw new RuntimeException('Il contenuto cifrato non corrisponde alla riga: '.$table);
 foreach($spec['fields'] as $field){
  if(!array_key_exists($field,$data['values']??[]))throw new RuntimeException('Campo cifrato non valido.');
  $v=$data['values'][$field];$plain=$v===null?null:base64_decode($v,true);if($v!==null&&$plain===false)throw new RuntimeException('Valore cifrato non valido.');
  $row[$field]=$plain;
 }
 unset($row['_Cifrato']);return $row;
}
function dc_mask(PDO $p,string $table,array $row):array {
 $spec=dc_spec()[$table];$columns=dc_columns($p,$table);
 foreach($spec['fields'] as $field){
  if(!array_key_exists($field,$row))throw new RuntimeException('Campo sensibile mancante: '.$field);
  if($row[$field]===null&&$columns[$field]['IS_NULLABLE']!=='YES')throw new RuntimeException('NULL non ammesso: '.$table.'.'.$field);
  if(in_array($field,$spec['indexed'],true))$row[$field]=dc_index($table,$field,$row[$field]);
  else $row[$field]=$columns[$field]['IS_NULLABLE']==='YES'?null:'';
 }
 return $row;
}
function dc_decode_rows(PDO $p,string $table,array $rows):array {
 $encrypted=dc_active($p)&&isset(dc_spec()[$table]);
 return $encrypted?array_map(fn($r)=>dc_open($table,$r),$rows):$rows;
}
function dc_rows(PDO $p,string $table,string $suffix='',array $args=[]):array {
 $q=$p->prepare('SELECT * FROM '.dc_q($table).' '.$suffix);$q->execute($args);
 return dc_decode_rows($p,$table,$q->fetchAll(PDO::FETCH_ASSOC));
}
function dc_iter(PDO $p,string $table):Generator {
 $encrypted=dc_active($p)&&isset(dc_spec()[$table]);$columns=dc_columns($p,$table);
 $key=isset($columns['ID'])?'ID':(isset($columns['_CryptoID'])?'_CryptoID':null);$offset=0;$last=null;
 do{
  if($key){$q=$p->prepare('SELECT * FROM '.dc_q($table).($last!==null?' WHERE '.dc_q($key).'>?':'').' ORDER BY '.dc_q($key).' LIMIT 500');$q->execute($last!==null?[$last]:[]);}
  else{$q=$p->query('SELECT * FROM '.dc_q($table).' LIMIT 500 OFFSET '.$offset);}
  $rows=$q->fetchAll(PDO::FETCH_ASSOC);
  foreach($rows as $row){if($key)$last=$row[$key];yield $encrypted?dc_open($table,$row):$row;}
  $offset+=count($rows);
 }while(count($rows)===500);
}
function dc_find(PDO $p,string $table,array $key,bool $lock=false):?array {
 $encrypted=dc_active($p)&&isset(dc_spec()[$table]);$args=[];$where=[];
 foreach($key as $column=>$value){
  if($encrypted&&in_array($column,dc_spec()[$table]['indexed'],true))$value=dc_index($table,$column,$value);
  if($value===null)$where[]=dc_q($column).' IS NULL';
  else{$where[]=dc_q($column).'=?';$args[]=$value;}
 }
 if(!$where)throw new RuntimeException('Chiave di ricerca assente.');
 $q=$p->prepare('SELECT * FROM '.dc_q($table).' WHERE '.implode(' AND ',$where).' LIMIT 2'.($lock?' FOR UPDATE':''));$q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 if(count($rows)>1)throw new RuntimeException('Chiave non univoca: '.$table);
 return !$rows?null:($encrypted?dc_open($table,$rows[0]):$rows[0]);
}
function dc_insert(PDO $p,string $table,array $values) {
 $encrypted=dc_active($p)&&isset(dc_spec()[$table]);$owned=!$p->inTransaction();
 if($owned)$p->beginTransaction();
 try{
  $write=$values;
  if($encrypted){
   foreach(dc_spec()[$table]['fields'] as $field)if(!array_key_exists($field,$write)){
    if(dc_columns($p,$table)[$field]['IS_NULLABLE']!=='YES')throw new RuntimeException('Campo obbligatorio assente: '.$table.'.'.$field);
    $write[$field]=null;
   }
   // I valori richiesti dal contratto NOT NULL vanno esplicitati dal chiamante.
   $plain=$write;$write=dc_mask($p,$table,$write);$write['_Cifrato']=null;
  }
  $q=$p->prepare('INSERT INTO '.dc_q($table).' ('.implode(',',array_map('dc_q',array_keys($write))).') VALUES ('.implode(',',array_fill(0,count($write),'?')).')');$q->execute(array_values($write));
  $newId=$p->lastInsertId();
  if($encrypted){
   $identity=dc_spec()[$table]['identity'];$plain[$identity]=$values[$identity]??$newId;
   $q=$p->prepare('UPDATE '.dc_q($table).' SET _Cifrato=? WHERE '.dc_q($identity).'=?');$q->execute([dc_seal($table,$plain),$plain[$identity]]);
  }
  if($owned)$p->commit();
  return $values['ID']??$newId;
 }catch(Throwable $e){if($owned&&$p->inTransaction())$p->rollBack();throw $e;}
}
function dc_update(PDO $p,string $table,array $key,array $changes):void {
 $encrypted=dc_active($p)&&isset(dc_spec()[$table]);$owned=!$p->inTransaction();if($owned)$p->beginTransaction();
 try{
  $old=dc_find($p,$table,$key,true);if(!$old)throw new RuntimeException('Riga da aggiornare non trovata: '.$table);
  $row=array_merge($old,$changes);unset($row['_Cifrato']);
  if($encrypted){
   $spec=dc_spec()[$table];$masked=dc_mask($p,$table,$row);
   $write=$changes;foreach($spec['fields'] as $field)$write[$field]=$masked[$field];
   $write['_Cifrato']=dc_seal($table,$row);$whereKey=[$spec['identity']=>$old[$spec['identity']]];
  }else{$write=$changes;$whereKey=$key;}
  if($write){
   $where=[];$args=[];
   foreach($whereKey as $k=>$v){if($v===null)$where[]=dc_q($k).' IS NULL';else{$where[]=dc_q($k).'=?';$args[]=$v;}}
   $q=$p->prepare('UPDATE '.dc_q($table).' SET '.implode(',',array_map(fn($k)=>dc_q($k).'=?',array_keys($write))).' WHERE '.implode(' AND ',$where));$q->execute(array_merge(array_values($write),$args));
  }
  if($owned)$p->commit();
 }catch(Throwable $e){if($owned&&$p->inTransaction())$p->rollBack();throw $e;}
}
function dc_contains(string $text,string $query):bool {
 if(function_exists('mb_stripos'))return mb_stripos($text,$query,0,'UTF-8')!==false;
 return stripos($text,$query)!==false;
}
