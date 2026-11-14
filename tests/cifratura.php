<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/dati_migrazione.php';
require dirname(__DIR__).'/includes/anagrafica.php';
require dirname(__DIR__).'/includes/email.php';
require dirname(__DIR__).'/importazione_sqlite/tabelle.php';
function cr_check(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$p=poimanager_db();$keysBefore=file_get_contents(dirname(__DIR__).'/private/security.php');
$logical=[];$counts=[];
foreach(dc_spec() as $table=>$spec)if(dc_exists($p,$table)){
 $logical[$table]=$p->query('SELECT * FROM '.dc_q($table))->fetchAll(PDO::FETCH_ASSOC);
 $counts[$table]=count($logical[$table]);
}
$tokenJob=$p->query('SELECT ID FROM email_invii LIMIT 1')->fetchColumn();$tokenBefore=$tokenJob?em_token($tokenJob):null;
dc_begin($p);$stopped=false;try{enti_list($p,'',0,1);}catch(RuntimeException $e){$stopped=true;}cr_check($stopped,'Gestionale non bloccato durante migrazione');
$first=dc_migration_step($p,3);cr_check(!$first['done'],'Nessun lotto iniziale');
dc_begin($p); // Ripresa preparazione idempotente senza nuove chiavi o conteggi.
for($i=0;$i<1000;$i++){if(dc_migration_step($p,25)['done'])break;}
cr_check(dc_active($p),'Migrazione non completata');
cr_check(file_get_contents(dirname(__DIR__).'/private/security.php')===$keysBefore,'Chiave utenti alterata');
cr_check(!$tokenJob||em_token($tokenJob)===$tokenBefore,'Link conferme alterati');
foreach($logical as $table=>$before){
 cr_check((int)$p->query('SELECT COUNT(*) FROM '.dc_q($table))->fetchColumn()===$counts[$table],'Conteggio cambiato '.$table);
 $rows=dc_rows($p,$table);$spec=dc_spec()[$table];
 foreach($rows as &$row){unset($row['_CryptoID']);}unset($row);
 $sortedBefore=array_map('serialize',$before);$sortedAfter=array_map('serialize',$rows);sort($sortedBefore);sort($sortedAfter);cr_check($sortedAfter===$sortedBefore,'Valori logici diversi dopo cifratura '.$table);
 cr_check(dc_verify_table($p,$table)===$counts[$table],'Verifica indici '.$table);
 $raw=$p->query('SELECT * FROM '.dc_q($table))->fetchAll(PDO::FETCH_ASSOC);
 foreach($raw as $r)foreach($spec['fields'] as $field){
  if($r[$field]!==null)cr_check($r[$field]===''||preg_match('/^[a-f0-9]{64}$/D',$r[$field])===1,'Valore in chiaro rimasto '.$table.'.'.$field);
 }
}
$search=enti_list($p,'ricercabile',100,1);cr_check($search['total']===1,'Ricerca persone cifrate');
cr_check(enti_list($p,'%',100,1)['total']===0,'Ricerca letterale alterata');
cr_check(count(enti_runts_search($p,'rossi'))===1,'Ricerca RUNTS cifrato');
$created=enti_create($p,['SOGGETTO'=>'ENTE DOPO CIFRATURA','ID_TIPOLOGIA'=>100,'CODICEFISCALE'=>'CFCRYPTO001','TAVOLO'=>'1']);
cr_check(enti_detail($p,$created)['SOGGETTO']==='ENTE DOPO CIFRATURA','Inserimento anagrafica cifrata');
$raw=$p->query('SELECT SOGGETTO,CODICEFISCALE FROM Associazioni WHERE ID='.$created)->fetch();
cr_check($raw['SOGGETTO']!=='ENTE DOPO CIFRATURA'&&$raw['CODICEFISCALE']===null,'Nuovo ente in chiaro');
$duplicate=false;try{enti_create($p,['SOGGETTO'=>'ENTE DOPO CIFRATURA','ID_TIPOLOGIA'=>100]);}catch(PDOException $e){$duplicate=true;}cr_check($duplicate,'Unicità persa');
$contact=em_contacts($p)[0];$draft=em_save_draft($p,['subject'=>'CIFRATURA EMAIL','text'=>'Ciao {nome}','html'=>'<p>Ciao {nome}</p>','mail_ids'=>[$contact['ID']]]);
cr_check(dc_find($p,'email_messaggi',['ID'=>$draft])['Oggetto']==='CIFRATURA EMAIL','Bozza cifrata');
cr_check($p->query('SELECT Oggetto FROM email_messaggi WHERE ID='.$draft)->fetchColumn()==='','Oggetto in chiaro');
$config=em_config($p);$fake=function($c,$k,$payload){return ['ok'=>true,'sender'=>$c['Mittente'],'quota'=>100,'status'=>'sent'];};
em_ping($p,$config,$fake);em_prepare($p,$draft);
$seen=null;$send=function($c,$k,$payload)use(&$seen,$fake){if($payload['op']==='send')$seen=$payload;return $fake($c,$k,$payload);};
em_send_batch($p,$draft,$send);cr_check($seen!==null&&$seen['subject']==='[TEST] CIFRATURA EMAIL'&&$seen['to']===$config['EmailTest'],'Invio non decifrato');
$map=table_mapping();$path=tempnam(sys_get_temp_dir(),'crypto_import_');
try{
 $s=new PDO('sqlite:'.$path);$s->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$s->exec($map['tables']['Tipologia']['source_sql']);
 $s->exec("INSERT INTO Tipologia (ID,Tipologia) VALUES (1,'TIPO AGGIORNATO CIFRATO'),(400,'TIPO NUOVO CIFRATO')");
 $inventory=table_inventory($s,$map);$order=table_order(['Tipologia'],$map,$inventory);
 $plan=table_plan($p,$s,$map,$order);cr_check($plan['Tipologia']['aggiornati']===1&&$plan['Tipologia']['nuovi']===1,'Anteprima import cifrato');
 table_import($p,$s,$map,$order,$plan,table_fingerprint($p,$map,$order));
 cr_check(dc_find($p,'Tipologia',['ID'=>1])['Tipologia']==='TIPO AGGIORNATO CIFRATO','Update import cifrato');
 cr_check($p->query('SELECT Tipologia FROM Tipologia WHERE ID=400')->fetchColumn()!=='TIPO NUOVO CIFRATO','Import in chiaro');
 $plan=table_plan($p,$s,$map,$order);cr_check($plan['Tipologia']['invariati']===2,'Reimport cifrato non idempotente');
}finally{$s=null;@unlink($path);}
$blindOriginal=$p->query('SELECT SOGGETTO FROM Associazioni WHERE ID='.$created)->fetchColumn();
$p->prepare('UPDATE Associazioni SET SOGGETTO=? WHERE ID=?')->execute([str_repeat('0',64),$created]);
$blocked=false;try{enti_detail($p,$created);}catch(RuntimeException $e){$blocked=true;}cr_check($blocked,'Indice HMAC alterato non rilevato');
$p->prepare('UPDATE Associazioni SET SOGGETTO=? WHERE ID=?')->execute([$blindOriginal,$created]);
$targetOriginal=$p->query('SELECT _Cifrato FROM Associazioni WHERE ID=1')->fetchColumn();
$original=$p->query('SELECT _Cifrato FROM Associazioni WHERE ID='.$created)->fetchColumn();
$p->prepare('UPDATE Associazioni SET _Cifrato=? WHERE ID=?')->execute([$original,1]);
$rejected=false;try{enti_detail($p,1);}catch(RuntimeException $e){$rejected=true;}cr_check($rejected,'Scambio di ciphertext tra righe accettato');
$p->prepare('UPDATE Associazioni SET _Cifrato=? WHERE ID=1')->execute([$targetOriginal]);
echo "OK: migrazione riprendibile, dati/ID invariati, nessun residuo testuale, ricerche, email, import/update, unicità e autenticazione della riga. Nessuna email reale.\n";
