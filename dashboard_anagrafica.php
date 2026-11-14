<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('associazioni','lettura');
page_start('Gestione anagrafica','Seleziona l’archivio degli enti, dei legali rappresentanti o degli altri soggetti.',['Anagrafiche','Gestione anagrafica']);
?>
<link rel="stylesheet" href="/assets/anagrafica_scheda.css">
<div class="anagrafiche-cards">
<a class="anagrafiche-card" href="/anagrafica.php"><span class="anagrafiche-icon" aria-hidden="true">📂</span><h2>Anagrafica</h2><p>Elenco enti e associazioni</p></a>
<?php if(allowed($user,'soggetti')):?>
<a class="anagrafiche-card" href="/gestione_referenti.php"><span class="anagrafiche-icon" aria-hidden="true">📇</span><h2>Referenti</h2><p>Legali rappresentanti e contatti ufficiali</p></a>
<a class="anagrafiche-card" href="/gestione_altri.php"><span class="anagrafiche-icon" aria-hidden="true">👥</span><h2>Altri soggetti</h2><p>Collaboratori e persone collegate</p></a>
<?php endif;?></div><p><a href="/pannello.php">← Torna al pannello</a></p></main></body></html>
