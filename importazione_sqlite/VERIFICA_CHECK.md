# CHECK MySQL: verifica e aggiunte mirate

L'errore CHECK assenti o diversi riguarda i vincoli della destinazione MySQL, non automaticamente il contenuto del file SQLite.
L'importazione ora confronta le espressioni normalizzate, mantenendo il numero dei vincoli, senza richiedere lo stesso nome per regole equivalenti. CHECK aggiuntivi differenti o non applicati restano bloccanti.

Dopo git pull sincronizzare motore.php, check_schema.php e index.php nella cartella importazione_sqlite e verifica_check.php nella radice. Aprire il collegamento Verifica CHECK dalla pagina d'importazione, oppure /verifica_check.php?tabella=Associazioni.
La pagina è riservata ad admin/ghost e richiede CSRF per le modifiche. Mostra regole attese, regole presenti e stato ENFORCED. Le differenze sono lette dal MySQL reale.
Dopo backup e sospensione delle scritture si possono aggiungere soltanto i CHECK mancanti; prima di ogni ALTER vengono controllate tutte le righe esistenti. Regole diverse con lo stesso nome non vengono sostituite. Nessun dato corretto o cancellato automaticamente.
Le operazioni DDL MySQL sono confermate individualmente: in caso di errore rileggere lo stato, non presumere un rollback degli ALTER già eseguiti. La pagina può essere rieseguita senza duplicare i CHECK equivalenti. Richiede permesso ALTER.
Non eseguire indiscriminatamente il vecchio script aggiungi_check_mancanti_mysql8.sql: potrebbe tentare aggiunte già presenti.
