<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
try{
 $u=current_user();if(!$u){header('Location: /login.php');exit;}
 page_start('Pannello POImanager');
 echo '<p>Utente: '.uh($u['Profilo']['nome']).' · Ruolo: '.uh($u['Ruolo']).'</p>';
 if($u['Ruolo']==='ghost')echo '<aside>Modalità sviluppo: accesso completo ghost attivo. Disabilitare prima della produzione.</aside>';
 $routes=['associazioni'=>'/anagrafica.php','utenti'=>'/utenti.php','importazione'=>'/importazione_sqlite/index.php'];
 echo '<ul>';
 foreach(poimanager_db()->query('SELECT * FROM Pannelli ORDER BY Titolo') as $p){
  if(!allowed($u,$p['Codice']))continue;
  echo '<li>';
  if(isset($routes[$p['Codice']]))echo '<a href="'.uh($routes[$p['Codice']]).'">'.uh($p['Titolo']).'</a>';
  else echo uh($p['Titolo']).' — schermata applicativa da collegare';
  echo allowed($u,$p['Codice'],'scrittura')?' (lettura e scrittura)':' (sola lettura)';
  echo '</li>';
 }echo '</ul></main></body></html>';
}catch(Throwable $e){http_response_code(503);echo 'Pannello temporaneamente non disponibile. Verificare installazione utenti.';}
