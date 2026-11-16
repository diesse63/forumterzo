<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('confronto_contacts','lettura');
require_once __DIR__.'/includes/email.php';

function cc_email($value): string { return strtolower(trim((string)$value)); }
function cc_report(array $contacts,array $mail): array {
 $db=[];$external=[];$issues=[];$number=0;
 foreach($mail as $m){
  $e=cc_email($m['Indirizzo']??'');
  if(empty($m['EnteID'])||!filter_var($e,FILTER_VALIDATE_EMAIL)){
   $issues[]=['origine'=>'Mail #'.$m['ID'],'nome'=>$m['Nome']??'','email'=>$e,'nota'=>empty($m['EnteID'])?'Associazione o soggetto non collegato':'Email assente o non valida'];continue;
  }
  $db[$e][]=$m;
 }
 foreach($contacts as $c){
  ++$number;
  if(trim((string)($c['COL 1']??''))==='First Name'&&trim((string)($c['COL 19']??''))==='E-mail 1 - Value')continue;
  $name=trim(implode(' ',array_filter([$c['COL 1']??'',$c['COL 2']??'',$c['COL 3']??''])));
  $org=trim((string)($c['COL 12']??''));if($org==='')$org=trim((string)($c['COL 11']??''));
  $seen=[];$has=false;
  foreach(['COL 19','COL 21'] as $field){
   $e=cc_email($c[$field]??'');if($e==='')continue;$has=true;
   if(!filter_var($e,FILTER_VALIDATE_EMAIL)){$issues[]=['origine'=>'contacts riga letta '.$number,'nome'=>$name,'email'=>$e,'nota'=>'Email non valida in '.$field];continue;}
   if(isset($seen[$e]))continue;$seen[$e]=true;
   $external[$e][]=['nome'=>$name,'ente'=>$org,'riga'=>$number,'campo'=>$field];
  }
  if(!$has)$issues[]=['origine'=>'contacts riga letta '.$number,'nome'=>$name,'email'=>'','nota'=>'Nessuna email'];
 }
 $groups=['both'=>[],'contacts'=>[],'db'=>[]];
 $emails=array_unique(array_merge(array_keys($db),array_keys($external)));sort($emails,SORT_NATURAL|SORT_FLAG_CASE);
 foreach($emails as $e){
  $key=isset($db[$e],$external[$e])?'both':(isset($external[$e])?'contacts':'db');
  $groups[$key][]=['email'=>$e,'contacts'=>$external[$e]??[],'db'=>$db[$e]??[]];
 }
 return ['groups'=>$groups,'issues'=>$issues];
}
$error=null;$report=null;
try{
 $p=poimanager_db();
 // em_contacts usa dc_iter per decifrare Mail, Associazioni e Soggetti.
 $mail=em_contacts($p);
 $contacts=$p->query('SELECT * FROM contacts')->fetchAll(PDO::FETCH_ASSOC);
 $report=cc_report($contacts,$mail);
}catch(Throwable $e){$error='Report non generato: verificare la presenza di contacts, la configurazione della cifratura e i collegamenti del database. Nessun risultato parziale viene mostrato.';http_response_code(503);}
page_start('Confronto email contacts','Confronta gli indirizzi di contacts con le associazioni e tutti i soggetti collegati.',['Servizi','Gestione mail','Confronto contacts']);
if($error){echo '<aside>'.uh($error).'</aside></main></body></html>';exit;}
$labels=['both'=>'Presenti in entrambi gli archivi','contacts'=>'Solo in contacts','db'=>'Solo in associazioni e soggetti collegati'];
?>
<style>
.cc-scroll{overflow-x:auto}.cc-table{border-collapse:collapse;width:100%;margin:1rem 0}.cc-table th,.cc-table td{padding:.65rem;border:1px solid #aaa;text-align:left;vertical-align:top}.cc-table th{background:#edf2f7;color:#172033}.cc-table td{overflow-wrap:anywhere}.cc-table small{display:block}.cc-links{display:flex;gap:1rem;flex-wrap:wrap}
@media print{.app-header,.app-nav,.cc-links{display:none}.cc-scroll{overflow:visible}.cc-table{font-size:10px}section{break-before:page}tr{break-inside:avoid}}
</style>
<p>Confronto per email, ignorando maiuscole e spazi iniziali/finali. Ogni indirizzo è classificato separatamente: un contatto con due email può apparire in due sezioni. Sono incluse anche le PEC. Le corrispondenze duplicate sono mantenute sotto lo stesso indirizzo.</p>
<p>Generato il <?=uh((new DateTimeImmutable('now',new DateTimeZone('Europe/Rome')))->format('d/m/Y H:i:s'))?> (Italia). Consultazione soltanto: nessun dato viene modificato.</p>
<p>Le righe indicate per contacts sono posizioni di lettura del report, non identificativi permanenti. Le righe senza email, non valide o prive di associazione sono elencate nelle anomalie.</p>
<nav class="cc-links" aria-label="Sezioni report"><?php foreach($labels as $k=>$label):?><a href="#<?=uh($k)?>"><?=uh($label)?> (<?=count($report['groups'][$k])?>)</a><?php endforeach;?><a href="#issues">Anomalie (<?=count($report['issues'])?>)</a></nav>
<p>Per salvare il report: usa la funzione Stampa del browser e scegli Salva come PDF.</p>
<?php foreach($labels as $key=>$label):?>
<section id="<?=uh($key)?>"><h2><?=uh($label)?> — <?=count($report['groups'][$key])?> indirizzi unici</h2>
<div class="cc-scroll"><table class="cc-table"><thead><tr><th>Email</th><th>contacts: nominativo e associazione dichiarata</th><th>Gestionale: associazione e soggetto collegato</th></tr></thead><tbody>
<?php foreach($report['groups'][$key] as $r):?><tr><td><?=uh($r['email'])?></td><td>
<?php foreach($r['contacts'] as $c):?><p><strong><?=uh($c['nome'])?></strong><small><?=uh($c['ente'])?></small><small>Riga letta <?= (int)$c['riga']?> · <?=uh($c['campo'])?></small></p><?php endforeach;if(!$r['contacts'])echo '—';?>
</td><td><?php foreach($r['db'] as $m):?><p><strong><?=uh($m['Ente'])?></strong><small><?=uh($m['Nome'])?> · <?=uh($m['TipoSoggetto'])?><?=!empty($m['PEC'])?' · PEC':''?></small><small>Mail #<?=(int)$m['ID']?> · Associazione #<?=(int)$m['EnteID']?></small></p><?php endforeach;if(!$r['db'])echo '—';?></td></tr><?php endforeach;?>
<?php if(!$report['groups'][$key]):?><tr><td colspan="3">Nessun indirizzo.</td></tr><?php endif;?>
</tbody></table></div></section>
<?php endforeach;?>
<section id="issues"><h2>Anomalie — <?=count($report['issues'])?></h2><div class="cc-scroll"><table class="cc-table"><thead><tr><th>Origine</th><th>Nominativo</th><th>Email</th><th>Motivo</th></tr></thead><tbody>
<?php foreach($report['issues'] as $r):?><tr><?php foreach(['origine','nome','email','nota'] as $f):?><td><?=uh($r[$f])?></td><?php endforeach;?></tr><?php endforeach;?>
<?php if(!$report['issues']):?><tr><td colspan="4">Nessuna anomalia.</td></tr><?php endif;?>
</tbody></table></div></section></main></body></html>
