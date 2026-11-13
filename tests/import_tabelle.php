<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/importazione_sqlite/tabelle.php';
function ti_check(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$p=db(poimanager_config());$map=table_mapping();$path=tempnam(sys_get_temp_dir(),'table_import_');
try{
 $sqlite=new PDO('sqlite:'.$path);$sqlite->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 foreach(['Tipologia','Associazioni'] as $t)$sqlite->exec($map['tables'][$t]['source_sql']);
 $sqlite->exec("INSERT INTO Tipologia (ID,Tipologia) VALUES (1,'origine')");
 $sqlite->exec("INSERT INTO Associazioni (ID,ID_TIPOLOGIA,TAVOLO,DIRETTIVO_DELEGAZIONE,SOGGETTO) VALUES (1,1,0,0,'ente')");
 $sqlite->exec('CREATE TABLE Referenti (ID INTEGER PRIMARY KEY, Nome TEXT)');
 $sqlite->exec('CREATE TABLE Temporanea (SOGGETTO TEXT, MAIL TEXT)');
 $s=table_source($path);$inventory=table_inventory($s,$map);
 ti_check(!$inventory['Referenti']['enabled']&&!$inventory['Temporanea']['enabled'],'Tabelle non mappate o senza chiave abilitate');
 $order=table_order(['Associazioni'],$map,$inventory);
 ti_check($order===['Tipologia','Associazioni'],'Ordine FK non corretto');
 $p->exec("INSERT INTO Tipologia (ID,Tipologia) VALUES (1,'vecchio'),(10,'preservato')");
 $users=$p->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll();
 $plan=table_plan($p,$s,$map,$order);
 ti_check($plan['Tipologia']['aggiornati']===1&&$plan['Associazioni']['nuovi']===1,'Conteggi errati');
 ti_check($p->query('SELECT Tipologia FROM Tipologia WHERE ID=1')->fetchColumn()==='vecchio','Anteprima scrive dati');
 table_import($p,$s,$map,$order,$plan,table_fingerprint($p,$map,$order));
 ti_check($p->query('SELECT Tipologia FROM Tipologia WHERE ID=1')->fetchColumn()==='origine','Update non eseguito');
 ti_check((int)$p->query('SELECT COUNT(*) FROM Associazioni')->fetchColumn()===1,'Insert non eseguito');
 ti_check((int)$p->query('SELECT COUNT(*) FROM Tipologia WHERE ID=10')->fetchColumn()===1,'Riga non presente nel sorgente cancellata');
 ti_check($users===$p->query('SELECT * FROM Utenti ORDER BY ID')->fetchAll(),'Nuovi utenti modificati');
 $plan=table_plan($p,$s,$map,$order);
 ti_check($plan['Tipologia']['invariati']===1&&$plan['Associazioni']['invariati']===1,'Reimport non idempotente');
 table_import($p,$s,$map,$order,$plan,table_fingerprint($p,$map,$order));
 $sqlite->exec("UPDATE Tipologia SET Tipologia='preservato' WHERE ID=1");
 $blocked=false;try{table_plan($p,$s,$map,$order);}catch(RuntimeException $e){$blocked=true;}
 ti_check($blocked,'Collisione su altro ID non bloccata');
 $sqlite->exec("UPDATE Tipologia SET Tipologia='modifica_da_annullare' WHERE ID=1");
 $sqlite->exec('UPDATE Associazioni SET ID_TIPOLOGIA=999 WHERE ID=1');
 $plan=table_plan($p,$s,$map,$order);$blocked=false;
 try{table_import($p,$s,$map,$order,$plan,table_fingerprint($p,$map,$order));}catch(PDOException $e){$blocked=true;}
 ti_check($blocked&&$p->query('SELECT Tipologia FROM Tipologia WHERE ID=1')->fetchColumn()==='origine','FK o rollback non rispettati');
 $sqlite->exec('UPDATE Associazioni SET ID_TIPOLOGIA=1 WHERE ID=1');
 $plan=table_plan($p,$s,$map,$order);$fingerprint=table_fingerprint($p,$map,$order);
 $p->exec("UPDATE Tipologia SET Tipologia='cambiato_dopo_anteprima' WHERE ID=1");
 $blocked=false;try{table_import($p,$s,$map,$order,$plan,$fingerprint);}catch(RuntimeException $e){$blocked=true;}
 ti_check($blocked,'Destinazione cambiata non rilevata');
 $bad=false;try{table_order(['Soggetti'],$map,$inventory);}catch(RuntimeException $e){$bad=true;}ti_check($bad,'Tabella assente ammessa');
 $bad=false;try{table_identity(['CodiceFiscale'=>null],$map['tables']['Runts']);}catch(RuntimeException $e){$bad=true;}ti_check($bad,'Chiave nulla ambigua ammessa');
 $s=null;$sqlite=null;echo "OK: import per tabella, dipendenze, insert/update/idempotenza, conservazione righe, collisioni, FK/rollback e concorrenza.\n";
}finally{@unlink($path);}
