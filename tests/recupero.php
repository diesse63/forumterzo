<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/recupero_utenti.php';
require dirname(__DIR__).'/includes/email.php';
function ru_test(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function ru_rejected(callable $f,string $why):void{$bad=false;try{$f();}catch(RuntimeException $e){$bad=true;}ru_test($bad,$why);}
$root=new PDO('mysql:host=127.0.0.1;port=3306;charset=utf8mb4','root','test_only_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$root->exec('CREATE DATABASE poimanager_recovery_test');
$p=new PDO('mysql:host=127.0.0.1;port=3306;dbname=poimanager_recovery_test;charset=utf8mb4','root','test_only_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$private=sys_get_temp_dir().'/recovery_'.bin2hex(random_bytes(8));mkdir($private,0700);
$token=bin2hex(random_bytes(32));
$write=function(string $name,array $cfg)use($private):void{file_put_contents($private.'/'.$name,"<?php return ".var_export($cfg,true).";");};
try{
 $p->exec('CREATE TABLE Utenti (ID BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY)');
 $p->exec(file_get_contents(dirname(__DIR__).'/utenti/migrazione_v2.sql'));
 $p->exec("INSERT INTO Utenti (ID,NomeIndice,ProfiloCifrato,PasswordHash,Ruolo) VALUES (7,REPEAT('a',64),'vecchio profilo cifrato','vecchio hash','admin'),(8,REPEAT('b',64),'secondo profilo cifrato','secondo hash','user')");
 $p->exec("INSERT INTO Pannelli VALUES ('associazioni','Enti')");
 $p->exec("INSERT INTO UtentiPermessi VALUES (8,'associazioni','lettura')");
 $p->exec("CREATE TABLE EntiTest (ID BIGINT PRIMARY KEY,Nome TEXT) ENGINE=InnoDB");
 $p->exec("INSERT INTO EntiTest VALUES (1,'dato gestionale da conservare')");
 $oldUsers=$p->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll();$oldPerm=$p->query('SELECT * FROM UtentiPermessi')->fetchAll();
 ru_rejected(fn()=>ru_authorize($private,$token),'Recupero consentito senza file privato');
 $write('recupero_utenti.php',['token'=>$token]);
 ru_rejected(fn()=>ru_recover($p,$private,str_repeat('0',64),'NuovaPass!7'),'Token errato accettato');
 ru_rejected(fn()=>ru_recover($p,$private,$token,'1234567'),'Password debole accettata');
 $report=ru_inspect($p,$private);
 ru_test($report===['utenti'=>2,'permessi'=>1,'cifratura'=>'non avviata'],'Diagnostica iniziale');
 ru_test(!is_file($private.'/security.php')&&$p->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll()===$oldUsers,'Diagnostica con scritture');
 $p->exec("CREATE TABLE crypto_migrazione (ID INT PRIMARY KEY,Stato VARCHAR(20),ChiaveHash CHAR(64)) ENGINE=InnoDB");
 $keys=['key'=>base64_encode(random_bytes(32)),'index_key'=>base64_encode(random_bytes(32)),'cipher'=>profile_choose_cipher()];
 $q=$p->prepare("INSERT INTO crypto_migrazione VALUES (1,'pronto',?)");$q->execute([hash('sha256',$keys['key'].'|'.$keys['index_key'])]);
 ru_rejected(fn()=>ru_recover($p,$private,$token,'NuovaPass!7'),'Chiave dati perduta non blocca');
 $write('dati_security.php',$keys+['version'=>1]);$dataFile=file_get_contents($private.'/dati_security.php');
 $p->exec("UPDATE crypto_migrazione SET ChiaveHash=REPEAT('0',64)");
 ru_rejected(fn()=>ru_recover($p,$private,$token,'NuovaPass!7'),'Chiave dati errata non blocca');
 $q=$p->prepare('UPDATE crypto_migrazione SET ChiaveHash=?');$q->execute([hash('sha256',$keys['key'].'|'.$keys['index_key'])]);
 // Una dipendenza non mappata deve impedire qualsiasi sostituzione account.
 $p->exec('CREATE TABLE Collegata (UID BIGINT,FOREIGN KEY(UID) REFERENCES Utenti(ID)) ENGINE=InnoDB');
 ru_rejected(fn()=>ru_recover($p,$private,$token,'NuovaPass!7'),'FK esterna ignorata');
 $p->exec('DROP TABLE Collegata');
 // Errore prima della scrittura chiave: rollback completo anche dopo copia in archivio.
 $p->exec("CREATE TRIGGER no_admin BEFORE INSERT ON Utenti FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='errore test'");
 ru_rejected(fn()=>ru_recover($p,$private,$token,'NuovaPass!7'),'Errore inserimento ignorato');
 ru_test($p->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll()===$oldUsers&&!is_file($private.'/security.php'),'Rollback non completo');
 $p->exec('DROP TRIGGER no_admin');
 $result=ru_recover($p,$private,$token,'NuovaPass!7');
 ru_test($p->query('SELECT * FROM '.dc_q($result['archivio_utenti']).' ORDER BY ID')->fetchAll()===$oldUsers,'Archivio utenti non integro');
 ru_test($p->query('SELECT * FROM '.dc_q($result['archivio_permessi']))->fetchAll()===$oldPerm,'Archivio permessi non integro');
 ru_test((int)$p->query('SELECT COUNT(*) FROM UtentiPermessi')->fetchColumn()===0,'Permessi preesistenti assegnati');
 $u=$p->query('SELECT * FROM Utenti')->fetch();$s=require $private.'/security.php';$key=base64_decode($s['key'],true);
 ru_test((int)$u['ID']>8&&(int)$u['VersioneSessione']>=1000000000&&$u['Ruolo']==='admin','Identità o sessione nuova non valida');
 ru_test($u['NomeIndice']===hash_hmac('sha256','admin',$key)&&profile_decrypt($u['ProfiloCifrato'],$key)===['nome'=>'admin','email'=>'']&&password_verify('NuovaPass!7',$u['PasswordHash']),'Nuovo amministratore non autenticabile');
 ru_test(!$s['development']&&!$s['ghost_enabled']&&!is_file($private.'/recupero_utenti.php'),'Ghost o credenziale temporanea ancora attivi');
 ru_test(file_get_contents($private.'/dati_security.php')===$dataFile&&$p->query('SELECT Nome FROM EntiTest')->fetchColumn()==='dato gestionale da conservare','Dati o chiave gestionale modificati');
 $beforeKey=file_get_contents($private.'/security.php');
 ru_rejected(fn()=>ru_recover($p,$private,$token,'AltraPass!7'),'Secondo recupero consentito');
 ru_test(file_get_contents($private.'/security.php')===$beforeKey,'Chiave sovrascritta');
 // Contenuto email rifiutato prima del claim se usa un vecchio hash conferma.
 ru_rejected(fn()=>em_payload(['ID'=>str_repeat('1',32),'CodiceHash'=>str_repeat('0',64)],[]),'Token conferma incoerente spedibile');
 // Residui cifrati senza registro devono bloccare anche se le chiavi non sono disponibili.
 unlink($private.'/security.php');$p->exec('DROP TABLE crypto_migrazione');
 $p->exec("CREATE TABLE Residuo (ID INT,_Cifrato TEXT)");$p->exec("INSERT INTO Residuo VALUES (1,'dc1:test')");
 ru_rejected(fn()=>ru_inspect($p,$private),'Cifrato senza registro ignorato');
 echo "OK: recupero protetto, diagnosi senza scritture, chiavi mancanti/errate, FK, rollback, archivi integri, nuovo admin, sessioni, dati conservati e blocco vecchi token email.\n";
}finally{
 foreach(glob($private.'/*') as $f)unlink($f);rmdir($private);
 $root->exec('DROP DATABASE poimanager_recovery_test');
}
