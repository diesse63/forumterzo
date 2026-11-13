<?php
declare(strict_types=1);
// Fixture del componente reale, senza connessioni o credenziali.
function uh($s):string{return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function current_user():array{return ['Ruolo'=>'admin'];}
function allowed(array $u,string $panel,string $mode='lettura'):bool{return true;}
$_SERVER['SCRIPT_NAME']='/utenti.php';
require dirname(__DIR__).'/includes/layout.php';
layout_start('Utenti');
$edit=['ID'=>2,'Ruolo'=>'user','Attivo'=>1,'Profilo'=>['nome'=>'user','email'=>'']];
$panels=[['Codice'=>'associazioni','Titolo'=>'Associazioni'],['Codice'=>'nuovo','Titolo'=>'Nuovo pannello']];
$perms=['associazioni'=>'lettura'];
function csrf():string{return 'fixture';}
require dirname(__DIR__).'/includes/utenti_form.php';
echo '</main></body></html>';
