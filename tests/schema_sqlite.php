<?php
declare(strict_types=1);
require dirname(__DIR__).'/importazione_sqlite/motore.php';
function schema_check(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$map=mapping();$path=tempnam(sys_get_temp_dir(),'schema_');
try{
 $db=new PDO('sqlite:'.$path);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 foreach($map['tables'] as $spec)$db->exec($spec['source_sql']);
 $db->exec('CREATE TABLE Utenti (ID INTEGER PRIMARY KEY, Password TEXT)');
 $db->exec("INSERT INTO Utenti VALUES (1,'legacy cifrato')");
 $db=null;
 $source=source($path,$map);$source=null;
 $db=new PDO('sqlite:'.$path);
 $db->exec('DROP TABLE Tipologia');
 $db->exec('CREATE TABLE TabellaNuova (ID INTEGER PRIMARY KEY)');
 $db=null;$message='';
 try{source($path,$map);}catch(RuntimeException $e){$message=$e->getMessage();}
 schema_check(str_contains($message,'Mancanti: Tipologia.'),'Tabella mancante non indicata');
 schema_check(str_contains($message,'Aggiuntive: TabellaNuova.'),'Tabella extra non indicata');
 schema_check(str_contains($message,'Attese:')&&str_contains($message,'Rilevate:'),'Elenchi diagnostici assenti');
 schema_check(!str_contains($message,'Aggiuntive: Utenti'),'Utenti non ignorata');
 $db=new PDO('sqlite:'.$path);schema_check((int)$db->query('SELECT COUNT(*) FROM Utenti')->fetchColumn()===1,'Utenti sorgenti modificati');
 $db=null;
 echo "OK: differenze tabelle esplicite, sorgente conservato e utenti legacy ignorati.\n";
}finally{@unlink($path);}
