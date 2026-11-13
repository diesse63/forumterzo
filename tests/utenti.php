<?php
declare(strict_types=1);
// Solo CI su database effimero. Non eseguire sul sito.
if (getenv('POIMANAGER_TEST') !== '1') exit(1);
function ensure(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$base=dirname(__DIR__);
file_put_contents($base.'/private/config.php',"<?php return ".var_export([
 'dsn'=>'mysql:host=127.0.0.1;port=3306;dbname=poimanager_test;charset=utf8mb4',
 'user'=>'root','password'=>'test_only_password','admin_password'=>'TestSetupPassword!123',
 'private_dir'=>$base.'/importazione_sqlite/data','max_bytes'=>20971520
],true).";");
file_put_contents($base.'/private/security.php',"<?php return ".var_export([
 'key'=>base64_encode(random_bytes(32)),'development'=>false,'ghost_enabled'=>true,
 'ghost_hash'=>password_hash('123',PASSWORD_DEFAULT)
],true).";");
require $base.'/includes/auth.php';
require $base.'/importazione_sqlite/motore.php';
$db=poimanager_db();
// Verifica la migrazione del vecchio schema senza leggere i vecchi dati.
$db->exec("CREATE TABLE Utenti (ID BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,Nome LONGTEXT,Email VARBINARY(768),Password LONGTEXT,Amministratore BIGINT DEFAULT 0)");
$db->exec("INSERT INTO Utenti (Nome,Password) VALUES ('legacy cifrato','non decifrare')");
$db->exec(file_get_contents($base.'/utenti/migrazione_v2.sql'));
ensure((int)$db->query('SELECT COUNT(*) FROM Utenti_legacy_v1')->fetchColumn()===1,'Legacy non conservato');
ensure((int)$db->query('SELECT COUNT(*) FROM Utenti')->fetchColumn()===0,'Legacy importato');
$schema=file_get_contents($base.'/importazione_sqlite/struttura_solo_mysql8.sql');
$a=strpos($schema,'CREATE TABLE Utenti (');$b=strpos($schema,'CREATE TABLE `Report`',$a);
ensure($a!==false&&$b!==false,'Blocco schema assente');
$db->exec(substr($schema,0,$a).substr($schema,$b));
foreach(['123456.','1234567.','Abcdef!'] as $p)password_policy($p);
foreach(['123','1234567','abcdef '] as $p){$rejected=false;try{password_policy($p);}catch(RuntimeException $e){$rejected=true;}ensure($rejected,'Password debole accettata');}
$profile=['nome'=>'user','email'=>'test@example.invalid'];$cipher=user_encrypt($profile);
ensure(user_decrypt($cipher)===$profile,'Cifratura non reversibile');
ensure(!str_contains($cipher,'example'),'Profilo in chiaro');
$raw=base64_decode($cipher);$raw[30]=chr(ord($raw[30])^1);$rejected=false;
try{user_decrypt(base64_encode($raw));}catch(RuntimeException $e){$rejected=true;}ensure($rejected,'Manomissione non rilevata');
$q=$db->prepare('INSERT INTO Utenti (NomeIndice,ProfiloCifrato,PasswordHash,Ruolo,Attivo) VALUES (?,?,?,?,?)');
foreach([['admin','admin',1],['user','user',1],['esternobase','esternobase',0],['esternoevoluto','esternoevoluto',0]] as [$name,$role,$active])
 $q->execute([user_index($name),user_encrypt(['nome'=>$name,'email'=>'']),password_hash('1234567.',PASSWORD_DEFAULT),$role,$active]);
$db->exec("INSERT INTO Pannelli VALUES ('utenti','Utenti'),('associazioni','Associazioni'),('mail','Mail'),('importazione','Importazione')");
$uid=(int)$db->query("SELECT ID FROM Utenti WHERE Ruolo='user'")->fetchColumn();
$u=['ID'=>$uid,'Ruolo'=>'user'];ensure(!allowed($u,'associazioni'),'Default non nega');
$db->exec("INSERT INTO UtentiPermessi VALUES ($uid,'associazioni','lettura'),($uid,'mail','scrittura'),($uid,'utenti','scrittura')");
ensure(allowed($u,'associazioni')&&!allowed($u,'associazioni','scrittura'),'Lettura non rispettata');
ensure(allowed($u,'mail','scrittura')&&!allowed($u,'utenti'),'Escalation permessi utenti');
ensure(!allowed($u,'importazione','scrittura'),'User può importare');
ensure(allowed(['ID'=>1,'Ruolo'=>'admin'],'utenti','scrittura'),'Admin bloccato');
ensure(!allowed(['ID'=>3,'Ruolo'=>'esternobase'],'mail'),'Esterno abilitato');
$rejected=false;try{$db->exec("UPDATE Utenti SET Attivo=1 WHERE Ruolo='esternobase'");}catch(PDOException $e){$rejected=true;}ensure($rejected,'CHECK esterno mancante');
user_login('user','1234567.');ensure(current_user()['ID']===$uid,'Login non valido');
$db->exec("UPDATE Utenti SET VersioneSessione=VersioneSessione+1 WHERE ID=$uid");ensure(current_user()===null,'Sessione non invalidata');
foreach(['esternobase','esternoevoluto','ghost'] as $name){
 $rejected=false;try{user_login($name,$name==='ghost'?'123':'1234567.');}catch(RuntimeException $e){$rejected=true;}
 ensure($rejected,'Account inattivo o ghost produzione ammesso');
}
$map=mapping();ensure(!isset($map['tables']['Utenti']),'Utenti nel contratto importabile');
$path=tempnam(sys_get_temp_dir(),'sqlite_');$s=new PDO('sqlite:'.$path);
foreach($map['tables'] as $spec)$s->exec($spec['source_sql']);
$s->exec('CREATE TABLE Utenti (ID INTEGER PRIMARY KEY, Nome TEXT, Email TEXT, Password TEXT, Amministratore INTEGER)');
$s->exec("INSERT INTO Utenti VALUES (1,'cipher','cipher','cipher',1)");$s=null;
$src=source($path,$map);$counts=scan($src,$map);ensure(!isset($counts['Utenti']),'Legacy conteggiato');
$before=$db->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll();
inspectTarget($db,$map);importData($db,$src,$map,$counts);
ensure($before===$db->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll(),'Importazione modifica nuovi utenti');
ensure((int)$db->query('SELECT COUNT(*) FROM UtentiPermessi')->fetchColumn()===3,'Permessi persi');
$src=null;unlink($path);
echo "OK: migrazione, policy password, cifratura, ruoli, permessi, sessioni e importazione con utenti legacy ignorati.\n";
