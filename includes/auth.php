<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';
require_once __DIR__.'/crypto.php';
ini_set('display_errors','0');
if (session_status() !== PHP_SESSION_ACTIVE) {
 session_name('poimanager');
 session_set_cookie_params(['httponly'=>true,'secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'samesite'=>'Strict']);
 session_start();
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
function uh($s): string {return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function security(): array {
 static $c=null;
 if($c!==null)return $c;
 $file=dirname(__DIR__).'/private/security.php';
 if(!is_file($file))throw new RuntimeException('Completare prima installa_utenti.php.');
 if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
 $c=require $file;
 if(!is_array($c)||strlen(base64_decode($c['key']??'',true)?:'')!==32)throw new RuntimeException('Chiave utenti non valida.');
 return $c;
}
function user_key(): string {return base64_decode(security()['key'],true);}
function user_index(string $name): string {return hash_hmac('sha256',strtolower(trim($name)),user_key());}
function user_encrypt(array $profile): string {
 return profile_encrypt($profile,user_key(),security()['cipher']??'sodium');
}
function user_decrypt(string $cipher): array {
 return profile_decrypt($cipher,user_key());
}
function password_policy(string $p): void {
 if(strlen($p)>72 || preg_match('/^.{7,}$/us',$p)!==1 || preg_match('/[^\p{L}\p{N}\s]/u',$p)!==1)
  throw new RuntimeException('Password: almeno 7 caratteri, uno speciale; massimo 72 byte.');
}
function csrf(): string {return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function check_csrf(): void {
 if(!is_string($_POST['csrf']??null)||!hash_equals(csrf(),$_POST['csrf']))throw new RuntimeException('Sessione scaduta. Ricaricare.');
}
function current_user(): ?array {
 $a=$_SESSION['account']??null;
 if(!$a)return null;
 if(time()-($a['time']??0)>1800){unset($_SESSION['account']);return null;}
 if(($a['kind']??'')==='ghost'){
  $s=security();
  if(empty($s['development'])||empty($s['ghost_enabled'])||!hash_equals($a['version'],hash('sha256',$s['ghost_hash']))){unset($_SESSION['account']);return null;}
  $_SESSION['account']['time']=time();
  return ['ID'=>0,'Ruolo'=>'ghost','Attivo'=>1,'Profilo'=>['nome'=>'ghost','email'=>'']];
 }
 $q=poimanager_db()->prepare('SELECT * FROM Utenti WHERE ID=?');$q->execute([$a['id']]);$u=$q->fetch();
 if(!$u||!(int)$u['Attivo']||!in_array($u['Ruolo'],['admin','user'],true)||(int)$u['VersioneSessione']!==(int)$a['version']){unset($_SESSION['account']);return null;}
 $u['Profilo']=user_decrypt($u['ProfiloCifrato']);$_SESSION['account']['time']=time();return $u;
}
function allowed(array $u,string $panel,string $mode='lettura'): bool {
 if(!in_array($mode,['lettura','scrittura'],true))return false;
 if(in_array($u['Ruolo'],['admin','ghost'],true))return true;
 if($u['Ruolo']!=='user'||$panel==='utenti'||$panel==='importazione')return false;
 $q=poimanager_db()->prepare('SELECT Livello FROM UtentiPermessi WHERE UtenteID=? AND Pannello=?');$q->execute([$u['ID'],$panel]);$v=$q->fetchColumn();
 return $v==='scrittura'||($mode==='lettura'&&$v==='lettura');
}
function require_access(string $panel,string $mode='lettura'): array {
 $u=current_user();
 if(!$u){header('Location: /login.php');exit;}
 if(!allowed($u,$panel,$mode)){http_response_code(403);exit('Accesso non autorizzato.');}
 return $u;
}
function user_login(string $name,string $password): void {
 $db=poimanager_db();$s=security();
 // Chiave per IP: nessun nome utente né account ghost nella tabella tentativi.
 $rate=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),user_key());
 $q=$db->prepare('SELECT Tentativi,Ultimo FROM AccessiTentativi WHERE Chiave=?');$q->execute([$rate]);$r=$q->fetch();
 if($r&&(int)$r['Tentativi']>=5&&time()-(int)$r['Ultimo']<900)throw new RuntimeException('Troppi tentativi. Attendere 15 minuti.');
 $name=strtolower(trim($name));$account=null;
 if($name==='ghost'&&!empty($s['development'])&&!empty($s['ghost_enabled'])&&password_verify($password,$s['ghost_hash'])){
  $account=['kind'=>'ghost','version'=>hash('sha256',$s['ghost_hash'])];
 }else{
  $q=$db->prepare('SELECT * FROM Utenti WHERE NomeIndice=?');$q->execute([user_index($name)]);$u=$q->fetch();
  // Hash fittizio per rendere costoso anche un nome inesistente.
  $valid=password_verify($password,$u['PasswordHash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
  if($valid&&$u&&(int)$u['Attivo']===1&&in_array($u['Ruolo'],['admin','user'],true))$account=['kind'=>'db','id'=>(int)$u['ID'],'version'=>(int)$u['VersioneSessione']];
 }
 if(!$account){
  $q=$db->prepare('INSERT INTO AccessiTentativi (Chiave,Tentativi,Ultimo) VALUES (?,1,?) ON DUPLICATE KEY UPDATE Tentativi=IF(Ultimo < ?,1,Tentativi+1),Ultimo=?');
  $q->execute([$rate,time(),time()-900,time()]);throw new RuntimeException('Credenziali non valide o account non attivo.');
 }
 $q=$db->prepare('DELETE FROM AccessiTentativi WHERE Chiave=?');$q->execute([$rate]);
 session_regenerate_id(true);$_SESSION=['account'=>$account+['time'=>time()],'csrf'=>bin2hex(random_bytes(32))];
}
require_once __DIR__.'/layout.php';
function page_start(string $title, string $description='', array $context=[]): void {
 layout_start($title,$description,$context);
}
