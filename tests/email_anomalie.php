<?php
declare(strict_types=1);
// Solo sul database effimero predisposto da tests/utenti.php e tests/email.php.
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/email.php';
function ea_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function ea_blocked(callable $operation,string $message):void{
 $blocked=false;try{$operation();}catch(RuntimeException $e){$blocked=true;}
 ea_check($blocked,$message);
}
$p=poimanager_db();$encrypted=dc_active($p);$tag=$encrypted?'E':'N';
// La verifica ordinaria segnala lo schema vecchio senza eseguire DDL.
$receipts=$p->query('SELECT ID,ConfermaLettura FROM email_messaggi')->fetchAll(PDO::FETCH_KEY_PAIR);
$p->exec('ALTER TABLE email_messaggi DROP COLUMN ConfermaLettura');
try{
 ea_check(!em_ready($p),'Schema obsoleto considerato pronto');
 ea_check(!isset(dc_columns($p,'email_messaggi',true)['ConfermaLettura']),'La lettura ha modificato lo schema');
 em_install($p);em_install($p);ea_check(em_ready($p),'Aggiornamento esplicito non riuscito');
}finally{
 if(!isset(dc_columns($p,'email_messaggi',true)['ConfermaLettura']))em_install($p);
 $restore=$p->prepare('UPDATE email_messaggi SET ConfermaLettura=? WHERE ID=?');
 foreach($receipts as $id=>$flag)$restore->execute([$flag,$id]);
}
$contact=em_contacts($p)[0];$config=em_config($p);
$fake=static fn($c,$key,$payload)=>['ok'=>true,'sender'=>$c['Mittente'],'quota'=>100,'status'=>'sent'];
em_ping($p,$config,$fake);
$id=em_save_draft($p,['subject'=>'REGRESSIONE '.$tag,'text'=>'Messaggio','html'=>'<p>Messaggio</p>','mail_ids'=>[$contact['ID']]]);
em_prepare($p,$id);$message=dc_find($p,'email_messaggi',['ID'=>$id]);
$input=['id'=>$id,'version'=>$message['Versione'],'subject'=>'Modifica','text'=>'Modifica','html'=>'<p>Modifica</p>','mail_ids'=>[$contact['ID']]];
$cfg=poimanager_config();$other=new PDO($cfg['dsn'],$cfg['user'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$lock='email_send_'.$id;$other->prepare('SELECT GET_LOCK(?,0)')->execute([$lock]);
try{
 ea_blocked(fn()=>em_save_draft($p,$input),'Modifica durante invio ammessa');
 ea_blocked(fn()=>em_prepare($p,$id),'Preparazione durante invio ammessa');
 ea_blocked(fn()=>em_send_batch($p,$id,$fake),'Invio concorrente ammesso');
 ea_blocked(fn()=>em_reconcile($p,$id,$fake),'Verifica concorrente ammessa');
 ea_blocked(fn()=>em_delete($p,$id,(int)$message['Versione']),'Eliminazione concorrente ammessa');
}finally{$other->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
foreach(['in_corso','da_verificare','inviato'] as $state){
 $p->prepare('UPDATE email_invii SET Stato=? WHERE MessaggioID=?')->execute([$state,$id]);
 $before=dc_rows($p,'email_invii','WHERE MessaggioID=?',[$id]);
 ea_blocked(fn()=>em_save_draft($p,$input),'Modifica con esito '.$state.' ammessa');
 ea_check($before===dc_rows($p,'email_invii','WHERE MessaggioID=?',[$id]),'Registro alterato dopo modifica rifiutata');
}
// Una verifica non invia email e conserva gli esiti non definitivi.
$p->prepare("UPDATE email_invii SET Stato='da_verificare' WHERE MessaggioID=?")->execute([$id]);
foreach(['unknown','uncertain','unexpected'] as $status){
 $bridge=static function($c,$key,$payload)use($status){ea_check($payload['op']==='status','La verifica invia email');return ['status'=>$status];};
 ea_check(em_reconcile($p,$id,$bridge)===0,'Esito indefinito risolto');
 ea_check(dc_rows($p,'email_invii','WHERE MessaggioID=?',[$id])[0]['Stato']==='da_verificare','Reinvio autorizzato da esito indefinito');
}
$failed=static function(){throw new RuntimeException('Timeout simulato');};
ea_blocked(fn()=>em_reconcile($p,$id,$failed),'Timeout ignorato');
ea_check(em_reconcile($p,$id,static fn()=>['status'=>'not_sent'])===1,'Mancato invio non recuperato');
$job=dc_rows($p,'email_invii','WHERE MessaggioID=?',[$id])[0];
ea_check($job['Stato']==='da_inviare'&&$job['IniziatoIl']===null,'Mancato invio non pronto al tentativo manuale');
ea_check(em_send_batch($p,$id,$fake)['sent']===1,'Tentativo manuale non disponibile');
// Una conferma già registrata prevale su una risposta contraddittoria del ponte.
$p->prepare("UPDATE email_invii SET Stato='da_verificare',ConfermatoIl=UTC_TIMESTAMP() WHERE MessaggioID=?")->execute([$id]);
ea_check(em_reconcile($p,$id,static fn()=>['status'=>'not_sent'])===0,'Invio con prova di ricezione rimesso in coda');
ea_check(em_reconcile($p,$id,$fake)===1,'Invio confermato non recuperato');
// Le campagne preparate e mai tentate restano modificabili.
$editable=em_save_draft($p,['subject'=>'MODIFICABILE '.$tag,'text'=>'Testo','html'=>'<p>Testo</p>','mail_ids'=>[$contact['ID']]]);
em_prepare($p,$editable);$version=dc_find($p,'email_messaggi',['ID'=>$editable])['Versione'];
em_save_draft($p,array_merge($input,['id'=>$editable,'version'=>$version]));
ea_check(dc_find($p,'email_messaggi',['ID'=>$editable])['Stato']==='bozza'&&!dc_rows($p,'email_invii','WHERE MessaggioID=?',[$editable]),'Campagna mai inviata non modificabile');
// Più di 500 righe: il filtro e il PDF devono raggiungere anche le ultime.
$large=(int)dc_insert($p,'email_messaggi',['Oggetto'=>'OLTRE 500 '.$tag,'HTML'=>'<p>Testo</p>','Testo'=>'Testo','Stato'=>'preparato','ConfigID'=>$config['ID'],'ConfermaLettura'=>1]);
$p->beginTransaction();
try{
 for($i=0;$i<525;$i++){
  $dest=(int)dc_insert($p,'email_destinatari',['MessaggioID'=>$large,'MailOrigineID'=>$contact['ID'],'Nome'=>$i===524?'OLTRE500':'Destinatario '.$i,'Email'=>'regressione.'.$tag.'.'.$i.'@example.invalid','Tipo'=>'ASSOCIAZIONE','Ente'=>'Ente '.$i]);
  $job=bin2hex(random_bytes(16));dc_insert($p,'email_invii',['ID'=>$job,'MessaggioID'=>$large,'DestinatarioID'=>$dest,'EmailEffettiva'=>'regressione.'.$tag.'.'.$i.'@example.invalid','Stato'=>'da_verificare','CodiceHash'=>hash('sha256',em_token($job)),'ScadeIl'=>'2099-01-01 00:00:00','IniziatoIl'=>gmdate('Y-m-d H:i:s',time()-600-$i)]);
 }
 $p->commit();
}catch(Throwable $e){$p->rollBack();throw $e;}
$logs=em_logs($p,$large);ea_check(count($logs)===525&&$logs[524]['Nome']==='OLTRE500','Esiti ancora troncati');
// Verifica anche oltre il precedente limite di 20, senza spedire.
$calls=0;$status=static function($c,$key,$payload)use(&$calls){ea_check($payload['op']==='status','Verifica spedisce email');$calls++;return ['status'=>'sent'];};
ea_check(em_reconcile($p,$large,$status)===525&&$calls===525,'Esiti non verificati integralmente');
$p->prepare("UPDATE email_invii SET Stato='da_verificare' WHERE MessaggioID=?")->execute([$large]);
$fixtureIds=[$id,$editable,$large];
$campaigns=em_campaigns($p);$write=true;$_GET=['id'=>$large];
ob_start();include dirname(__DIR__).'/includes/email_archivio.php';$markup=ob_get_clean();
ea_check(str_contains($markup,'value="status"')&&str_contains($markup,'Recupera esiti dal ponte'),'Comando di recupero assente');
$write=false;ob_start();include dirname(__DIR__).'/includes/email_archivio.php';$markup=ob_get_clean();
ea_check(!str_contains($markup,'value="status"'),'Comando di recupero con permesso di sola lettura');
// Render del report completo e filtrato attraverso la stessa pagina usata dal sito.
user_login('admin','1234567.');$_GET=['id'=>$large];
ob_start();include dirname(__DIR__).'/email_report.php';$pdf=ob_get_clean();
ea_check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'Risultati: 525 di 525')&&str_contains($pdf,'OLTRE500'),'Report PDF incompleto');
$_GET=['id'=>$large,'esiti_q'=>'OLTRE500'];
ob_start();include dirname(__DIR__).'/email_report.php';$pdf=ob_get_clean();
ea_check(str_contains($pdf,'Risultati: 1 di 525')&&str_contains($pdf,'OLTRE500'),'Filtro del PDF applicato dopo un taglio');
// Rimuove solo le fixture di questa prova; nessuna anagrafica viene modificata.
foreach($fixtureIds as $fixture){
 $p->prepare("UPDATE email_invii SET Stato='inviato' WHERE MessaggioID=?")->execute([$fixture]);
 em_delete($p,$fixture,(int)dc_find($p,'email_messaggi',['ID'=>$fixture])['Versione']);
}
echo 'OK: regressioni email '.$tag.', lock concorrenti, recupero esiti, report oltre 500, permessi e aggiornamento schema esplicito. Nessun invio reale.'.PHP_EOL;
