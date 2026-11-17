<?php
declare(strict_types=1);
require_once __DIR__.'/database.php';
require_once __DIR__.'/email_http.php';
require_once __DIR__.'/dati.php';
function em_h($value): string {return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function em_ready(PDO $p): bool {
 $q=$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('email_configurazione','email_messaggi','email_destinatari','email_allegati','email_invii')");
 if((int)$q->fetchColumn()!==5)return false;
 $q=$p->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_messaggi' AND COLUMN_NAME='ConfermaLettura'");$q->execute();
 return (int)$q->fetchColumn()===1;
}
function em_install(PDO $p): void {
 if(!class_exists('DOMDocument'))throw new RuntimeException('Abilitare PHP DOM per l’editor email prima di installare il modulo.');
 $sql=file_get_contents(dirname(__DIR__).'/email/migrazione_v1.sql');
 foreach(explode(';',$sql) as $statement)if(trim($statement)!=='')$p->exec($statement);
 // Gli aggiornamenti dello schema avvengono solo dalla procedura amministrativa esplicita.
 $q=$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_messaggi' AND COLUMN_NAME='ConfermaLettura'");
 if(!(int)$q->fetchColumn())$p->exec("ALTER TABLE email_messaggi ADD COLUMN ConfermaLettura TINYINT NOT NULL DEFAULT 1 AFTER Stato");
 foreach(['email_configurazione'=>['ID','SegretoRef','VerificataIl'],'email_messaggi'=>['ID','Versione','ConfigID','ConfermaLettura'],'email_destinatari'=>['ID','MailOrigineID','MessaggioID'],'email_allegati'=>['ID','MessaggioID','NomeFile','Mime','Dimensione','Contenuto'],'email_invii'=>['ID','CodiceHash','ScadeIl','EmailEffettiva']] as $table=>$required){
  $q=$p->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);
  if(array_diff($required,$q->fetchAll(PDO::FETCH_COLUMN)))throw new RuntimeException('Struttura delle nuove tabelle email non compatibile: '.$table);
 }
 if(dc_active($p)){
  require_once __DIR__.'/dati_migrazione.php';
  foreach(['email_configurazione','email_messaggi','email_destinatari','email_invii'] as $t){
   if(!isset(dc_columns($p,$t,true)['_Cifrato'])){
    if((int)$p->query('SELECT COUNT(*) FROM '.dc_q($t))->fetchColumn()!==0)throw new RuntimeException('Nuova tabella email con dati non cifrati: richiede migrazione controllata.');
    dc_prepare_table($p,$t);
    $q=$p->prepare("INSERT IGNORE INTO crypto_tabelle (Nome,Stato,Righe,Convertite) VALUES (?,'pronto',0,0)");$q->execute([$t]);
   }
  }
 }

}
function em_admin(array $u): bool{return in_array($u['Ruolo'],['admin','ghost'],true);}
function em_secret_path(string $ref): string {
 if(!preg_match('/^[a-f0-9]{32}$/D',$ref))throw new RuntimeException('Riferimento credenziale non valido.');
 return dirname(__DIR__).'/private/email_'.$ref.'.php';
}
function em_secret(array $config): string {
 $path=em_secret_path($config['SegretoRef']);
 if(!is_file($path))throw new RuntimeException('Credenziale privata di questa configurazione non disponibile.');
 if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
 $secret=require $path;
 if(!is_string($secret)||!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new RuntimeException('Credenziale ponte non valida.');
 return $secret;
}
function em_config(PDO $p,?int $id=null): ?array {
 if($id!==null)return dc_find($p,'email_configurazione',['ID'=>$id]);
 $rows=dc_rows($p,'email_configurazione','WHERE Attiva=1 ORDER BY ID DESC LIMIT 1');return $rows[0]??null;
}
function em_save_config(PDO $p,array $in): int {
 $url=trim((string)($in['url']??''));em_bridge_url($url);
 $sender=strtolower(trim((string)($in['sender']??'')));$name=trim((string)($in['sender_name']??'Gestione'));
 $batch=(int)($in['batch']??20);
 if(isset($in['mode'])&&$in['mode']!=='produzione')throw new RuntimeException('Configurazione obsoleta: ricaricare la pagina. Gli invii usano gli indirizzi selezionati nell’anagrafica.');
 if(!filter_var($sender,FILTER_VALIDATE_EMAIL)||strlen($sender)>254)throw new RuntimeException('Inserire un indirizzo mittente valido.');
 if($batch<1||$batch>20||strlen($name)>200)throw new RuntimeException('Lotto da 1 a 20 e nome mittente fino a 200 caratteri.');
 $old=em_config($p);$secret=trim((string)($in['secret']??''));$reuseSecret=$secret===''&&$old&&is_file(em_secret_path((string)$old['SegretoRef']));
 if($secret===''){$secret=$old?em_secret($old):'';}
 if(!preg_match('/^[a-f0-9]{64}$/D',$secret))throw new RuntimeException('Generare una credenziale segreta di 64 caratteri esadecimali e copiarla nelle proprietà Apps Script.');
 $private=dirname(__DIR__).'/private';
 if(trim((string)@file_get_contents($private.'/.htaccess'))!=='Require all denied')throw new RuntimeException('Ripristinare la protezione private/.htaccess prima di salvare.');
 $ref=$reuseSecret?(string)$old['SegretoRef']:bin2hex(random_bytes(16));$file=em_secret_path($ref);$created=false;
 if(!$reuseSecret){$handle=@fopen($file,'x');if(!$handle)throw new RuntimeException('Cartella private non scrivibile: impossibile salvare la credenziale.');$bytes="<?php\nif (!defined('POIMANAGER_INTERNAL')) { http_response_code(403); exit; }\nreturn ".var_export($secret,true).";\n";try{if(fwrite($handle,$bytes)!==strlen($bytes))throw new RuntimeException('Salvataggio credenziale incompleto.');}finally{fclose($handle);}$created=true;@chmod($file,0600);}
 try{
  $p->beginTransaction();$p->exec('UPDATE email_configurazione SET Attiva=0 WHERE Attiva=1');
  $id=(int)dc_insert($p,'email_configurazione',['URLPonte'=>$url,'Mittente'=>$sender,'NomeMittente'=>$name,'Modalita'=>'produzione','EmailTest'=>'','Lotto'=>$batch,'SegretoRef'=>$ref]);$p->commit();return $id;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();if($created)@unlink($file);throw $e;}
}
function em_ping(PDO $p,array $config,?callable $bridge=null): array {
 $bridge??='em_bridge_call';$r=$bridge($config,em_secret($config),['op'=>'ping']);
 if(empty($r['ok'])||strtolower($r['sender']??'')!==strtolower($config['Mittente']))throw new RuntimeException('Mittente del ponte diverso da quello configurato.');
 $q=$p->prepare('UPDATE email_configurazione SET VerificataIl=UTC_TIMESTAMP() WHERE ID=?');$q->execute([$config['ID']]);return $r;
}
function em_contacts(PDO $p,string $search='',int $type=0,int $list=0): array {
 if(dc_active($p))return em_encrypted_contacts($p,$search,$type,$list);
 $where=[];$args=[];
 if($search!==''){
  $like='%'.str_replace(['=','%','_'],['==','=%','=_'],$search).'%';
  $where[]="(CONVERT(m.Indirizzo USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=' OR CONVERT(a.SOGGETTO USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=' OR CONVERT(s.Nome USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=')";$args=[$like,$like,$like];
 }
 if($type>0){$where[]='a.ID_TIPOLOGIA=?';$args[]=$type;}
 if($list>0){$where[]='EXISTS (SELECT 1 FROM ListaMail lm WHERE lm.IDMail=m.ID AND lm.IDListaMail=?)';$args[]=$list;}
 $sql="SELECT m.ID,m.Indirizzo,m.PEC,m.TipoSoggetto,COALESCE(s.Nome,a.SOGGETTO,'Nominativo non collegato') AS Nome,COALESCE(a.SOGGETTO,'') AS Ente,a.ID AS EnteID,a.ID_TIPOLOGIA FROM Mail m LEFT JOIN Soggetti s ON m.IDSoggetto=s.ID AND ((m.TipoSoggetto='REFERENTE' AND s.Tipo='LR') OR (m.TipoSoggetto='ALTRO' AND s.Tipo='AS')) LEFT JOIN Associazioni a ON a.ID=CASE WHEN m.TipoSoggetto='ASSOCIAZIONE' THEN m.IDSoggetto ELSE s.ID_Associazione END";
 $q=$p->prepare($sql.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY m.ID');$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function em_sanitize(string $html): string {
 if(strlen($html)>60000)throw new RuntimeException('Messaggio troppo lungo.');
 if(!class_exists('DOMDocument'))throw new RuntimeException('Occorre l’estensione PHP DOM per il testo formattato.');
 $dom=new DOMDocument();$old=libxml_use_internal_errors(true);
 try{$dom->loadHTML('<?xml encoding="UTF-8"><div id="email-root">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
 $root=$dom->getElementById('email-root');if(!$root)throw new RuntimeException('Testo HTML non valido.');
 $allowed=['p','div','br','b','strong','i','em','u','ul','ol','li','a','span','blockquote','font'];
 $walk=function(DOMNode $node)use(&$walk,$allowed,$dom):void{
  foreach(iterator_to_array($node->childNodes) as $child){
   if($child instanceof DOMElement){
    $tag=strtolower($child->tagName);
    if(in_array($tag,['script','style','iframe','object','embed','svg','math','img','form','input','link','meta'],true)){$node->removeChild($child);continue;}
    $href=$child->getAttribute('href');$style=$child->getAttribute('style');$color=$child->getAttribute('color');
    foreach(iterator_to_array($child->attributes) as $attr)$child->removeAttribute($attr->name);
    if($tag==='a'&&preg_match('~^https?://[^\\s]+$~iD',$href)&&!preg_match('/[\\x00-\\x20]/',$href))$child->setAttribute('href',$href);
    if(preg_match('/(?:^|;)\\s*color\\s*:\\s*(#[a-f0-9]{3,8}|[a-z]+|rgb\\([0-9, %]+\\))\\s*(?:;|$)/i',$style,$match))$color=$match[1];
    if(preg_match('/^(#[a-f0-9]{3,8}|[a-z]+|rgb\\([0-9, %]+\\))$/iD',$color))$child->setAttribute('style','color:'.$color);
    $walk($child);
    if(!in_array($tag,$allowed,true)){while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);}
   }elseif(!($child instanceof DOMText))$node->removeChild($child);
  }
 };
 $walk($root);$result='';foreach($root->childNodes as $node)$result.=$dom->saveHTML($node);return $result;
}
function em_save_draft(PDO $p,array $in): int {
 $subject=trim((string)($in['subject']??''));$text=trim((string)($in['text']??''));$html=em_sanitize((string)($in['html']??''));
 if(strlen($subject)>250||preg_match('/[\r\n]/',$subject)||strlen($text)>30000)throw new RuntimeException('Oggetto massimo 250 byte, senza ritorni a capo; testo massimo 30.000 byte.');
 if($subject===''||$text==='')throw new RuntimeException('Salvataggio non eseguito: inserire sia l’oggetto sia il testo della mail.');
 $selected=em_resolve_recipients($p,$in['mail_ids']??[]);$upload=$in['attachment']??null;$attachment=null;
 if(is_array($upload)&&($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){if(($upload['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new RuntimeException('Caricamento allegato non riuscito.');$name=trim((string)($upload['name']??''));$size=(int)($upload['size']??0);$mime=(string)($upload['type']??'application/octet-stream');$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));$allowed=['pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','csv'=>'text/csv','txt'=>'text/plain','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];if($size<1||$size>5242880||!isset($allowed[$ext]))throw new RuntimeException('Allegato non valido: usare PDF, Office, CSV, TXT, JPG o PNG fino a 5 MB.');$data=file_get_contents((string)$upload['tmp_name']);if($data===false)throw new RuntimeException('Impossibile leggere l’allegato.');$attachment=['name'=>substr($name,0,180),'mime'=>$allowed[$ext],'size'=>$size,'data'=>$data];}
 $id=(int)($in['id']??0);$lock=$id?em_campaign_lock($p,$id):null;
 try{
  $p->beginTransaction();
  if($id){
   $old=dc_find($p,'email_messaggi',['ID'=>$id],true);
   if(!$old||!in_array($old['Stato'],['bozza','preparato'],true)||(int)$old['Versione']!==(int)($in['version']??0))throw new RuntimeException('Bozza già modificata in un’altra scheda.');
   if($old['Stato']==='preparato'){$q=$p->prepare("SELECT COUNT(*) FROM email_invii WHERE MessaggioID=? AND Stato IN ('inviato','in_corso','da_verificare')");$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('La campagna contiene email inviate o con esito incerto: verificare gli esiti o duplicarla per apportare modifiche.');$q=$p->prepare('DELETE FROM email_invii WHERE MessaggioID=?');$q->execute([$id]);}
   dc_update($p,'email_messaggi',['ID'=>$id],['Oggetto'=>$subject,'HTML'=>$html,'Testo'=>$text,'Stato'=>'bozza','ConfigID'=>null,'ConfermaLettura'=>array_key_exists('conferma_lettura',$in)?(int)(bool)$in['conferma_lettura']:1,'Versione'=>(int)$old['Versione']+1]);
   $q=$p->prepare('DELETE FROM email_destinatari WHERE MessaggioID=?');$q->execute([$id]);
  }else{$id=(int)dc_insert($p,'email_messaggi',['Oggetto'=>$subject,'HTML'=>$html,'Testo'=>$text,'ConfermaLettura'=>array_key_exists('conferma_lettura',$in)?(int)(bool)$in['conferma_lettura']:1]);}
  if($attachment){$q=$p->prepare('DELETE FROM email_allegati WHERE MessaggioID=?');$q->execute([$id]);$q=$p->prepare('INSERT INTO email_allegati (MessaggioID,NomeFile,Mime,Dimensione,Contenuto) VALUES (?,?,?,?,?)');$q->execute([$id,$attachment['name'],$attachment['mime'],$attachment['size'],$attachment['data']]);}
  foreach($selected as $email=>$c)dc_insert($p,'email_destinatari',['MessaggioID'=>$id,'MailOrigineID'=>$c['ID'],'Nome'=>$c['Nome'],'Email'=>$email,'Tipo'=>$c['TipoSoggetto'],'Ente'=>$c['Ente']]);
  $p->commit();return $id;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 finally{if($lock!==null)em_campaign_unlock($p,$lock);}
}
function em_token(string $id): string {
 if(!defined('POIMANAGER_INTERNAL'))define('POIMANAGER_INTERNAL',true);
 $s=require dirname(__DIR__).'/private/security.php';$key=base64_decode($s['key']??'',true);
 if($key===false||strlen($key)!==32)throw new RuntimeException('Chiave conferme non valida.');
 return hash_hmac('sha256','email-confirm-v1:'.$id,$key);
}
function em_require_production(array $config):void {
 if(($config['Modalita']??'')!=='produzione')throw new RuntimeException('Configurazione precedente non utilizzabile per nuovi invii. Salvare la configurazione attuale e creare una nuova campagna dalla rubrica.');
}
function em_campaign_sendable(PDO $p,int $id,array $config):bool {
 if(($config['Modalita']??'')!=='produzione')return false;
 $q=$p->prepare("SELECT COUNT(*) FROM email_destinatari WHERE MessaggioID=? AND (MailOrigineID IS NULL OR Tipo NOT IN ('ASSOCIAZIONE','REFERENTE','ALTRO'))");$q->execute([$id]);return (int)$q->fetchColumn()===0;
}
// Stesso lock per modifica, preparazione, invio, verifica ed eliminazione.
function em_campaign_lock(PDO $p,int $id):string {
 $lock='email_send_'.$id;$q=$p->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);
 if((int)$q->fetchColumn()!==1)throw new RuntimeException('Operazione già in corso per questa campagna. Attendere e aggiornare il registro.');
 return $lock;
}
function em_campaign_unlock(PDO $p,string $lock):void {
 $q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);
}
function em_prepare(PDO $p,int $id): int {
 $config=em_config($p);if(!$config)throw new RuntimeException('Salvare prima la configurazione mail.');
 if(!$config['VerificataIl']){$ping=em_ping($p,$config);$config=em_config($p,(int)$config['ID'])??$config;}
 em_require_production($config);
 $lock=em_campaign_lock($p,$id);
 try{
  $p->beginTransaction();
  $m=dc_find($p,'email_messaggi',['ID'=>$id],true);
  if(!$m)throw new RuntimeException('Messaggio non trovato.');
  if(!em_campaign_sendable($p,$id,$config))throw new RuntimeException('La campagna deve contenere soltanto destinatari selezionati dalla rubrica.');
  if($m['Stato']==='preparato'){if(!em_campaign_sendable($p,$id,em_config($p,(int)$m['ConfigID'])??[]))throw new RuntimeException('Campagna precedente non utilizzabile. Creare una nuova bozza dalla rubrica.');$q=$p->prepare('SELECT COUNT(*) FROM email_invii WHERE MessaggioID=?');$q->execute([$id]);$count=(int)$q->fetchColumn();$p->commit();return $count;}
  $dest=dc_rows($p,'email_destinatari','WHERE MessaggioID=? ORDER BY ID',[$id]);
  if(!$dest)throw new RuntimeException('Bozza senza destinatari.');
  if(trim($m['Oggetto'])===''||trim($m['Testo'])==='')throw new RuntimeException('Completare oggetto e testo prima di preparare l’invio.');
  $current=array_values(em_resolve_recipients($p,array_column($dest,'MailOrigineID')));
  $byId=[];foreach($current as $r)$byId[(int)$r['ID']]=$r;
  foreach($dest as $d){$r=$byId[(int)$d['MailOrigineID']]??null;if(!$r||$d['Email']!==strtolower(trim($r['Indirizzo']))||$d['Nome']!==$r['Nome']||$d['Ente']!==$r['Ente']||$d['Tipo']!==$r['TipoSoggetto'])throw new RuntimeException('La rubrica è cambiata. Riaprire e salvare la bozza per aggiornare i destinatari prima di preparare.');}
  foreach($dest as $d){$job=bin2hex(random_bytes(16));$to=$d['Email'];dc_insert($p,'email_invii',['ID'=>$job,'MessaggioID'=>$id,'DestinatarioID'=>$d['ID'],'EmailEffettiva'=>$to,'CodiceHash'=>hash('sha256',em_token($job)),'ScadeIl'=>gmdate('Y-m-d H:i:s',time()+365*86400)]);}
  $q=$p->prepare("UPDATE email_messaggi SET Stato='preparato',ConfigID=?,Versione=Versione+1 WHERE ID=?");$q->execute([$config['ID'],$id]);$p->commit();return count($dest);
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 finally{em_campaign_unlock($p,$lock);}
}
function em_payload(array $row,array $config): array {
 em_require_production($config);
 if(empty($row['MailOrigineID'])||!in_array($row['Tipo']??'', ['ASSOCIAZIONE','REFERENTE','ALTRO'],true)||!filter_var($row['Email']??'',FILTER_VALIDATE_EMAIL)||strtolower(trim((string)($row['EmailEffettiva']??'')))!==strtolower(trim((string)$row['Email'])))throw new RuntimeException('Destinatario effettivo non coerente con la rubrica congelata. Creare una nuova campagna; nessun invio effettuato.');
 if(!isset($row['CodiceHash'])||!hash_equals((string)$row['CodiceHash'],hash('sha256',em_token($row['ID']))))throw new RuntimeException('Invio preparato con una chiave utenti diversa. Ricreare la campagna come nuova bozza; non reinviare gli esiti incerti.');
 $name=$row['Nome'];$withReceipt=(int)($row['ConfermaLettura']??1)===1;$link='https://poimanager.altervista.org/email_conferma.php?t='.em_token($row['ID']);
 $html=str_replace('{nome}',em_h($name),$row['HTML']);$text=str_replace('{nome}',$name,$row['Testo']);
 $subject=$row['Oggetto'];
 $out=['op'=>'send','job'=>$row['ID'],'to'=>$row['EmailEffettiva'],'subject'=>$subject,'name'=>$config['NomeMittente'],'text'=>$withReceipt?$text."\n\nConferma la lettura: ".$link."\nLink personale: non inoltrarlo.":$text,'html'=>'<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.6">'.$html.($withReceipt?'<p><a href="'.em_h($link).'" style="display:inline-block;padding:14px 22px;background:#175cd3;color:#fff;text-decoration:none;border-radius:8px">Conferma la lettura</a></p><p>Link personale: non inoltrarlo.</p>':'').'</div>'];if(!empty($row['Allegato']))$out['attachments']=[$row['Allegato']];return $out;
}
function em_send_batch(PDO $p,int $id,?callable $bridge=null): array {
 $bridge??='em_bridge_call';$lock=em_campaign_lock($p,$id);
 $sent=0;$notice='';$start=time();
 try{
  $q=$p->prepare("SELECT m.*,c.Modalita FROM email_messaggi m JOIN email_configurazione c ON c.ID=m.ConfigID WHERE m.ID=? AND m.Stato='preparato'");$q->execute([$id]);$message=$q->fetch();if(!$message)throw new RuntimeException('Preparare prima il messaggio.');
  $config=em_config($p,(int)$message['ConfigID']);
  if(!$config||!em_campaign_sendable($p,$id,$config))throw new RuntimeException('Campagna precedente non utilizzabile per inviare. Creare una nuova bozza dalla rubrica.');
  em_require_production($config);$secret=em_secret($config);
  $ping=$bridge($config,$secret,['op'=>'ping']);if(empty($ping['ok'])||strtolower($ping['sender']??'')!==strtolower($config['Mittente']))throw new RuntimeException('Il mittente del ponte non corrisponde alla campagna.');
  $limit=min((int)$config['Lotto'],max(0,(int)($ping['quota']??0)));
  if(!$limit)return ['sent'=>0,'notice'=>'Quota Gmail esaurita. Riprendere più tardi.'];
  $jobs=dc_rows($p,'email_invii',"WHERE MessaggioID=? AND Stato='da_inviare' ORDER BY DestinatarioID LIMIT ".$limit,[$id]);
  foreach($jobs as $row){
   dc_active($p);
   $dest=dc_find($p,'email_destinatari',['ID'=>$row['DestinatarioID']]);$plainMessage=dc_find($p,'email_messaggi',['ID'=>$id]);$aq=$p->prepare('SELECT NomeFile,Mime,Dimensione,Contenuto FROM email_allegati WHERE MessaggioID=? LIMIT 1');$aq->execute([$id]);$attachment=$aq->fetch(PDO::FETCH_ASSOC);if($attachment)$attachment=['filename'=>$attachment['NomeFile'],'mimeType'=>$attachment['Mime'],'content'=>base64_encode($attachment['Contenuto'])];
   $row=array_merge($plainMessage,$dest,$row,['Allegato'=>$attachment]);
   if(time()-$start>150){$notice='Tempo del lotto terminato: riprendere i rimanenti.';break;}
   $payload=em_payload($row,$config);
   $claim=$p->prepare("UPDATE email_invii SET Stato='in_corso',IniziatoIl=UTC_TIMESTAMP() WHERE ID=? AND Stato='da_inviare'");$claim->execute([$row['ID']]);if(!$claim->rowCount())continue;
   try{
    $r=$bridge($config,$secret,$payload);
    if(($r['status']??'')==='sent'){
     $update=$p->prepare("UPDATE email_invii SET Stato='inviato',InviatoIl=COALESCE(InviatoIl,UTC_TIMESTAMP()),Nota='' WHERE ID=?");$update->execute([$row['ID']]);$sent++;
    }elseif(($r['status']??'')==='not_sent'){
     $update=$p->prepare("UPDATE email_invii SET Stato='da_inviare',Nota='Quota esaurita prima dell’invio' WHERE ID=?");$update->execute([$row['ID']]);$notice='Quota esaurita.';break;
    }else throw new RuntimeException('Esito del ponte non definitivo.');
   }catch(Throwable $e){
    $update=$p->prepare("UPDATE email_invii SET Stato='da_verificare',Nota='Esito incerto: verificare il ponte prima di qualsiasi nuovo invio' WHERE ID=?");$update->execute([$row['ID']]);
    $notice='Un invio ha esito incerto. Non sarà ritentato automaticamente; usare Verifica esiti.';break;
   }
  }
  return ['sent'=>$sent,'notice'=>$notice];
 }finally{$q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
function em_reconcile(PDO $p,int $id,?callable $bridge=null): int {
 $bridge??='em_bridge_call';$lock=em_campaign_lock($p,$id);$count=0;$start=time();
 try{
  $q=$p->prepare("SELECT i.ID,m.ConfigID FROM email_invii i JOIN email_messaggi m ON m.ID=i.MessaggioID WHERE i.MessaggioID=? AND (i.Stato='da_verificare' OR (i.Stato='in_corso' AND i.IniziatoIl<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 MINUTE))) ORDER BY i.IniziatoIl,i.ID");$q->execute([$id]);$rows=$q->fetchAll();
  foreach($rows as $row){
   if(time()-$start>120)throw new RuntimeException('Tempo di verifica terminato. Gli esiti già risolti sono salvati; riprendere dal registro.');
   $c=em_config($p,(int)$row['ConfigID']);
   if(!$c)throw new RuntimeException('Configurazione della campagna non disponibile.');
   $r=$bridge($c,em_secret($c),['op'=>'status','job'=>$row['ID']]);$status=$r['status']??'';
   if($status==='sent'){
    $u=$p->prepare("UPDATE email_invii SET Stato='inviato',InviatoIl=COALESCE(InviatoIl,UTC_TIMESTAMP()),Nota='Confermato dal registro del ponte' WHERE ID=? AND Stato IN ('in_corso','da_verificare')");$u->execute([$row['ID']]);$count+=$u->rowCount();
   }elseif($status==='not_sent'){
    // Solo un esito esplicito di mancato invio permette il successivo tentativo manuale.
    $u=$p->prepare("UPDATE email_invii SET Stato='da_inviare',IniziatoIl=NULL,Nota='Mancato invio confermato dal ponte: pronto per un nuovo invio manuale' WHERE ID=? AND Stato IN ('in_corso','da_verificare') AND InviatoIl IS NULL AND ConfermatoIl IS NULL");$u->execute([$row['ID']]);$count+=$u->rowCount();
   }else{
    // unknown, uncertain o risposte non definitive non autorizzano mai un reinvio.
    $u=$p->prepare("UPDATE email_invii SET Stato='da_verificare',Nota='Il ponte non conferma un esito definitivo. Nessun reinvio autorizzato.' WHERE ID=? AND Stato IN ('in_corso','da_verificare')");$u->execute([$row['ID']]);
   }
  }
  return $count;
 }finally{em_campaign_unlock($p,$lock);}
}
function em_confirm(PDO $p,string $token): bool {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
 $hash=hash('sha256',$token);
 $q=$p->prepare("UPDATE email_invii SET ConfermatoIl=COALESCE(ConfermatoIl,UTC_TIMESTAMP()) WHERE CodiceHash=? AND ScadeIl>=UTC_TIMESTAMP() AND Stato IN ('inviato','in_corso','da_verificare')");$q->execute([$hash]);
 $q=$p->prepare('SELECT ConfermatoIl FROM email_invii WHERE CodiceHash=?');$q->execute([$hash]);return (bool)$q->fetchColumn();
}

function em_send_nonce(): string {
 $nonce=bin2hex(random_bytes(16));$_SESSION['email_send_nonces'][$nonce]=time();
 if(count($_SESSION['email_send_nonces'])>200)$_SESSION['email_send_nonces']=array_slice($_SESSION['email_send_nonces'],-100,null,true);
 return $nonce;
}
function em_consume_send_nonce(string $nonce): void {
 $time=$_SESSION['email_send_nonces'][$nonce]??0;
 unset($_SESSION['email_send_nonces'][$nonce]);
 if(!$time||time()-$time>1800)throw new RuntimeException('Richiesta già elaborata o scaduta. Ricaricare il registro prima di inviare.');
}

function em_encrypted_contacts(PDO $p,string $search,int $type,int $list):array {
 $entities=[];foreach(dc_iter($p,'Associazioni') as $r)$entities[$r['ID']]=$r;
 $people=[];foreach(dc_iter($p,'Soggetti') as $r)$people[$r['ID']]=$r;
 $members=[];
 if($list>0){$q=$p->prepare('SELECT IDMail FROM ListaMail WHERE IDListaMail=?');$q->execute([$list]);$members=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);}
 $rows=[];
 foreach(dc_iter($p,'Mail') as $m){
  if($list>0&&!isset($members[$m['ID']]))continue;
  $person=null;$entity=null;
  if($m['TipoSoggetto']==='ASSOCIAZIONE')$entity=$entities[$m['IDSoggetto']]??null;
  else{$person=$people[$m['IDSoggetto']]??null;if($person&&(($m['TipoSoggetto']==='REFERENTE'&&$person['Tipo']==='LR')||($m['TipoSoggetto']==='ALTRO'&&$person['Tipo']==='AS')))$entity=$entities[$person['ID_Associazione']]??null;else $person=null;}
  if($type>0&&(int)($entity['ID_TIPOLOGIA']??0)!==$type)continue;
  $r=['ID'=>$m['ID'],'Indirizzo'=>$m['Indirizzo'],'PEC'=>$m['PEC'],'TipoSoggetto'=>$m['TipoSoggetto'],'Nome'=>$person['Nome']??$entity['SOGGETTO']??'Nominativo non collegato','Ente'=>$entity['SOGGETTO']??'','EnteID'=>$entity['ID']??null,'ID_TIPOLOGIA'=>$entity['ID_TIPOLOGIA']??null];
  if($search!==''&&!dc_contains($r['Nome'].' '.$r['Ente'].' '.$r['Indirizzo'],$search))continue;
  $rows[]=$r;
 }
 usort($rows,fn($a,$b)=>(int)$a['ID']<=>(int)$b['ID']);return $rows;
}
function em_campaigns(PDO $p):array {
 $stats=[];foreach($p->query("SELECT MessaggioID,COUNT(*) AS Totale,SUM(Stato='inviato') AS Inviate,SUM(Stato IN ('in_corso','da_verificare')) AS Incerte,MAX(InviatoIl) AS UltimoInvio FROM email_invii GROUP BY MessaggioID") as $r)$stats[$r['MessaggioID']]=$r;
 $dest=[];foreach($p->query('SELECT MessaggioID,COUNT(*) AS N FROM email_destinatari GROUP BY MessaggioID') as $r)$dest[$r['MessaggioID']]=(int)$r['N'];
 $out=[];foreach(dc_iter($p,'email_messaggi') as $r){unset($r['HTML'],$r['Testo']);$st=$stats[$r['ID']]??['Totale'=>0,'Inviate'=>0,'Incerte'=>0,'UltimoInvio'=>null];$r['Destinatari']=$dest[$r['ID']]??0;$r['Inviate']=(int)$st['Inviate'];$r['Incerte']=(int)$st['Incerte'];$r['UltimoInvio']=$st['UltimoInvio']??null;$r['StatoEtichetta']=$r['Stato']==='bozza'?'Bozza':($r['Incerte']?'Da verificare':((int)$st['Totale']>0&&$r['Inviate']===(int)$st['Totale']?'Inviata':($r['Inviate']?'Parzialmente inviata':'Elaborata')));$out[]=$r;}
 usort($out,fn($a,$b)=>(int)$b['ID']<=>(int)$a['ID']);return $out;
}
function em_logs(PDO $p,int $id=0):array {
 $rows=dc_rows($p,'email_invii',($id?'WHERE MessaggioID=? ':'').'ORDER BY IniziatoIl DESC,ID',$id?[$id]:[]);
 $messages=[];
 foreach($rows as &$r){$d=dc_find($p,'email_destinatari',['ID'=>$r['DestinatarioID']]);$m=$messages[$r['MessaggioID']]??=dc_find($p,'email_messaggi',['ID'=>$r['MessaggioID']]);$r['Nome']=$d['Nome'];$r['Email']=$d['Email'];$r['Ente']=$d['Ente']??'';$r['Oggetto']=$m['Oggetto'];}unset($r);return $rows;
}



function em_resolve_recipients(PDO $p,$ids,bool $strict=true):array {
 if(!is_array($ids)||count($ids)>2000)throw new RuntimeException('Selezione destinatari non valida (massimo 2000 indirizzi).');
 $ids=array_values(array_unique(array_map('intval',$ids)));$wanted=array_fill_keys($ids,true);$selected=[];$found=[];
 foreach(em_contacts($p) as $c)if(isset($wanted[(int)$c['ID']])){
  $email=strtolower(trim((string)$c['Indirizzo']));
  if(!$c['EnteID']||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254){if($strict)throw new RuntimeException('Indirizzo non valido o non collegato a un ente.');continue;}
  $found[(int)$c['ID']]=true;if(!isset($selected[$email]))$selected[$email]=$c;
 }
 if($strict&&count($found)!==count($ids))throw new RuntimeException('Indirizzi eliminati o modificati: aggiornare la selezione dalla rubrica.');
 return $selected;
}
function em_address_book(PDO $p):array {
 $people=[];foreach(dc_iter($p,'Soggetti') as $r)$people[$r['ID_Associazione']][]=['nome'=>$r['Nome'],'ruolo'=>$r['Tipo']];
 $lists=[];foreach($p->query('SELECT IDMail,IDListaMail FROM ListaMail') as $r)$lists[$r['IDMail']][]=(int)$r['IDListaMail'];
 $out=[];foreach(em_contacts($p) as $c){$email=strtolower(trim((string)$c['Indirizzo']));if(!$c['EnteID']||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254)continue;
  $c['Indirizzo']=$email;$c['Ruolo']=['REFERENTE'=>'LR','ALTRO'=>'AS','ASSOCIAZIONE'=>'ENTE'][$c['TipoSoggetto']]??'';$c['Riferimenti']=$people[$c['EnteID']]??[];$c['Liste']=$lists[$c['ID']]??[];$out[]=$c;
 }
 return $out;
}
function em_duplicate(PDO $p,int $id,bool $withRecipients,?string $subject=null):int {
 $p->beginTransaction();
 try{
  $source=dc_find($p,'email_messaggi',['ID'=>$id],true);if(!$source)throw new RuntimeException('Mail non trovata.');
  $dest=$withRecipients?dc_rows($p,'email_destinatari','WHERE MessaggioID=?',[$id]):[];
  $resolved=em_resolve_recipients($p,array_filter(array_column($dest,'MailOrigineID')),false);
  $new=(int)dc_insert($p,'email_messaggi',['Oggetto'=>$subject??$source['Oggetto'],'HTML'=>em_sanitize($source['HTML']),'Testo'=>$source['Testo'],'ConfermaLettura'=>(int)($source['ConfermaLettura']??1)]);
  foreach($resolved as $email=>$c)dc_insert($p,'email_destinatari',['MessaggioID'=>$new,'MailOrigineID'=>$c['ID'],'Nome'=>$c['Nome'],'Email'=>$email,'Tipo'=>$c['TipoSoggetto'],'Ente'=>$c['Ente']]);
  $p->commit();return $new;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
function em_duplicate_reminder(PDO $p,int $id):int {
 $p->beginTransaction();
 try{
  $source=dc_find($p,'email_messaggi',['ID'=>$id],true);if(!$source)throw new RuntimeException('Mail non trovata.');
  $sent=dc_rows($p,'email_invii','WHERE MessaggioID=? AND Stato=\'inviato\' AND ConfermatoIl IS NULL',[$id]);$wanted=array_fill_keys(array_map(static fn($r)=>(int)$r['DestinatarioID'],$sent),true);
  $dest=array_values(array_filter(dc_rows($p,'email_destinatari','WHERE MessaggioID=?',[$id]),static fn($r)=>isset($wanted[(int)$r['ID']])));
  if(!$dest)throw new RuntimeException('Non ci sono destinatari inviati senza conferma ricezione.');
  $resolved=em_resolve_recipients($p,array_filter(array_column($dest,'MailOrigineID')),false);$subject=trim((string)$source['Oggetto']).' - sollecito';
  $new=(int)dc_insert($p,'email_messaggi',['Oggetto'=>$subject,'HTML'=>em_sanitize($source['HTML']),'Testo'=>$source['Testo'],'ConfermaLettura'=>1]);
  foreach($resolved as $email=>$c)dc_insert($p,'email_destinatari',['MessaggioID'=>$new,'MailOrigineID'=>$c['ID'],'Nome'=>$c['Nome'],'Email'=>$email,'Tipo'=>$c['TipoSoggetto'],'Ente'=>$c['Ente']]);
  $p->commit();return $new;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
function em_delete(PDO $p,int $id,int $version):void {
 $q=$p->prepare('SELECT GET_LOCK(?,0)');$lock='email_send_'.$id;$q->execute([$lock]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Invio in corso: attendere il termine prima di eliminare.');
 try{
  $p->beginTransaction();$m=dc_find($p,'email_messaggi',['ID'=>$id],true);
  if(!$m||(int)$m['Versione']!==$version)throw new RuntimeException('Mail eliminata o modificata: aggiornare l’archivio.');
  $q=$p->prepare("SELECT COUNT(*) FROM email_invii WHERE MessaggioID=? AND Stato IN ('in_corso','da_verificare')");$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Verificare gli esiti incerti prima di eliminare la mail.');
  foreach(['email_invii','email_destinatari'] as $table){$q=$p->prepare('DELETE FROM '.dc_q($table).' WHERE MessaggioID=?');$q->execute([$id]);}
  $q=$p->prepare('DELETE FROM email_messaggi WHERE ID=?');$q->execute([$id]);$p->commit();
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
 finally{$q=$p->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);}
}
