<?php
declare(strict_types=1);
require_once __DIR__.'/anagrafica.php';
function scheda_revision(array $entity):string {return hash('sha256',json_encode($entity,JSON_THROW_ON_ERROR));}
function scheda_runts_revision(array $record):string {
 $values=[];foreach(['CodiceFiscale','Denominazione','Cognome','Nome'] as $field)$values[$field]=$record[$field]??null;
 return hash('sha256',json_encode($values,JSON_THROW_ON_ERROR));
}
function scheda_owner(PDO $p,int $entityId,string $owner):array {
 if($owner==='ente')return ['TipoSoggetto'=>'ASSOCIAZIONE','IDSoggetto'=>$entityId];
 if(!preg_match('/^persona:([1-9][0-9]*)$/D',$owner,$m))throw new RuntimeException('Destinatario del recapito non valido.');
 $person=dc_find($p,'Soggetti',['ID'=>(int)$m[1]]);
 if(!$person||(int)$person['ID_Associazione']!==$entityId)throw new RuntimeException('Persona non appartenente a questo ente.');
 return ['TipoSoggetto'=>$person['Tipo']==='LR'?'REFERENTE':'ALTRO','IDSoggetto'=>(int)$person['ID']];
}
function scheda_contact(PDO $p,array $entity,string $table,int $id):array {
 foreach($entity[$table] as $r)if((int)$r['ID']===$id)return $r;
 throw new RuntimeException('Recapito non appartenente a questo ente.');
}
function scheda_contact_save(PDO $p,int $entityId,string $table,array $in):int {
 if(!in_array($table,['Mail','Telefono'],true))throw new RuntimeException('Tipo di recapito non valido.');
 $entity=enti_detail($p,$entityId);if(!$entity)throw new RuntimeException('Ente non trovato.');
 $id=(int)($in['contact_id']??0);$owner=scheda_owner($p,$entityId,(string)($in['owner']??'ente'));
 if($id){$old=scheda_contact($p,$entity,$table,$id);if($old['TipoSoggetto']!==$owner['TipoSoggetto']||(int)$old['IDSoggetto']!==$owner['IDSoggetto'])throw new RuntimeException('Il titolare di un recapito esistente non può cambiare.');}
 $field=$table==='Mail'?'Indirizzo':'Telefono';$value=trim((string)($in['value']??''));
 if($table==='Mail'){$value=strtolower($value);if(strlen($value)>254||!filter_var($value,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Indirizzo email non valido.');}
 elseif(strlen(preg_replace('/[^0-9]/','',$value))<2||strlen($value)>50||!preg_match('/^[+0-9][0-9 ()+.\/-]{1,49}$/D',$value))throw new RuntimeException('Telefono non valido: da 2 a 50 caratteri, cifre e separatori telefonici.');
 foreach($entity[$table] as $r)if((int)$r['ID']!==$id&&$r['TipoSoggetto']===$owner['TipoSoggetto']&&(int)$r['IDSoggetto']===$owner['IDSoggetto']&&scheda_contact_normal($table,$r[$field])===scheda_contact_normal($table,$value))throw new RuntimeException('Recapito già presente per questo titolare.');
 $values=$owner+[$field=>$value,'Predefinito'=>isset($in['preferred'])?1:0];
 if($table==='Mail')$values['PEC']=isset($in['pec'])?1:0;
 if($values['Predefinito']){
  $q=$p->prepare('UPDATE '.dc_q($table).' SET Predefinito=0 WHERE TipoSoggetto=? AND IDSoggetto=?');$q->execute([$owner['TipoSoggetto'],$owner['IDSoggetto']]);
 }
 if($id)dc_update($p,$table,['ID'=>$id],$values);else $id=(int)dc_insert($p,$table,$values);
 return $id;
}
function scheda_contact_normal(string $table,string $value):string {return $table==='Mail'?strtolower(trim($value)):(preg_replace('/[^0-9]/','',$value)??'');}
function scheda_entity_save(PDO $p,int $id,array $in):void {
 $name=enti_upper(trim((string)($in['SOGGETTO']??'')));$cf=enti_upper(preg_replace('/\s+/u','',(string)($in['CODICEFISCALE']??''))??'');
 if($name===''||strlen($name)>768)throw new RuntimeException('Denominazione obbligatoria, massimo 768 byte.');
 if(strlen($cf)>16||($cf!==''&&!preg_match('/^[A-Z0-9]+$/D',$cf)))throw new RuntimeException('Codice fiscale: massimo 16 caratteri alfanumerici.');
 $type=filter_var($in['ID_TIPOLOGIA']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 $t=$type?dc_find($p,'Tipologia',['ID'=>$type]):null;if(!$t)throw new RuntimeException('Tipologia non valida.');
 $ets=enti_is_ets($t['Tipologia']);
 dc_update($p,'Associazioni',['ID'=>$id],['SOGGETTO'=>$name,'CODICEFISCALE'=>$cf!==''?$cf:null,'ID_TIPOLOGIA'=>$type,'TAVOLO'=>$ets&&isset($in['TAVOLO'])?1:0,'DIRETTIVO_DELEGAZIONE'=>$ets&&isset($in['DIRETTIVO_DELEGAZIONE'])?1:0]);
}
function scheda_person_save(PDO $p,int $entityId,array $in):int {
 $id=(int)($in['person_id']??0);$name=enti_upper(trim((string)($in['name']??'')));$type=(string)($in['type']??'');
 if($name===''||strlen($name)>768||!in_array($type,['LR','AS'],true))throw new RuntimeException('Nome e ruolo della persona non validi.');
 if($id){$old=dc_find($p,'Soggetti',['ID'=>$id]);if(!$old||(int)$old['ID_Associazione']!==$entityId||$old['Tipo']!==$type)throw new RuntimeException('Persona o ruolo non appartenenti a questa scheda.');dc_update($p,'Soggetti',['ID'=>$id],['Nome'=>$name]);}
 else $id=(int)dc_insert($p,'Soggetti',['ID_Associazione'=>$entityId,'Nome'=>$name,'Tipo'=>$type]);
 if(trim((string)($in['email']??''))!=='')scheda_contact_save($p,$entityId,'Mail',['owner'=>'persona:'.$id,'value'=>$in['email']]);
 return $id;
}
function scheda_runts(PDO $p,array $entity):array {
 $matches=[];$cf=enti_upper(preg_replace('/\s+/u','',(string)($entity['CODICEFISCALE']??''))??'');
 foreach(dc_iter($p,'Runts') as $r){
  $rCf=enti_upper(preg_replace('/\s+/u','',(string)($r['CodiceFiscale']??''))??'');
  if($rCf!==''&&(($cf!==''&&$rCf===$cf)||($cf===''&&enti_upper(trim((string)$r['Denominazione']))===enti_upper(trim($entity['SOGGETTO'])))))$matches[]=$r;
 }
 if(count($matches)!==1)return ['status'=>count($matches)>1?'AMBIGUO':'MISSING','record'=>null];
 $r=$matches[0];$name=enti_upper(trim(($r['Cognome']??'').' '.($r['Nome']??'')));$personMatch=$name==='';
 foreach($entity['Persone'] as $person)if($person['Tipo']==='LR'&&enti_upper(trim($person['Nome']))===$name)$personMatch=true;
 $aligned=enti_upper(trim($entity['SOGGETTO']))===enti_upper(trim((string)$r['Denominazione']))&&$cf===enti_upper(trim((string)$r['CodiceFiscale']))&&$personMatch;
 return ['status'=>$aligned?'ON':'OFF','record'=>$r];
}
function scheda_cesvot(PDO $p,array $entity):array {
 $out=['Mail'=>[],'Telefono'=>[]];
 foreach(['Mail'=>'CesvotMail','Telefono'=>'CesvotTelefono'] as $table=>$source){
  $field=$table==='Mail'?'Mail':'Telefono';$target=$table==='Mail'?'Indirizzo':'Telefono';$seen=[];
  foreach($entity[$table] as $r)if($r['TipoSoggetto']==='ASSOCIAZIONE')$seen[scheda_contact_normal($table,$r[$target])]=true;
  foreach(dc_iter($p,$source) as $r){
   if(enti_upper(trim((string)($r['Denominazione']??'')))!==enti_upper(trim($entity['SOGGETTO'])))continue;
   $value=trim((string)($r[$field]??''));$normal=scheda_contact_normal($table,$value);
   if($normal===''||isset($seen[$normal]))continue;
   if($table==='Mail'&&(!filter_var($value,FILTER_VALIDATE_EMAIL)||strlen($value)>254))continue;
   if($table==='Telefono'&&(strlen(preg_replace('/[^0-9]/','',$value))<2||strlen($value)>50||!preg_match('/^[+0-9][0-9 ()+.\/-]{1,49}$/D',$value)))continue;
   $out[$table][]=['ID'=>(int)$r['ID'],'value'=>$value];$seen[$normal]=true;
  }
 }
 return $out;
}
function scheda_needed_panels(string $action,array $in):array {
 $map=['entity_save'=>['associazioni'],'person_save'=>['soggetti'],'person_delete'=>['soggetti','mail','telefono','maillist'],'mail_save'=>['mail'],'mail_delete'=>['mail','maillist'],'phone_save'=>['telefono'],'phone_delete'=>['telefono'],'entity_delete'=>['associazioni','soggetti','mail','telefono','maillist'],'runts_align'=>['associazioni','runts'],'cesvot_import'=>[($in['table']??'')==='Mail'?'mail':'telefono']];
 if(!isset($map[$action]))throw new RuntimeException('Operazione non consentita.');
 $panels=$map[$action];if(($action==='person_save'&&trim((string)($in['email']??''))!=='')||($action==='runts_align'&&isset($in['import_person'])))$panels[]=$action==='person_save'?'mail':'soggetti';
 return $panels;
}
function scheda_mutate(PDO $p,int $id,string $revision,string $action,array $in):void {
 scheda_needed_panels($action,$in);dc_active($p);
 $p->beginTransaction();
 try{
  if(!dc_find($p,'Associazioni',['ID'=>$id],true))throw new RuntimeException('Ente non trovato.');
  $entity=enti_detail($p,$id);if(!hash_equals(scheda_revision($entity),$revision))throw new RuntimeException('Scheda modificata da un’altra operazione. Ricaricare prima di salvare.');
  if($action==='entity_save')scheda_entity_save($p,$id,$in);
  elseif($action==='person_save')scheda_person_save($p,$id,$in);
  elseif($action==='mail_save'||$action==='phone_save')scheda_contact_save($p,$id,$action==='mail_save'?'Mail':'Telefono',$in);
  elseif($action==='mail_delete'||$action==='phone_delete'){
   $table=$action==='mail_delete'?'Mail':'Telefono';$r=scheda_contact($p,$entity,$table,(int)($in['contact_id']??0));
   $q=$p->prepare('DELETE FROM '.dc_q($table).' WHERE ID=?');$q->execute([$r['ID']]);
  }elseif($action==='person_delete'){
   $person=dc_find($p,'Soggetti',['ID'=>(int)($in['person_id']??0)]);
   if(!$person||(int)$person['ID_Associazione']!==$id)throw new RuntimeException('Persona non appartenente a questo ente.');
   foreach(['Mail','Telefono'] as $table){$q=$p->prepare('DELETE FROM '.dc_q($table).' WHERE TipoSoggetto=? AND IDSoggetto=?');$q->execute([$person['Tipo']==='LR'?'REFERENTE':'ALTRO',$person['ID']]);}
   $q=$p->prepare('DELETE FROM Soggetti WHERE ID=?');$q->execute([$person['ID']]);
  }elseif($action==='entity_delete'){
   $q=$p->prepare('SELECT COUNT(*) FROM AgoraPresenti WHERE IDAssociazione=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Ente presente in Agorà: eliminazione bloccata dal collegamento.');
   $q=$p->prepare('SELECT COUNT(*) FROM Runts WHERE IDAssociazione=?');$q->execute([$id]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Ente collegato a RUNTS: eliminazione bloccata.');
   foreach(['Mail','Telefono'] as $table){foreach($entity[$table] as $r){$q=$p->prepare('DELETE FROM '.dc_q($table).' WHERE ID=?');$q->execute([$r['ID']]);}}
   $q=$p->prepare('DELETE FROM Associazioni WHERE ID=?');$q->execute([$id]);
  }elseif($action==='runts_align'){
   $cf=(string)($in['runts_cf']??'');$r=enti_runts_record($p,$cf);if(!$r||trim((string)$r['Denominazione'])==='')throw new RuntimeException('Record RUNTS non disponibile.');
   if(!hash_equals(scheda_runts_revision($r),(string)($in['source_revision']??'')))throw new RuntimeException('Record RUNTS modificato: ricaricare il confronto prima di confermare.');
   $change=['SOGGETTO'=>$r['Denominazione'],'CODICEFISCALE'=>$r['CodiceFiscale'],'ID_TIPOLOGIA'=>$entity['ID_TIPOLOGIA']];
   foreach(['TAVOLO','DIRETTIVO_DELEGAZIONE'] as $flag)if((int)$entity[$flag])$change[$flag]='1';
   scheda_entity_save($p,$id,$change);
   if(isset($in['import_person'])){
    $name=enti_upper(trim(($r['Cognome']??'').' '.($r['Nome']??'')));$type=(string)($in['person_type']??'');if($name===''||!in_array($type,['LR','AS'],true))throw new RuntimeException('Scegliere esplicitamente il ruolo della persona RUNTS.');
    $exists=false;foreach($entity['Persone'] as $person)if($person['Tipo']===$type&&enti_upper(trim($person['Nome']))===$name)$exists=true;
    if(!$exists)scheda_person_save($p,$id,['name'=>$name,'type'=>$type]);
   }
  }elseif($action==='cesvot_import'){
   $table=(string)($in['table']??'');if(!in_array($table,['Mail','Telefono'],true))throw new RuntimeException('Tipo Cesvot non valido.');
   $ids=$in['source_ids']??[];if(!is_array($ids)||!$ids||count($ids)>200)throw new RuntimeException('Selezionare da 1 a 200 recapiti Cesvot.');
   $ids=array_values(array_unique(array_map('intval',$ids)));$available=scheda_cesvot($p,$entity)[$table];$selected=[];
   if(!hash_equals(hash('sha256',json_encode($available,JSON_THROW_ON_ERROR)),(string)($in['source_revision']??'')))throw new RuntimeException('Proposte Cesvot modificate: ricaricare prima di importare.');
   foreach($available as $r)if(in_array($r['ID'],$ids,true))$selected[]=$r;
   if(count($selected)!==count($ids))throw new RuntimeException('Recapiti Cesvot cambiati o non appartenenti a questo ente: ricaricare.');
   foreach($selected as $r)scheda_contact_save($p,$id,$table,['owner'=>'ente','value'=>$r['value']]);
  }
  $p->commit();
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
