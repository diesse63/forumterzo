<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
require dirname(__DIR__).'/includes/auth.php';
require dirname(__DIR__).'/importazione_sqlite/check_schema.php';
function check_schema_test(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$p=db(poimanager_config());$map=table_mapping();$spec=$map['tables']['Associazioni'];
$p->exec('ALTER TABLE Associazioni DROP CHECK ck_Associazioni_1');
$p->exec('ALTER TABLE Associazioni ADD CONSTRAINT nome_diverso CHECK (TAVOLO IN (0,1)) ENFORCED');
$subset=$map;$subset['tables']=['Associazioni'=>$spec];inspectTarget($p,$subset,false);
$status=schema_check_status($p,'Associazioni',$spec);
check_schema_test($status['ck_Associazioni_1']['status']==='presente','CHECK equivalente con nome diverso rifiutato');
check_schema_test(schema_add_missing_checks($p,'Associazioni',$spec)===[],'CHECK equivalente duplicato');
$p->exec('ALTER TABLE Associazioni DROP CHECK nome_diverso');
$before=$p->query('SELECT * FROM Associazioni ORDER BY ID')->fetchAll();
$done=schema_add_missing_checks($p,'Associazioni',$spec);
check_schema_test($done===['ck_Associazioni_1'],'CHECK mancante non aggiunto');
check_schema_test($before===$p->query('SELECT * FROM Associazioni ORDER BY ID')->fetchAll(),'Riparazione modifica righe');
check_schema_test(schema_add_missing_checks($p,'Associazioni',$spec)===[],'Riparazione non idempotente');
$p->exec('ALTER TABLE Associazioni DROP CHECK ck_Associazioni_1');
$p->exec('UPDATE Associazioni SET TAVOLO=2 WHERE ID=1');
$blocked=false;try{schema_add_missing_checks($p,'Associazioni',$spec);}catch(RuntimeException $e){$blocked=true;}
check_schema_test($blocked,'Dati non validi accettati');
$p->exec('UPDATE Associazioni SET TAVOLO=0 WHERE ID=1');schema_add_missing_checks($p,'Associazioni',$spec);
inspectTarget($p,$subset,false);
echo "OK: equivalenza CHECK con nomi diversi, aggiunta mancante, idempotenza e blocco dati incompatibili.\n";
