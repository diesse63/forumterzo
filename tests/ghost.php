<?php
declare(strict_types=1);
if(getenv('POIMANAGER_TEST')!=='1')exit(1);
$base=dirname(__DIR__);
$file=$base.'/private/security.php';$security=require $file;
$security['development']=true;$security['ghost_enabled']=true;
file_put_contents($file,"<?php return ".var_export($security,true).";");
require $base.'/includes/auth.php';
user_login('ghost','123');
$u=current_user();
if(!$u||$u['Ruolo']!=='ghost'||!allowed($u,'utenti','scrittura'))throw new RuntimeException('Ghost sviluppo non funziona.');
if((int)poimanager_db()->query("SELECT COUNT(*) FROM Utenti WHERE Ruolo='ghost'")->fetchColumn()!==0)throw new RuntimeException('Ghost memorizzato nel DB.');
echo "OK: ghost di sviluppo fuori dalle tabelle utenti.\n";
