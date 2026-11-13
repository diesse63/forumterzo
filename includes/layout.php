<?php
declare(strict_types=1);
/**
 * Tutte le pagine usano page_start() da auth.php.
 * I parametri espliciti prevalgono sui metadati delle pagine esistenti.
 */
function layout_start(string $title, string $description='', array $context=[]): void {
 $pages=[
  '/login.php'=>['Accesso','Accedi con il tuo nome utente e la password personale.',['Accesso']],
  '/pannello.php'=>['Pannello','Consulta le funzioni disponibili in base ai tuoi permessi.',['Pannello']],
  '/utenti.php'=>['Utenti','Crea e modifica gli utenti, assegna ruoli e permessi per ogni schermata.',['Servizi','Utenti']],
  '/cambia_password.php'=>['Cambia password','Modifica la password personale mantenendo la cifratura dei tuoi dati.',['Servizi','Cambia password']],
  '/logout.php'=>['Uscita','Conferma la chiusura della sessione di lavoro.',['Sessione','Esci']],
  '/installa_utenti.php'=>['Installazione utenti','Inizializza gli account e la chiave di cifratura per il primo utilizzo.',['Servizi','Installazione utenti']],
  '/importazione_sqlite/index.php'=>['Importazione SQLite','Seleziona le tabelle SQLite e inserisci o aggiorna i record rispettando i vincoli.',['Servizi','Importazione SQLite']],
 ];
 $path=parse_url($_SERVER['SCRIPT_NAME']??'',PHP_URL_PATH);
 $meta=$pages[$path]??[$title,$description,$context];
 // Per pagine note il titolo comune è quello del registro.
 $title=$meta[0];
 if($description==='')$description=$meta[1];
 if(!$context)$context=$meta[2];
 $user=null;
 try{$user=current_user();}catch(Throwable $e){/* Accesso e installazione devono restare visualizzabili. */}
 $items=[];
 if($user){
  $items[]=['label'=>'Pannello','url'=>'/pannello.php'];
  $services=[];
  if(allowed($user,'utenti','scrittura'))$services[]=['label'=>'Utenti','url'=>'/utenti.php'];
  if(allowed($user,'importazione','scrittura'))$services[]=['label'=>'Importazione SQLite','url'=>'/importazione_sqlite/index.php'];
  if($user['Ruolo']!=='ghost')$services[]=['label'=>'Cambia password','url'=>'/cambia_password.php'];
  if($services)$items[]=['label'=>'Servizi','children'=>$services];
  $items[]=['label'=>'Sessione','children'=>[['label'=>'Esci','url'=>'/logout.php']]];
 }else{
  $items[]=['label'=>'Accesso','url'=>'/login.php'];
 }
 echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.uh($title).' · Gestione</title><link rel="stylesheet" href="/assets/layout.css"><script src="/assets/menu.js" defer></script></head><body>';
 echo '<header class="app-header"><a class="app-name" href="'.($user?'/pannello.php':'/login.php').'">Gestione</a><p class="app-context">'.uh(implode(' › ',$context)).'</p></header>';
 echo '<nav class="app-nav" aria-label="Menu principale"><ul class="menu-bar" data-menubar>';
 foreach($items as $i=>$item){
  echo '<li class="menu-item">';
  if(isset($item['children'])){
   $selected=($context[0]??'')===$item['label'];
   echo '<button type="button" class="menu-toggle" data-menu-toggle aria-expanded="false" aria-controls="submenu-'.$i.'"'.($selected?' aria-current="true"':'').'>'.uh($item['label']).' <span aria-hidden="true">▾</span></button><ul class="submenu" id="submenu-'.$i.'" hidden>';
   foreach($item['children'] as $child)echo '<li><a class="menu-link" href="'.uh($child['url']).'"'.($path===$child['url']?' aria-current="page"':'').'>'.uh($child['label']).'</a></li>';
   echo '</ul>';
  }else echo '<a class="menu-link" href="'.uh($item['url']).'"'.($path===$item['url']?' aria-current="page"':'').'>'.uh($item['label']).'</a>';
  echo '</li>';
 }
 echo '</ul></nav><main class="page-main"><header class="page-heading"><h1 class="page-title">'.uh($title).'</h1><p class="page-description">'.uh($description).'</p></header>';
}
