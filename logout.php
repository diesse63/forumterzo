<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{check_csrf();}catch(Throwable $e){http_response_code(403);exit('Sessione non valida.');}
 $_SESSION=[];session_destroy();header('Location: /login.php');exit;
}
page_start('Uscita');
echo '<form method="post"><input type="hidden" name="csrf" value="'.uh(csrf()).'"><button>Conferma uscita</button></form></main></html>';
