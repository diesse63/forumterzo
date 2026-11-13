<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('gestione_email','scrittura');
if(!in_array($user['Ruolo'],['admin','ghost'],true)){http_response_code(403);exit('Installazione riservata agli amministratori.');}
require_once __DIR__.'/includes/email.php';
$message='';
try{if($_SERVER['REQUEST_METHOD']==='POST'){check_csrf();if(!isset($_POST['backup']))throw new RuntimeException('Confermare il backup.');em_install(poimanager_db());$message='Tabelle email installate. Le anagrafiche non sono state modificate.';}}
catch(Throwable $e){$message=$e instanceof PDOException?'Installazione non completata. Verificare permessi CREATE e struttura delle sole tabelle email. Nessuna tabella anagrafica è stata modificata.':$e->getMessage();}
page_start('Installazione gestione email','Crea le tabelle dedicate a messaggi, destinatari, invii e configurazioni.',['Servizi','Gestione email','Installazione']);
if($message)echo '<aside>'.em_h($message).'</aside>';
?>
<p>La procedura non importa né modifica enti, persone, indirizzi o liste esistenti. È rieseguibile sulle tabelle email compatibili.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=em_h(csrf())?>"><label><input type="checkbox" name="backup" required> Ho eseguito il backup del database.</label><button>Installa le tabelle dedicate</button></form>
<p><a href="/gestione_email.php">Apri Gestione email</a></p></main></body></html>
