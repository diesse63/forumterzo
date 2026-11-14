<?php
declare(strict_types=1);
require_once __DIR__.'/dati.php';
function enti_upper(string $s): string {return function_exists('mb_strtoupper')?mb_strtoupper($s,'UTF-8'):strtoupper($s);}
function enti_like(string $s): string{return '%'.str_replace(['=','%','_'],['==','=%','=_'],$s).'%';}
function enti_tipologie(PDO $p): array{$rows=dc_rows($p,'Tipologia');usort($rows,fn($a,$b)=>strnatcasecmp($a['Tipologia'],$b['Tipologia'])?:((int)$a['ID']<=>(int)$b['ID']));return $rows;}
function enti_is_ets(string $label): bool{return enti_upper(trim($label))==='ENTI DEL TERZO SETTORE';}
function enti_list(PDO $p,string $query,int $type,int $page): array {
 $query=trim($query);if(strlen($query)>200)throw new RuntimeException('Ricerca troppo lunga.');
 if(dc_active($p))return enti_encrypted_list($p,$query,$type,$page);
 $where=[];$args=[];
 if($query!==''){
  $like=enti_like($query);
  $where[]="(CONVERT(a.SOGGETTO USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=' OR a.CODICEFISCALE COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=' OR EXISTS (SELECT 1 FROM Soggetti s WHERE s.ID_Associazione=a.ID AND CONVERT(s.Nome USING utf8mb4) COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '='))";
  $args=[$like,$like,$like];
 }
 if($type>0){$where[]='a.ID_TIPOLOGIA=?';$args[]=$type;}
 $condition=$where?' WHERE '.implode(' AND ',$where):'';
 $q=$p->prepare('SELECT COUNT(*) FROM Associazioni a'.$condition);$q->execute($args);$total=(int)$q->fetchColumn();
 $pages=max(1,(int)ceil($total/50));$page=max(1,min($page,$pages));$offset=($page-1)*50;
 $q=$p->prepare('SELECT a.*,t.Tipologia AS Tipologia_Nome FROM Associazioni a JOIN Tipologia t ON t.ID=a.ID_TIPOLOGIA'.$condition.' ORDER BY CONVERT(a.SOGGETTO USING utf8mb4) COLLATE utf8mb4_unicode_ci,a.ID LIMIT 50 OFFSET '.$offset);
 $q->execute($args);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as &$row)$row['Persone']=[];unset($row);
 if($rows){
  $ids=array_column($rows,'ID');$q=$p->prepare('SELECT ID,ID_Associazione,Nome,Tipo FROM Soggetti WHERE ID_Associazione IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY Tipo DESC,Nome,ID');
  $q->execute($ids);$people=[];
  foreach($q as $person)$people[$person['ID_Associazione']][]=$person;
  foreach($rows as &$row)$row['Persone']=$people[$row['ID']]??[];unset($row);
 }
 return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
}
function enti_runts_search(PDO $p,string $term): array {
 $term=trim($term);if(strlen($term)<3)return [];
 if(strlen($term)>200)throw new RuntimeException('Ricerca RUNTS troppo lunga.');
 if(dc_active($p)){
  $rows=[];foreach(dc_iter($p,'Runts') as $r)if($r['CodiceFiscale']!==null&&(dc_contains($r['Denominazione']??'',$term)||dc_contains($r['Cognome']??'',$term)||dc_contains($r['Nome']??'',$term)))$rows[]=$r;
  usort($rows,fn($a,$b)=>strnatcasecmp($a['Denominazione']??'',$b['Denominazione']??''));return array_slice($rows,0,20);
 }
 $like=enti_like($term);
 $q=$p->prepare("SELECT CodiceFiscale,Denominazione,Cognome,Nome,Provincia FROM Runts WHERE CodiceFiscale IS NOT NULL AND (Denominazione COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=' OR Cognome COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=' OR Nome COLLATE utf8mb4_unicode_ci LIKE ? ESCAPE '=') ORDER BY Denominazione,CodiceFiscale LIMIT 20");
 $q->execute([$like,$like,$like]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function enti_runts_record(PDO $p,string $cf): ?array {return dc_find($p,'Runts',['CodiceFiscale'=>$cf]);}
function enti_create(PDO $p,array $input): int {
 $name=enti_upper(trim((string)($input['SOGGETTO']??'')));
 $cf=enti_upper(preg_replace('/\s+/u','',(string)($input['CODICEFISCALE']??'')));
 $type=filter_var($input['ID_TIPOLOGIA']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if($name===''||strlen($name)>768)throw new RuntimeException('Denominazione obbligatoria, massimo 768 byte.');
 if(strlen($cf)>16||($cf!==''&&!preg_match('/^[A-Z0-9]+$/D',$cf)))throw new RuntimeException('Codice fiscale: massimo 16 caratteri alfanumerici.');
 if(!$type)throw new RuntimeException('Selezionare una tipologia.');
 $typeRow=dc_find($p,'Tipologia',['ID'=>$type]);$label=$typeRow['Tipologia']??false;
 if($label===false)throw new RuntimeException('Tipologia non valida.');
 $ets=enti_is_ets((string)$label);$table=$ets&&isset($input['TAVOLO'])?1:0;$board=$ets&&isset($input['DIRETTIVO_DELEGAZIONE'])?1:0;
 $p->beginTransaction();
 try{
  $person=null;$personType=null;
  if(isset($input['import_person'])){
   $record=enti_runts_record($p,(string)($input['runts_cf']??''));
   $personType=(string)($input['person_type']??'');
   if(!$record||trim((string)$record['Cognome'])===''||trim((string)$record['Nome'])===''||!in_array($personType,['LR','AS'],true))throw new RuntimeException('Per importare la persona selezionare un risultato RUNTS valido e il suo ruolo.');
   $person=enti_upper(trim($record['Cognome'].' '.$record['Nome']));
   if(strlen($person)>768)throw new RuntimeException('Nome della persona troppo lungo.');
  }
  $id=(int)dc_insert($p,'Associazioni',['ID_TIPOLOGIA'=>$type,'SOGGETTO'=>$name,'CODICEFISCALE'=>$cf!==''?$cf:null,'TAVOLO'=>$table,'DIRETTIVO_DELEGAZIONE'=>$board]);
  if($person!==null)dc_insert($p,'Soggetti',['ID_Associazione'=>$id,'Nome'=>$person,'Tipo'=>$personType]);
  $p->commit();return $id;
 }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}
function enti_detail(PDO $p,int $id): ?array {
 $entity=dc_find($p,'Associazioni',['ID'=>$id]);if(!$entity)return null;
 $type=dc_find($p,'Tipologia',['ID'=>$entity['ID_TIPOLOGIA']]);$entity['Tipologia_Nome']=$type['Tipologia']??'';
 $entity['Persone']=dc_rows($p,'Soggetti','WHERE ID_Associazione=?',[$id]);
 usort($entity['Persone'],fn($a,$b)=>strcmp($b['Tipo'],$a['Tipo'])?:strnatcasecmp($a['Nome'],$b['Nome']));
 foreach(['Mail','Telefono'] as $table){
  $q=$p->prepare('SELECT * FROM '.$table." WHERE (TipoSoggetto='ASSOCIAZIONE' AND IDSoggetto=?) OR (TipoSoggetto='REFERENTE' AND IDSoggetto IN (SELECT ID FROM Soggetti WHERE ID_Associazione=? AND Tipo='LR')) OR (TipoSoggetto='ALTRO' AND IDSoggetto IN (SELECT ID FROM Soggetti WHERE ID_Associazione=? AND Tipo='AS')) ORDER BY TipoSoggetto,Predefinito DESC,ID");
  $q->execute([$id,$id,$id]);$entity[$table]=dc_decode_rows($p,$table,$q->fetchAll(PDO::FETCH_ASSOC));
 }
 return $entity;
}
function enti_encrypted_list(PDO $p,string $query,int $type,int $page):array {
 $types=[];foreach(enti_tipologie($p) as $t)$types[$t['ID']]=$t['Tipologia'];
 $people=[];foreach(dc_iter($p,'Soggetti') as $r)$people[$r['ID_Associazione']][]=$r;
 $rows=[];
 foreach(dc_iter($p,'Associazioni') as $r){
  if($type>0&&(int)$r['ID_TIPOLOGIA']!==$type)continue;
  $r['Persone']=$people[$r['ID']]??[];$r['Tipologia_Nome']=$types[$r['ID_TIPOLOGIA']]??'';
  if($query!==''){
   $match=dc_contains($r['SOGGETTO'],$query)||dc_contains($r['CODICEFISCALE']??'',$query);
   foreach($r['Persone'] as $person)if(dc_contains($person['Nome'],$query))$match=true;
   if(!$match)continue;
  }
  usort($r['Persone'],fn($a,$b)=>strcmp($b['Tipo'],$a['Tipo'])?:strnatcasecmp($a['Nome'],$b['Nome']));$rows[]=$r;
 }
 usort($rows,fn($a,$b)=>strnatcasecmp($a['SOGGETTO'],$b['SOGGETTO'])?:((int)$a['ID']<=>(int)$b['ID']));
 $total=count($rows);$pages=max(1,(int)ceil($total/50));$page=max(1,min($page,$pages));
 return ['rows'=>array_slice($rows,($page-1)*50,50),'total'=>$total,'page'=>$page,'pages'=>$pages];
}
function enti_query(array $params,array $extra=[]): string {
 return '/anagrafica.php?'.http_build_query(array_merge(['q'=>$params['q']??'','tipo'=>$params['tipo']??0,'pagina'=>$params['pagina']??1],$extra));
}
