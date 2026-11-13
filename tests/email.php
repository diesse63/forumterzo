<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';require dirname(__DIR__).'/includes/email.php';
function mail_check(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$p=poimanager_db();em_install($p);em_install($p);mail_check(em_ready($p),'Schema email');
$oldConfig=em_config($p);mail_check($oldConfig===null,'Configurazione iniziale inattesa');
$secret=str_repeat('a',64);
$configId=em_save_config($p,['url'=>'https://script.google.com/macros/s/TEST_DEPLOYMENT/exec','sender'=>'sender@example.invalid','sender_name'=>'Prova','mode'=>'test','test_email'=>'test@example.invalid','batch'=>1,'secret'=>$secret]);
$c=em_config($p);
$fake=function($config,$key,$payload){
 if($payload['op']==='ping')return ['ok'=>true,'sender'=>$config['Mittente'],'quota'=>100];
 return ['ok'=>true,'sender'=>$config['Mittente'],'status'=>'sent'];
};
em_ping($p,$c,$fake);$c=em_config($p);
$ownerIds=$p->query('SELECT ID FROM Associazioni ORDER BY ID LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
$insert=$p->prepare("INSERT INTO Mail (TipoSoggetto,IDSoggetto,Indirizzo,PEC,Predefinito) VALUES ('ASSOCIAZIONE',?,?,0,0)");
$insert->execute([$ownerIds[0],'email-a@example.invalid']);$insert->execute([$ownerIds[0],'email-b@example.invalid']);$insert->execute([$ownerIds[1],'EMAIL-A@example.invalid']);
$rows=em_contacts($p);mail_check(count($rows)>0,'Contatti sorgenti assenti');
$ids=array_column($rows,'ID');$sources=[];
foreach(['Associazioni','Soggetti','Mail','Telefono','MailList','ListaMail'] as $t)$sources[$t]=$p->query('SELECT * FROM '.$t)->fetchAll();
$clean=em_sanitize('<p onclick="evil()">Ciao {nome}<script>evil()</script><img src="x"><a href="javascript:evil()">x</a><b>Bene</b></p>');
mail_check(!str_contains($clean,'script')&&!str_contains($clean,'onclick')&&!str_contains($clean,'img')&&!str_contains($clean,'javascript:')&&str_contains($clean,'<b>Bene</b>'),'Sanificazione');
$id=em_save_draft($p,['subject'=>'Prova','text'=>'Ciao {nome}','html'=>'<p>Ciao {nome}</p>','mail_ids'=>$ids]);
$number=em_prepare($p,$id);mail_check($number<count($ids),'Indirizzi duplicati non accorpati');mail_check($number>0&&em_prepare($p,$id)===$number,'Preparazione idempotente');
$jobs=$p->query('SELECT * FROM email_invii WHERE MessaggioID='.$id)->fetchAll();
foreach($jobs as $job)mail_check($job['EmailEffettiva']==='test@example.invalid','Modalità test non isola indirizzi');
$newId=em_save_config($p,['url'=>'https://script.google.com/macros/s/NEW_DEPLOYMENT/exec','sender'=>'new@example.invalid','mode'=>'test','test_email'=>'newtest@example.invalid','batch'=>20,'secret'=>str_repeat('b',64)]);
mail_check((int)$p->query('SELECT ConfigID FROM email_messaggi WHERE ID='.$id)->fetchColumn()===$configId,'Campagna cambia configurazione');
$sentPayloads=[];
$sender=function($config,$key,$payload)use(&$sentPayloads,$fake,$configId){
 mail_check((int)$config['ID']===$configId,'Invio usa configurazione nuova');
 if($payload['op']==='send'){$sentPayloads[]=$payload;mail_check($payload['to']==='test@example.invalid','Destinatario reale raggiunto in test');mail_check(str_contains($payload['subject'],'[TEST]'),'Prefisso test');}
 return $fake($config,$key,$payload);
};
em_send_batch($p,$id,$sender);mail_check(count($sentPayloads)===1,'Limite lotto non rispettato');
$first=$jobs[0];$token=em_token($first['ID']);mail_check(!$p->query("SELECT ConfermatoIl FROM email_invii WHERE ID='".$first['ID']."'")->fetchColumn(),'Conferma anticipata');
mail_check(em_confirm($p,$token)&&em_confirm($p,$token),'Conferma non idempotente');mail_check(!em_confirm($p,str_repeat('0',64)),'Token sconosciuto accettato');
$blocked=false;try{em_save_draft($p,['id'=>$id,'version'=>1,'subject'=>'Modifica','text'=>'Modifica','html'=>'<p>Modifica</p>','mail_ids'=>$ids]);}catch(RuntimeException $e){$blocked=true;}mail_check($blocked,'Messaggio preparato modificabile');
if($number>1){
 $calls=0;$uncertain=function($config,$key,$payload)use(&$calls,$fake){if($payload['op']==='send'){$calls++;throw new RuntimeException('Timeout simulato');}return $fake($config,$key,$payload);};
 em_send_batch($p,$id,$uncertain);mail_check($calls===1,'Invio incerto ripetuto nello stesso lotto');
 $q=$p->prepare("SELECT COUNT(*) FROM email_invii WHERE MessaggioID=? AND Stato='da_verificare'");$q->execute([$id]);mail_check((int)$q->fetchColumn()===1,'Esito incerto non conservato');
 em_reconcile($p,$id,$fake);$q->execute([$id]);mail_check((int)$q->fetchColumn()===0,'Riconciliazione non riuscita');
}
foreach($sources as $t=>$before)mail_check($before===$p->query('SELECT * FROM '.$t)->fetchAll(),'Tabella attuale modificata: '.$t);
$transport=function($url,$method,$body)use($secret){
 if($method==='POST')return ['code'=>302,'location'=>'https://script.googleusercontent.com/macros/echo?key=mock','body'=>''];
 mail_check($body==='','Corpo segreto inoltrato al redirect');
 return ['code'=>200,'location'=>'','body'=>'{}'];
};
$rejected=false;try{em_bridge_call($c,$secret,['op'=>'ping'],$transport);}catch(RuntimeException $e){$rejected=true;}mail_check($rejected,'Risposta non firmata accettata');
$captured=null;
$good=function($url,$method,$body)use($secret,&$captured){
 if($method==='POST'){$env=json_decode($body,true);mail_check(hash_equals(hash_hmac('sha256',$env['data'],$secret),$env['signature']),'Firma richiesta');$captured=json_decode(base64_decode($env['data']),true);return ['code'=>302,'location'=>'https://script.googleusercontent.com/macros/echo?key=mock','body'=>''];}
 mail_check($body==='','Credenziale nel redirect');$data=base64_encode(json_encode(['ok'=>true,'sender'=>'sender@example.invalid','nonce'=>$captured['nonce'],'quota'=>100]));return ['code'=>200,'location'=>'','body'=>json_encode(['data'=>$data,'signature'=>hash_hmac('sha256',$data,$secret)])];
};
mail_check(em_bridge_call($c,$secret,['op'=>'ping'],$good)['quota']===100,'Protocollo firmato');
$bad=false;try{em_bridge_url('https://example.com/exec');}catch(RuntimeException $e){$bad=true;}mail_check($bad,'Origine non Google ammessa');
$nonce=em_send_nonce();em_consume_send_nonce($nonce);$bad=false;try{em_consume_send_nonce($nonce);}catch(RuntimeException $e){$bad=true;}mail_check($bad,'Doppio invio nonce');
echo "OK: tabelle separate, bozze, configurazioni congelate, test isolato, lotti, esiti incerti, firme e conferme. Nessuna email reale inviata.\n";

$userId=(int)$p->query("SELECT ID FROM Utenti WHERE Ruolo='user' LIMIT 1")->fetchColumn();
mail_check(!allowed(['ID'=>$userId,'Ruolo'=>'user'],'gestione_email'),'Nuova voce concessa automaticamente');
$p->prepare("INSERT INTO UtentiPermessi (UtenteID,Pannello,Livello) VALUES (?,'gestione_email','lettura')")->execute([$userId]);
