<?php
declare(strict_types=1);
require_once __DIR__.'/includes/auth.php';
$user=require_access('contacts_sync','lettura');
require_once __DIR__.'/includes/google_contacts_bridge.php';
require_once __DIR__.'/includes/dati.php';
function gcs_norm_email(string $v):string{return strtolower(trim($v));}
function gcs_labels(mixed $value):array{$out=[];foreach(is_array($value)?$value:[] as $item){$label='';if(is_string($item))$label=trim($item);elseif(is_array($item)){$group=$item['contactGroupMembership']??[];$label=trim((string)($item['displayName']??$item['name']??$item['label']??$item['value']??(is_array($group)?($group['contactGroupName']??$group['name']??''):'')??''));}if($label!==''&&!str_starts_with($label,'contactGroups/'))$out[]=$label;}return array_values(array_unique($out));}
function gcs_db_entities(PDO $p):array{$out=[];foreach(dc_iter($p,'Associazioni') as $a)$out[(int)$a['ID']]=(string)$a['SOGGETTO'];return $out;}
function gcs_snapshot(PDO $p,array $config):array{
 $entities=gcs_db_entities($p);$db=[];
 foreach(dc_iter($p,'Mail') as $m){if(($m['TipoSoggetto']??'')!=='ASSOCIAZIONE')continue;$id=(int)$m['IDSoggetto'];$email=gcs_norm_email((string)$m['Indirizzo']);if(!isset($entities[$id])||!filter_var($email,FILTER_VALIDATE_EMAIL))continue;$db[$email][]=['mail_id'=>(int)$m['ID'],'entity_id'=>$id,'label'=>$entities[$id],'email'=>(string)$m['Indirizzo']];}
 $google=[];foreach(gcs_people($config) as $person){$seen=[];foreach($person['emailAddresses']??[] as $item){$e=gcs_norm_email((string)($item['value']??''));if(!filter_var($e,FILTER_VALIDATE_EMAIL)||isset($seen[$e]))continue;$seen[$e]=true;$orgs=$person['organizations']??[];$google[$e][]=['resourceName'=>(string)($person['resourceName']??''),'etag'=>(string)($person['etag']??''),'name'=>(string)($person['names'][0]['displayName']??''),'organizations'=>$orgs,'labels'=>gcs_labels(array_merge(is_array($person['labels']??null)?$person['labels']:[],is_array($person['memberships']??null)?$person['memberships']:[])),'title'=>count($orgs)===1?(string)($orgs[0]['title']??''):'','email'=>$e];}}
 $rows=[];$emails=array_unique(array_merge(array_keys($db),array_keys($google)));sort($emails,SORT_NATURAL|SORT_FLAG_CASE);
 foreach($emails as $email){$d=$db[$email]??[];$g=$google[$email]??[];$status=count($d)>1||count($g)>1||($g&&count($g[0]['organizations']??[])>1)?'ambiguous':(!$d?'google_only':(!$g?'db_only':(gcs_norm_email($d[0]['label'])===gcs_norm_email($g[0]['title'])?'same':'different')));$rows[]=['key'=>substr(hash('sha256',$email),0,24),'email'=>$email,'db'=>$d,'google'=>$g,'status'=>$status];}
 $owners=[];foreach($rows as $i=>$r)foreach($r['google'] as $g)$owners[$g['resourceName']][]=$i;foreach($owners as $idx)if(count($idx)>1)foreach(array_unique($idx) as $i)$rows[$i]['status']='ambiguous';
 $options=[];foreach($entities as $id=>$label)$options[]=['id'=>$id,'label'=>$label];$labels=[];foreach($rows as $row)foreach($row['google'] as $g)foreach($g['labels']??[] as $label)$labels[$label]=true;ksort($labels,SORT_NATURAL|SORT_FLAG_CASE);
 return ['rows'=>$rows,'entities'=>$options,'labels'=>array_keys($labels),'created'=>time(),'account'=>$config['account']];
}
function gcs_apply(PDO $p,array $config,array $snapshot,array $choices):array{
 $user=current_user();if(!$user||!allowed($user,'contacts_sync','scrittura'))throw new RuntimeException('Permesso di scrittura non assegnato.');
 $rows=[];foreach($snapshot['rows'] as $r)$rows[$r['key']]=$r;$results=[];$live=[];
 foreach(gcs_people($config) as $person)foreach($person['emailAddresses']??[] as $item){$e=gcs_norm_email((string)($item['value']??''));if(filter_var($e,FILTER_VALIDATE_EMAIL))$live[$e][]=$person;}
 foreach($choices as $key=>$choice){if(!isset($rows[$key])||!is_string($choice))continue;$r=$rows[$key];$email=$r['email'];
  if(in_array($r['status'],['different','ambiguous'],true)&&in_array($choice,['google','database'],true)){
   if(count($r['db'])!==1||count($r['google'])!==1)throw new RuntimeException('Contatto ambiguo: la scelta richiede una sola corrispondenza nel database e in Google.');
   $d=$r['db'][0];$g=$r['google'][0];
   if($choice==='google'){
    $orgs=$g['organizations'];if(count($orgs)>1)throw new RuntimeException('Più organizzazioni sul contatto '.$email.': azione bloccata.');
    if(!$orgs)$orgs=[['name'=>$d['label'],'title'=>$d['label'],'type'=>'work']];else{$orgs[0]['title']=$d['label'];if(empty($orgs[0]['name']))$orgs[0]['name']=$d['label'];}
    $editable=['name','title','department','symbol','domain','location','jobDescription','phoneticName','type'];$clean=[];foreach($orgs as $o){$x=[];foreach($editable as $field)if(array_key_exists($field,$o))$x[$field]=$o[$field];$clean[]=$x;}
    gcs_call($config,['op'=>'update','resourceName'=>$g['resourceName'],'etag'=>$g['etag'],'organizations'=>$clean]);$results[]='Google aggiornato: '.$email;
   }else{
    if(trim($g['title'])==='')throw new RuntimeException('Organization Title vuoto per '.$email.': non è possibile sostituire il nome dell’associazione.');
    $freshGoogle=$live[$email]??[];if(count($freshGoogle)!==1||($freshGoogle[0]['resourceName']??'')!==$g['resourceName']||($freshGoogle[0]['etag']??'')!==$g['etag'])throw new RuntimeException('Contatto Google modificato o duplicato dopo l’anteprima: '.$email);
    $p->beginTransaction();try{$entity=dc_find($p,'Associazioni',['ID'=>$d['entity_id']],true);$mail=dc_find($p,'Mail',['ID'=>$d['mail_id']]);if(!$entity||$entity['SOGGETTO']!==$d['label']||!$mail||$mail['TipoSoggetto']!=='ASSOCIAZIONE'||(int)$mail['IDSoggetto']!==$d['entity_id']||gcs_norm_email((string)$mail['Indirizzo'])!==$email)throw new RuntimeException('Associazione o email modificata dopo l’anteprima: '.$email);dc_update($p,'Associazioni',['ID'=>$d['entity_id']],['SOGGETTO'=>$g['title']]);$p->commit();$results[]='Database aggiornato: '.$email;}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
   }
  }elseif($r['status']==='db_only'&&$choice==='google'){
   $d=$r['db'][0];$a=dc_find($p,'Associazioni',['ID'=>$d['entity_id']]);$m=dc_find($p,'Mail',['ID'=>$d['mail_id']]);if(!$a||$a['SOGGETTO']!==$d['label']||!$m||$m['TipoSoggetto']!=='ASSOCIAZIONE'||(int)$m['IDSoggetto']!==$d['entity_id']||gcs_norm_email((string)$m['Indirizzo'])!==$email)throw new RuntimeException('Associazione o indirizzo cambiato dopo l’anteprima: '.$email);if(!empty($live[$email]))throw new RuntimeException('Email Google aggiunta dopo l’anteprima; eseguire un nuovo confronto: '.$email);gcs_call($config,['op'=>'create','emailAddresses'=>[['value'=>$d['email'],'type'=>'work']],'organization'=>$d['label']]);$results[]='Contatto Google creato: '.$email;
  }elseif($r['status']==='google_only'&&preg_match('/^[1-9][0-9]*$/D',$choice)){
   $entityId=(int)$choice;$label=null;foreach($snapshot['entities'] as $a)if((int)$a['id']===$entityId)$label=$a['label'];if($label===null)throw new RuntimeException('Associazione selezionata non valida.');if(count($live[$email]??[])!==1)throw new RuntimeException('Email Google assente o duplicata dopo l’anteprima: '.$email);
   $p->beginTransaction();try{$entity=dc_find($p,'Associazioni',['ID'=>$entityId],true);if(!$entity||$entity['SOGGETTO']!==$label)throw new RuntimeException('Associazione cambiata dopo l’anteprima.');foreach(dc_iter($p,'Mail') as $m)if(gcs_norm_email((string)$m['Indirizzo'])===$email)throw new RuntimeException('Email già censita nel database: '.$email);dc_insert($p,'Mail',['TipoSoggetto'=>'ASSOCIAZIONE','IDSoggetto'=>$entityId,'Indirizzo'=>$email,'PEC'=>0,'Predefinito'=>0]);$p->commit();$results[]='Email censita in '.$label.': '.$email;}catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
  }
 }
 return $results;
}
$error='';$message='';$snapshot=null;
try{
 $config=gcs_config_load();if(!$config)throw new RuntimeException('Configurare prima il ponte autonomo Google Contacts.');
 $p=poimanager_db();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();$action=(string)($_POST['action']??'');
  if($action==='scan'){$snapshot=gcs_snapshot($p,$config);$id=bin2hex(random_bytes(16));$_SESSION['gcs_snapshots']=[$id=>$snapshot];$_SESSION['gcs_snapshot_id']=$id;}
  elseif($action==='apply'){
   require_access('contacts_sync','scrittura');if(($_POST['confirm']??'')!=='1')throw new RuntimeException('Confermare esplicitamente le operazioni selezionate.');$id=(string)($_POST['snapshot_id']??'');$saved=$_SESSION['gcs_snapshots'][$id]??null;if(!$saved||!hash_equals((string)($_SESSION['gcs_snapshot_id']??''),$id))throw new RuntimeException('Anteprima scaduta: eseguire nuovamente il confronto.');
   try{$done=gcs_apply($p,$config,$saved,is_array($_POST['choice']??null)?$_POST['choice']:[]);$message=$done?implode(' · ',$done):'Nessuna modifica selezionata.';}finally{unset($_SESSION['gcs_snapshots'][$id],$_SESSION['gcs_snapshot_id']);}
  }
 }
 if(!$snapshot&&!empty($_SESSION['gcs_snapshot_id']))$snapshot=$_SESSION['gcs_snapshots'][$_SESSION['gcs_snapshot_id']]??null;
}catch(Throwable $e){$error=$e->getMessage();}
page_start('Prova Google Contacts','Confronta gli indirizzi delle associazioni con Google Contacts. Questa funzione è autonoma e si può valutare separatamente.',['Google Contacts','Prova di allineamento']);
if($error)echo '<aside class="error">'.uh($error).'</aside>';if($message)echo '<aside>'.uh($message).'</aside>';
?>
<p>Account Google del ponte autonomo: <strong><?=uh($config['account']??'non configurato')?></strong>. Il confronto usa le email registrate per le associazioni nel database; la tabella temporanea <em>contacts</em> non viene letta.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="scan"><button>Avvia confronto manuale</button></form>
<?php if($snapshot):?><p>Anteprima del <?=uh(date('d/m/Y H:i:s',$snapshot['created']))?>. L’anteprima non modifica dati.</p><form method="post"><input type="hidden" name="csrf" value="<?=uh(csrf())?>"><input type="hidden" name="action" value="apply"><input type="hidden" name="snapshot_id" value="<?=uh($_SESSION['gcs_snapshot_id']??'')?>">
<div class="gcs-cards">
<div class="gcs-filters" aria-label="Filtri contatti"><label>Filtra testo<input type="search" id="gcs-text-filter" placeholder="Cerca in tutti i campi" autocomplete="off"></label><label>Classificazione<select id="gcs-status-filter"><option value="all">Tutte</option><option value="same">Allineato</option><option value="different">Dati diversi</option><option value="ambiguous">Ambiguo</option><option value="db_only">Solo database</option><option value="google_only">Solo Google</option></select></label><label>Etichetta Google<select id="gcs-label-filter"><option value="all">Tutte</option><?php foreach($snapshot['labels']??[] as $label):?><option value="<?=uh($label)?>"><?=uh($label)?></option><?php endforeach;?></select></label><span id="gcs-filter-count" class="gcs-filter-count" aria-live="polite"></span></div>
<?php foreach($snapshot['rows'] as $r):?><article class="gcs-card" data-gcs-status="<?=uh($r['status'])?>"><header class="gcs-card-header"><h2><?=uh($r['email'])?></h2><span class="gcs-status gcs-status-<?=uh($r['status'])?>"><?php if($r['status']==='same'):?>Allineato<?php elseif($r['status']==='ambiguous'):?>Ambiguo<?php elseif($r['status']==='different'):?>Dati diversi<?php elseif($r['status']==='db_only'):?>Solo database<?php else:?>Solo Google<?php endif;?></span></header><div class="gcs-card-columns"><section><h3>Associazione database</h3><?php foreach($r['db'] as $d):?><p><?=uh($d['label'])?> <small>(#<?= (int)$d['entity_id']?>)</small></p><?php endforeach;if(!$r['db'])echo '<p class="gcs-empty">—</p>';?></section><section><h3>Google Contacts</h3><?php foreach($r['google'] as $g):?><p><?=uh($g['title']?:'(vuoto)')?><?php if($g['name']!==''):?> <small><?=uh($g['name'])?></small><?php endif;?></p><?php if(!empty($g['labels'])):?><p class="gcs-labels"><strong>Etichette:</strong> <?=uh(implode(', ',$g['labels']))?></p><?php endif;?><?php endforeach;if(!$r['google'])echo '<p class="gcs-empty">—</p>';?></section></div><div class="gcs-choice"><h3>Scelta</h3>
<?php if($r['status']==='same'):?>Già allineato<?php elseif($r['status']==='ambiguous'):?><strong>Ambiguo:</strong> selezionare una sorgente; con duplicati multipli l’applicazione resta bloccata.<label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="skip" checked> Lascia invariato</label><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="google"> Usa Google</label><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="database"> Usa database</label><?php elseif($r['status']==='different'):?><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="skip" checked> Lascia invariato</label><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="google"> Aggiorna Google</label><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="database"> Aggiorna database</label><?php elseif($r['status']==='db_only'):?><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="skip" checked> Lascia invariato</label><label><input type="radio" name="choice[<?=uh($r['key'])?>]" value="google"> Crea contatto Google</label><?php else:?><label>Associazione da collegare<select name="choice[<?=uh($r['key'])?>]"><option value="skip" selected>Non censire</option><?php foreach($snapshot['entities'] as $a):?><option value="<?=(int)$a['id']?>"><?=uh($a['label'])?> (#<?=(int)$a['id']?>)</option><?php endforeach;?></select></label><?php endif;?></div></article><?php endforeach;?>
<?php if(!$snapshot['rows']):?><p class="gcs-empty-card">Nessun indirizzo email da confrontare.</p><?php endif;?></div>
<?php if(allowed($user,'contacts_sync','scrittura')):?><label><input type="checkbox" name="confirm" value="1" required> Confermo le sole operazioni selezionate</label><button>Conferma e applica</button><?php else:?><aside>Permesso di sola lettura: per applicare scelte occorre il livello scrittura.</aside><?php endif;?></form><?php endif;?>
<p><a href="/google_contacts_config.php">Configurazione del ponte autonomo</a>. Nessuna funzione del ponte email o della tabella temporanea contacts interviene in questa procedura.</p>
<script src="/assets/google_contacts_sync.js" defer></script>
</main></body></html>
