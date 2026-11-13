<?php
declare(strict_types=1);
require dirname(__DIR__).'/importazione_sqlite/tabelle.php';
function relation_check(bool $v,string $message):void{if(!$v)throw new RuntimeException($message);}
$s=new PDO('sqlite::memory:',null,null,[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$map=table_mapping();
$s->exec($map['tables']['Tipologia']['source_sql']);
$s->exec($map['tables']['Associazioni']['source_sql']);
$s->exec('CREATE TABLE Referenti (ID INTEGER PRIMARY KEY, IDAssociazione INTEGER REFERENCES Associazioni(ID))');
$s->exec('CREATE TABLE AltriSoggetti (ID INTEGER PRIMARY KEY)');
$s->exec('CREATE TABLE Agora_old (ID INTEGER PRIMARY KEY)');
$list=table_inventory($s,$map);
relation_check(isset($list['Soggetti'])&&!$list['Soggetti']['present']&&!$list['Soggetti']['enabled']&&$list['Soggetti']['count']===null,'Soggetti assente non visibile');
relation_check(isset($list['Referenti'])&&$list['Referenti']['present']&&!$list['Referenti']['enabled'],'Tabella originale non mappata assente');
relation_check(count($list['Soggetti']['outgoing'])===1&&str_contains($list['Soggetti']['outgoing'][0],'Associazioni.ID'),'FK prevista Soggetti mancante');
relation_check(count(array_filter($list['Associazioni']['incoming'],fn($r)=>str_contains($r,'Referenti.IDAssociazione')))===1,'FK SQLite non mappata non letta');
relation_check(count(array_filter($list['Tipologia']['incoming'],fn($r)=>str_contains($r,'Associazioni.ID_TIPOLOGIA')))===1,'Collegamento inverso assente o duplicato');
relation_check(str_contains($list['Associazioni']['outgoing'][0],'FK SQLite / FK MySQL prevista'),'Origini FK non distinte');
relation_check(count(array_filter($list['ListaMail']['outgoing'],fn($r)=>str_contains($r,'MailList.ID')&&str_contains($r,'vincolo logico')))===1,'Vincolo logico non mostrato');
relation_check(count(array_filter($list['Mail']['outgoing'],fn($r)=>str_contains($r,'polimorfico')))===1,'Riferimenti non dichiarati non evidenziati');
echo "OK: tabelle attese assenti, FK sorgenti e previste, origini, collegamenti inversi e logici.\n";
