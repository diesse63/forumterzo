<?php
declare(strict_types=1);
ini_set('display_errors','0');
header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
require_once dirname(__DIR__).'/includes/auth.php';
require_access('importazione','scrittura');
$_SESSION['authenticated']=time();
require_once dirname(__DIR__).'/includes/database.php';
require __DIR__.'/motore.php';
require __DIR__.'/deposito.php';
function h($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$_SESSION['csrf']??=bin2hex(random_bytes(32));$message='';$report=null;$error=false;
try{
 $cfg=poimanager_config();

 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($_SESSION['csrf'],$_POST['csrf']??''))throw new RuntimeException('Sessione scaduta: ricaricare la pagina.');
  $action=$_POST['action']??'';
  if($action==='login'){
   if(time()<($_SESSION['retry_after']??0))throw new RuntimeException('Attendere prima di riprovare.');
   if(!hash_equals($cfg['admin_password'],$_POST['password']??'')){$_SESSION['retry_after']=time()+5;throw new RuntimeException('Password non corretta.');}
   session_regenerate_id(true);$_SESSION['authenticated']=time();
  }else{
   if(empty($_SESSION['authenticated'])||time()-$_SESSION['authenticated']>1800)throw new RuntimeException('Accedere nuovamente.');
   $_SESSION['authenticated']=time();
   if($action==='logout'){
    if(isset($_SESSION['upload']))@unlink($_SESSION['upload']['path']);
    $_SESSION=[];session_destroy();header('Location: index.php');exit;
   }
   $dir=import_private_dir($cfg['private_dir']);
   $map=mapping();$p=db($cfg);
   if($action==='analyse'){
    if(isset($_SESSION['upload'])){@unlink($_SESSION['upload']['path']);unset($_SESSION['upload']);}
    $u=$_FILES['sqlite']??null;
    if(!$u||$u['error']!==UPLOAD_ERR_OK||$u['size']>$cfg['max_bytes'])throw new RuntimeException('Caricamento fallito o file troppo grande. Verificare anche upload_max_filesize e post_max_size.');
    $path=$dir.'/sqlite_'.bin2hex(random_bytes(20)).'.db';
    if(!move_uploaded_file($u['tmp_name'],$path))throw new RuntimeException('Impossibile acquisire il file.');chmod($path,0600);
    try{$s=source($path,$map);inspectTarget($p,$map);$counts=scan($s,$map);}catch(Throwable $e){@unlink($path);throw $e;}
    $_SESSION['upload']=['path'=>$path,'sha256'=>hash_file('sha256',$path),'map'=>hash_file('sha256',__DIR__.'/mappa_v2.json'),'time'=>time(),'counts'=>$counts];
    $report=$counts;$message='Analisi completata. Nessun dato scritto. Controllare i conteggi e avviare l’importazione.';
   }elseif($action==='import'){
    $u=$_SESSION['upload']??null;
    if(!$u||time()-$u['time']>1800)throw new RuntimeException('Analisi scaduta: caricare nuovamente il file.');
    if(!hash_equals($u['sha256'],hash_file('sha256',$u['path']))||!hash_equals($u['map'],hash_file('sha256',__DIR__.'/mappa_v2.json')))throw new RuntimeException('File o mappatura cambiati. Ripetere l’analisi.');
    $s=source($u['path'],$map);$counts=scan($s,$map);$report=importData($p,$s,$map,$counts);
    $log=['date'=>gmdate('c'),'mapping'=>$map['version'],'source_sha256'=>$u['sha256'],'counts'=>$report,'outcome'=>'COMMIT'];
    $logged=file_put_contents($dir.'/importazioni.jsonl',json_encode($log,JSON_THROW_ON_ERROR)."\n",FILE_APPEND|LOCK_EX)!==false;
    $s=null;@unlink($u['path']);unset($_SESSION['upload']);$message='Importazione completata e dati verificati: '.array_sum($report).' record.'.($logged?'':' Il registro su file non è stato salvato.');
   }
  }
 }
}catch(Throwable $e){$error=true;$message=$e instanceof PDOException?'Operazione interrotta per errore database (codice '.h($e->getCode()).'). Nessun dato parziale viene confermato durante l’importazione; verificare struttura, permessi e vincoli.':$e->getMessage();}
$loggedIn=!empty($_SESSION['authenticated'])&&time()-$_SESSION['authenticated']<=1800;
?>
<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Importazione SQLite</title><style>body{font:17px system-ui;background:#eef3f8;color:#172b41;max-width:900px;margin:40px auto;padding:20px}main{background:white;padding:30px;border-radius:16px}button{padding:12px 20px;background:#185b8c;color:white;border:0;border-radius:7px;cursor:pointer}input{padding:12px;max-width:100%;box-sizing:border-box}table{border-collapse:collapse;width:100%;margin:20px 0}td,th{text-align:left;padding:8px;border-bottom:1px solid #ccd}aside{padding:16px;background:#e7f3ff;margin:20px 0}.error{background:#ffe8e8}form{margin:20px 0}</style><main><h1>Importazione da SQLite</h1><p>Utenti SQLite ignorati · Mappatura versione <?=h(mapping()['version'])?> · Importazione in tabelle vuote</p>
<?php if($message):?><aside class="<?=$error?'error':''?>"><?=h($message)?></aside><?php endif;?>
<?php if(!$loggedIn):?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="login"><label>Password amministrativa <input type="password" name="password" required autocomplete="current-password"></label> <button>Accedi</button></form>
<?php else:?><p>Carica una copia aggiornata del file <strong>.db SQLite</strong>. L’analisi verifica struttura, vincoli e compatibilità. L’importazione mantiene gli ID e si blocca se trova tabelle non vuote.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="analyse"><input type="file" name="sqlite" accept=".db,.sqlite,.sqlite3" required><button>1. Analizza file</button></form>
<?php $rows=$report??($_SESSION['upload']['counts']??null);if($rows):?><table><tr><th>Tabella</th><th>Record</th></tr><?php foreach($rows as $t=>$n):?><tr><td><?=h($t)?></td><td><?=h($n)?></td></tr><?php endforeach;?><tr><th>Totale</th><th><?=array_sum($rows)?></th></tr></table><?php endif;?>
<?php if(isset($_SESSION['upload'])):?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="import"><button>2. Importa i dati verificati</button></form><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button>Esci ed elimina il file temporaneo</button></form><?php endif;?></main></html>
