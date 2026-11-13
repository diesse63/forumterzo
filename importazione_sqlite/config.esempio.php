<?php
// Copiare in config.php. Non pubblicare le proprie credenziali.
return [
 'dsn' => 'mysql:host=localhost;dbname=my_poimanager;charset=utf8mb4',
 'user' => 'poimanager',
 'password' => 'INSERIRE_PASSWORD_DATABASE',
 // Scegliere una password lunga e unica per questa pagina amministrativa.
 'admin_password' => 'CAMBIARE_PASSWORD_IMPORTAZIONE',
 // Directory NON raggiungibile via web, scrivibile da PHP. Esempio da adattare.
 'private_dir' => '/PERCORSO_NON_PUBBLICO/importazioni_private',
 'max_bytes' => 20 * 1024 * 1024,
];
