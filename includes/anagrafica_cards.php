<?php
declare(strict_types=1);
require_once __DIR__.'/anagrafica_scheda.php';
/** Carica in gruppo i recapiti della sola pagina e legge il RUNTS una volta. */
function enti_cards_data(PDO $p,array $data):array {
 if(!$data['rows'])return $data;
 $owners=[];$ids=['ASSOCIAZIONE'=>[],'REFERENTE'=>[],'ALTRO'=>[]];$byId=[];$wantedCf=[];$wantedName=[];
 foreach($data['rows'] as $i=>$row){
  $id=(int)$row['ID'];$byId[$id]=$i;$ids['ASSOCIAZIONE'][]=$id;$owners['ASSOCIAZIONE:'.$id]=$id;
  foreach($row['Persone'] as $person){$kind=$person['Tipo']==='LR'?'REFERENTE':'ALTRO';$ids[$kind][]=(int)$person['ID'];$owners[$kind.':'.$person['ID']]=$id;}
  $data['rows'][$i]['Mail']=[];$data['rows'][$i]['Telefono']=[];
  $cf=enti_upper(preg_replace('/\s+/u','',(string)($row['CODICEFISCALE']??''))??'');
  if($cf!=='')$wantedCf[$cf]=true;else $wantedName[enti_upper(trim($row['SOGGETTO']))]=true;
 }
 $where=[];$args=[];
 foreach($ids as $kind=>$values)if($values){$where[]='(TipoSoggetto=? AND IDSoggetto IN ('.implode(',',array_fill(0,count($values),'?')).'))';$args[]=$kind;array_push($args,...$values);}
 foreach(['Mail','Telefono'] as $table)foreach(dc_rows($p,$table,'WHERE '.implode(' OR ',$where).' ORDER BY Predefinito DESC,ID',$args) as $contact){
  $entityId=$owners[$contact['TipoSoggetto'].':'.$contact['IDSoggetto']]??null;
  if($entityId!==null)$data['rows'][$byId[$entityId]][$table][]=$contact;
 }
 $cfRecords=[];$nameRecords=[];
 foreach(dc_iter($p,'Runts') as $r){
  $cf=enti_upper(preg_replace('/\s+/u','',(string)($r['CodiceFiscale']??''))??'');if($cf==='')continue;
  $name=enti_upper(trim((string)($r['Denominazione']??'')));
  if(isset($wantedCf[$cf]))$cfRecords[$cf][]=$r;if(isset($wantedName[$name]))$nameRecords[$name][]=$r;
 }
 foreach($data['rows'] as &$row){$cf=enti_upper(preg_replace('/\s+/u','',(string)($row['CODICEFISCALE']??''))??'');$row['Runts']=scheda_runts_matches($row,$cf!==''?($cfRecords[$cf]??[]):($nameRecords[enti_upper(trim($row['SOGGETTO']))]??[]));}unset($row);
 return $data;
}
