<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/includes/anagrafica.php';
function ae_check(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$p=poimanager_db();
$p->exec("INSERT INTO Tipologia (ID,Tipologia) VALUES (100,'ENTI DEL TERZO SETTORE'),(101,'ISTITUZIONI STATALI')");
$ids=[];
for($i=1;$i<=51;$i++)$ids[]=enti_create($p,['SOGGETTO'=>'Clone fixture '.str_pad((string)$i,3,'0',STR_PAD_LEFT),'ID_TIPOLOGIA'=>100]);
$first=$ids[0];
$list=enti_list($p,'clone fixture',100,1);ae_check($list['total']===51&&count($list['rows'])===50&&$list['pages']===2,'Paginazione');
$list=enti_list($p,'clone fixture',100,99);ae_check($list['page']===2&&count($list['rows'])===1,'Limite pagina');
$p->prepare('INSERT INTO Soggetti (ID_Associazione,Nome,Tipo) VALUES (?,?,?)')->execute([$first,'PERSONA RICERCABILE','LR']);$person=(int)$p->lastInsertId();
$list=enti_list($p,'ricercabile',100,1);ae_check($list['total']===1&&count($list['rows'][0]['Persone'])===1,'Ricerca persone e aggregazione');
ae_check(enti_list($p,'%',100,1)['total']===0,'LIKE non tratta wildcard come testo');
$other=enti_create($p,['SOGGETTO'=>'Clone altro','ID_TIPOLOGIA'=>101,'TAVOLO'=>'1','DIRETTIVO_DELEGAZIONE'=>'1']);
$detail=enti_detail($p,$other);ae_check((int)$detail['TAVOLO']===0&&(int)$detail['DIRETTIVO_DELEGAZIONE']===0,'Incarichi non ETS');
$p->prepare('INSERT INTO Runts (CodiceFiscale,Denominazione,Cognome,Nome,Provincia) VALUES (?,?,?,?,?)')->execute(['CLONERUNTS001','Clone RUNTS ente','ROSSI','ANNA','XX']);
ae_check(count(enti_runts_search($p,'rossi'))===1&&enti_runts_search($p,'ro')===[],'Ricerca RUNTS');
$fromRunts=enti_create($p,['SOGGETTO'=>'Clone da RUNTS','ID_TIPOLOGIA'=>100,'runts_cf'=>'CLONERUNTS001','import_person'=>'1','person_type'=>'AS']);
ae_check(enti_detail($p,$fromRunts)['Persone'][0]['Tipo']==='AS','Ruolo persona non rispettato');
$before=(int)$p->query('SELECT COUNT(*) FROM Associazioni')->fetchColumn();
$blocked=false;try{enti_create($p,['SOGGETTO'=>'Clone fixture 001','ID_TIPOLOGIA'=>100]);}catch(PDOException $e){$blocked=true;}
ae_check($blocked&&(int)$p->query('SELECT COUNT(*) FROM Associazioni')->fetchColumn()===$before,'Duplicato inserito');
$p->exec("CREATE TRIGGER clone_test_failure BEFORE INSERT ON Soggetti FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'");
try{
 $blocked=false;try{enti_create($p,['SOGGETTO'=>'Clone rollback','ID_TIPOLOGIA'=>100,'runts_cf'=>'CLONERUNTS001','import_person'=>'1','person_type'=>'LR']);}catch(PDOException $e){$blocked=true;}
 ae_check($blocked&&(int)$p->query('SELECT COUNT(*) FROM Associazioni')->fetchColumn()===$before,'Salvataggio non atomico');
}finally{$p->exec('DROP TRIGGER clone_test_failure');}
$p->prepare("INSERT INTO Mail (TipoSoggetto,IDSoggetto,Indirizzo,PEC,Predefinito) VALUES ('ASSOCIAZIONE',?,'ente@example.invalid',0,1)")->execute([$first]);
$p->prepare("INSERT INTO Telefono (TipoSoggetto,IDSoggetto,Telefono,Predefinito) VALUES ('REFERENTE',?,'000111',1)")->execute([$person]);
$detail=enti_detail($p,$first);ae_check(count($detail['Mail'])===1&&count($detail['Telefono'])===1,'Recapiti ente e persona');
$missing=false;try{enti_create($p,['SOGGETTO'=>'Clone invalido','ID_TIPOLOGIA'=>99999]);}catch(RuntimeException $e){$missing=true;}ae_check($missing,'Tipologia inesistente accettata');
echo "OK: filtri, ricerca persone, paginazione, RUNTS, inserimento atomico, duplicati, incarichi e recapiti.\n";
