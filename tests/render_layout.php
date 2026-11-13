<?php
declare(strict_types=1);
// Fixture del componente reale, senza connessioni o credenziali.
function uh($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function current_user():array{return ['Ruolo'=>'admin'];}
function allowed(array $u,string $panel,string $mode='lettura'):bool{return true;}
$_SERVER['SCRIPT_NAME']='/utenti.php';
require dirname(__DIR__).'/includes/layout.php';
layout_start('Utenti');
echo '<table><tr><th>Nome</th><th>Ruolo</th><th>Stato</th></tr><tr><td>admin</td><td>admin</td><td>Attivo</td></tr></table><h2>Nuovo utente</h2><form><label>Nome di accesso<input></label><label>Email<input type="email"></label><label>Ruolo<select><option>user</option></select></label><button>Salva utente e permessi</button></form></main></body></html>';
