<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$u=current_user();if(!$u){header('Location: /login.php');exit;}
if(!in_array($u['Ruolo'],['admin','ghost'],true)){http_response_code(403);exit('Accesso riservato agli amministratori.');}
require_once __DIR__.'/includes/dati_migrazione.php';
$message='';$jsonResult=null;$p=poimanager_db();
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$action=$_POST['action']??'';
  if($action==='start'){
   if(!isset($_POST['backup'])||!isset($_POST['maintenance']))throw new RuntimeException('Confermare backup e sospensione delle attività.');
   dc_begin($p);$message='Preparazione completata. Le funzioni gestionali restano in manutenzione fino al termine. Conservare subito una copia protetta di private/dati_security.php.';
  }elseif($action==='step'){
   $r=dc_migration_step($p);$jsonResult=['ok'=>true,'done'=>$r['done']];$message=$r['done']?'Cifratura completata: le funzioni sono nuovamente disponibili.':'Lotto completato: '.$r['table'].' · '.$r['converted'].' righe.';
  }elseif($action==='verify'){
   $table=(string)($_POST['table']??'');if(!isset(dc_spec()[$table])||!dc_exists($p,$table))throw new RuntimeException('Tabella non valida.');
   $message='Verifica autenticità e indici completata: '.dc_verify_table($p,$table).' righe in '.$table.'.';
  }
 }
}catch(Throwable $e){$message=$e instanceof PDOException?'Operazione non completata: controllare permessi ALTER/CREATE, vincoli e stato della migrazione. Le modifiche DDL già applicate restano presenti; usare Riprendi preparazione.':$e->getMessage();}
if($_SERVER['REQUEST_METHOD']==='POST'&&str_contains($_SERVER['HTTP_ACCEPT']??'','application/json')){header('Content-Type: application/json');echo json_encode(($jsonResult??['ok'=>false,'done'=>false])+['message'=>$message]);exit;}
$state=dc_state($p);$rows=$state?$p->query('SELECT * FROM crypto_tabelle ORDER BY Nome')->fetchAll():[];
page_start('Cifratura dati','Migra i campi riservati, controlla l’avanzamento e verifica la leggibilità autorizzata.',['Servizi','Cifratura dati']);
if($message)echo '<aside role="status">'.uh($message).'</aside>';
?>
<p>Prima di iniziare: distribuire tutti i file aggiornati, fare un backup completo del database e dei file privati, sospendere importazioni e invii e chiudere le altre sessioni operative. Non è una migrazione reversibile con un clic: il ripristino richiede il backup completo corrispondente.</p>
<p>Le chiavi sono separate dai profili utenti e dai token delle conferme. Il nuovo file <strong>private/dati_security.php</strong> non deve entrare in Git: conservarne una copia protetta insieme al backup successivo. Perderlo rende illeggibili i dati cifrati.</p>
<p>Stato: <strong><?=uh($state['Stato']??'Non avviata')?></strong></p>
<?php if(!$state||$state['Stato']!=='pronto'):?>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="start">
<label><input type="checkbox" name="backup" required> Backup completo eseguito e verificato.</label>
<label><input type="checkbox" name="maintenance" required> Importazioni, invii e modifiche ai dati sospesi; codice aggiornato distribuito.</label>
<button><?=$state?'Riprendi preparazione':'Prepara chiave e migrazione'?></button></form>
<?php if($state):?><form method="post" id="crypto-step"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="step"><button>Esegui prossimo lotto (100 righe)</button></form><div><button type="button" id="crypto-auto">Completa automaticamente per lotti</button> <button type="button" id="crypto-stop" disabled>Ferma dopo questo lotto</button><p id="crypto-status" role="status" aria-live="polite"></p></div><p>Ripetere fino allo stato pronto. Un’interruzione non richiede di ricominciare: i lotti completati sono conservati.</p><?php endif;endif;?>
<table><tr><th>Tabella</th><th>Righe iniziali</th><th>Convertite</th><th>Stato</th><th>Verifica</th></tr>
<?php foreach($rows as $r):?><tr><td><?=uh($r['Nome'])?></td><td><?=(int)$r['Righe']?></td><td><?=(int)$r['Convertite']?></td><td><?=uh($r['Stato'])?></td><td><?php if(($state['Stato']??'')==='pronto'):?><form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="verify"><input type="hidden" name="table" value="<?=uh($r['Nome'])?>"><button>Verifica</button></form><?php endif;?></td></tr><?php endforeach;?></table>
<p>I campi originali riservati saranno sostituiti da indici HMAC o valori vuoti/nulli; il contenuto leggibile solo con la chiave sarà nella colonna _Cifrato. ID, FK, ruoli e stati restano tecnici. La cifratura non cancella eventuali copie precedenti nei backup o nei log del server.</p>
<script src="/assets/cifratura.js" defer></script></main></body></html>
