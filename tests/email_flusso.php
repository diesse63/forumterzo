<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';require dirname(__DIR__).'/includes/email.php';require dirname(__DIR__).'/includes/anagrafica.php';
function ef_check(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function ef_bad(callable $f,string $m):void{$bad=false;try{$f();}catch(RuntimeException $e){$bad=true;}ef_check($bad,$m);}
$p=poimanager_db();$tag=dc_active($p)?'E':'N';$c=em_config($p);$fake=function($cfg,$key,$payload){return ['ok'=>true,'sender'=>$cfg['Mittente'],'quota'=>100,'status'=>'sent'];};em_ping($p,$c,$fake);
$entity=enti_create($p,['SOGGETTO'=>'FLUSSO BROWSER '.$tag,'ID_TIPOLOGIA'=>100]);
$lr=(int)dc_insert($p,'Soggetti',['ID_Associazione'=>$entity,'Nome'=>'COGNOME NOME LR '.$tag,'Tipo'=>'LR']);
$as=(int)dc_insert($p,'Soggetti',['ID_Associazione'=>$entity,'Nome'=>'COGNOME NOME AS '.$tag,'Tipo'=>'AS']);
dc_insert($p,'Soggetti',['ID_Associazione'=>$entity,'Nome'=>'SENZA EMAIL '.$tag,'Tipo'=>'LR']);
$email='flusso.'.strtolower($tag).'@example.invalid';
$first=(int)dc_insert($p,'Mail',['TipoSoggetto'=>'REFERENTE','IDSoggetto'=>$lr,'Indirizzo'=>$email,'PEC'=>0,'Predefinito'=>1]);
$second=(int)dc_insert($p,'Mail',['TipoSoggetto'=>'ALTRO','IDSoggetto'=>$as,'Indirizzo'=>strtoupper($email),'PEC'=>0,'Predefinito'=>1]);
$invalid=(int)dc_insert($p,'Mail',['TipoSoggetto'=>'ASSOCIAZIONE','IDSoggetto'=>$entity,'Indirizzo'=>'non-email-'.$tag,'PEC'=>0,'Predefinito'=>0]);
$book=em_address_book($p);$ids=array_column($book,'ID');ef_check(in_array($first,$ids)&&in_array($second,$ids)&&!in_array($invalid,$ids),'Rubrica con indirizzi invalidi o senza mail');
$row=array_values(array_filter($book,fn($r)=>(int)$r['ID']===$first))[0];ef_check($row['Ruolo']==='LR'&&$row['Ente']==='FLUSSO BROWSER '.$tag&&count($row['Riferimenti'])===3,'Riferimenti nominativo/ente');
$messageOnly=em_save_draft($p,['subject'=>'SOLO TESTO '.$tag,'text'=>'Contenuto','html'=>'<p>Contenuto</p>']);
ef_bad(fn()=>em_prepare($p,$messageOnly),'Invio senza destinatari ammesso');
$peopleOnly=em_save_draft($p,['mail_ids'=>[$first,$second]]);
ef_check(count(dc_rows($p,'email_destinatari','WHERE MessaggioID=?',[$peopleOnly]))===1,'Duplicati in bozza');
ef_bad(fn()=>em_prepare($p,$peopleOnly),'Invio senza contenuto ammesso');
$draft=dc_find($p,'email_messaggi',['ID'=>$peopleOnly]);
em_save_draft($p,['id'=>$peopleOnly,'version'=>$draft['Versione'],'subject'=>'FLUSSO '.$tag,'text'=>'Messaggio {nome}','html'=>'<p>Messaggio {nome}</p>','mail_ids'=>[$first]]);
dc_update($p,'Mail',['ID'=>$first],['Indirizzo'=>'aggiornato.'.strtolower($tag).'@example.invalid']);
ef_bad(fn()=>em_prepare($p,$peopleOnly),'Rubrica cambiata dopo salvataggio non rilevata');
$draft=dc_find($p,'email_messaggi',['ID'=>$peopleOnly]);em_save_draft($p,['id'=>$peopleOnly,'version'=>$draft['Versione'],'subject'=>$draft['Oggetto'],'text'=>$draft['Testo'],'html'=>$draft['HTML'],'mail_ids'=>[$first]]);
ef_check(em_prepare($p,$peopleOnly)===1,'Preparazione aggiornata');$sent=[];$transport=function($cfg,$key,$payload)use($fake,&$sent){if($payload['op']==='send')$sent[]=$payload;return $fake($cfg,$key,$payload);};em_send_batch($p,$peopleOnly,$transport);
ef_check(count($sent)===1&&$sent[0]['to']==='aggiornato.'.strtolower($tag).'@example.invalid','Invio non aggiornato');
$job=dc_rows($p,'email_invii','WHERE MessaggioID=?',[$peopleOnly])[0];$token=em_token($job['ID']);
dc_update($p,'Soggetti',['ID'=>$lr],['Nome'=>'NOME AGGIORNATO '.$tag]);
$copy=em_duplicate($p,$peopleOnly,true);$copied=dc_find($p,'email_messaggi',['ID'=>$copy]);$copyDest=dc_rows($p,'email_destinatari','WHERE MessaggioID=?',[$copy]);
ef_check($copied['Stato']==='bozza'&&$copied['ConfigID']===null&&$copyDest[0]['Nome']==='NOME AGGIORNATO '.$tag&&count(dc_rows($p,'email_invii','WHERE MessaggioID=?',[$copy]))===0,'Duplicazione non pulita o non aggiornata');
$copyText=em_duplicate($p,$peopleOnly,false);ef_check(!dc_rows($p,'email_destinatari','WHERE MessaggioID=?',[$copyText]),'Duplicazione solo testo con destinatari');
em_prepare($p,$copy);$q=$p->prepare("UPDATE email_invii SET Stato='da_verificare' WHERE MessaggioID=?");$q->execute([$copy]);
ef_bad(fn()=>em_delete($p,$copy,1),'Eliminazione incerta ammessa');
$version=(int)dc_find($p,'email_messaggi',['ID'=>$copy])['Versione'];ef_bad(fn()=>em_delete($p,$copy,$version),'Esiti incerti non protetti');
$p->prepare("UPDATE email_invii SET Stato='inviato' WHERE MessaggioID=?")->execute([$copy]);em_delete($p,$copy,$version);
$cfg=poimanager_config();$other=new PDO($cfg['dsn'],$cfg['user'],$cfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$lock='email_send_'.$messageOnly;$other->prepare('SELECT GET_LOCK(?,0)')->execute([$lock]);
try{ef_bad(fn()=>em_delete($p,$messageOnly,1),'Lock invio ignorato');}finally{$other->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
$before=count(em_address_book($p));$version=(int)dc_find($p,'email_messaggi',['ID'=>$peopleOnly])['Versione'];em_delete($p,$peopleOnly,$version);
ef_check(!dc_find($p,'email_messaggi',['ID'=>$peopleOnly])&&!em_confirm($p,$token)&&count(em_address_book($p))===$before,'Eliminazione incompleta o rubrica alterata');
ef_check(dc_find($p,'email_messaggi',['ID'=>$copyText])!==null,'Eliminata mail diversa');
if(dc_active($p))ef_check($p->query('SELECT Oggetto FROM email_messaggi WHERE ID='.$copyText)->fetchColumn()==='','Copia in chiaro');
// Ripristina una coppia duplicata per le prove dell’interfaccia, senza invii reali.
dc_update($p,'Mail',['ID'=>$first],['Indirizzo'=>$email]);dc_update($p,'Soggetti',['ID'=>$lr],['Nome'=>'COGNOME NOME LR '.$tag]);
echo 'OK: flusso '.$tag.', bozze indipendenti, rubrica viva, duplicati, preparazione obsoleta, copie, cancellazioni e lock. Nessuna email reale.'.PHP_EOL;
