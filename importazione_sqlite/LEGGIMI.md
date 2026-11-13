# Importazione SQLite → MySQL AlterVista — versione 1.0.0

Questa procedura legge direttamente un file SQLite aggiornato: i dati non sono inclusi in nessun file SQL del pacchetto. Il file di esempio dell'utente contiene 16 tabelle e 2.730 record; i conteggi vengono ricalcolati ad ogni caricamento e non sono fissi.

## Installazione

1. Verificare in phpMyAdmin `SELECT VERSION();`: questa versione richiede MySQL 8.0.16 o successivo (non MariaDB). PHP 8.0 o successivo con PDO MySQL e PDO SQLite. La pagina segnala eventuali driver mancanti. Se PDO SQLite non è disponibile nel pannello AlterVista, occorre abilitarlo o adattare la procedura per eseguirla da PC; questo pacchetto non include una modalità alternativa.
2. Le 16 tabelle sono già presenti e vuote. Se l'importazione precedente si è fermata al primo trigger, i CHECK non sono stati creati: eseguire una sola volta `aggiungi_check_mancanti_mysql8.sql` in phpMyAdmin. Non usarlo se i CHECK sono già presenti. Lo script contiene soltanto ALTER, nessun dato e nessun trigger.
3. `struttura_solo_mysql8.sql` serve esclusivamente per installazioni nuove con le tabelle assenti. Non eseguirlo sulle tabelle già create. Anche questo file non contiene dati.
4. Caricare la cartella del pacchetto sul sito, ad esempio `/importazione_sqlite/`. Mantenerla separata dall'applicazione originale. Conservare `.htaccess`, che impedisce lo scaricamento dei file interni. Verificare che un URL verso `mappa_v1.json` risponda 403. Se il server rifiuta `Options -Indexes`, rimuovere solo quella riga conservando FilesMatch.
5. Caricare anche le cartelle `includes` e `private` nella radice del sito. Copiare `private/config.esempio.php` in `private/config.php`. Host `localhost`, utente `poimanager`, database `my_poimanager`; password DB vuota come indicato, da compilare solo se richiesta dal servizio. Impostare una password amministrativa unica di almeno 16 caratteri. Non usare quella di un account applicativo. Eliminare eventuali vecchi `importazione_sqlite/config.php` dopo la migrazione. Leggere `CONNESSIONE_DB.md` per protezione e sincronizzazione WinSCP.
6. Predisporre una directory scrivibile da PHP fuori dalla radice pubblica del sito e indicarne il percorso assoluto in `private_dir`. Non basta creare una cartella dentro lo spazio web. Se il piano non permette una directory non pubblica, fermarsi: serve adattare il deposito al piano, non disabilitare il controllo. Caricamenti SQLite e registro contengono informazioni amministrative e non vanno resi scaricabili.
7. Aprire tramite HTTPS `https://poimanager.altervista.org/importazione_sqlite/index.php` e accedere con la password amministrativa impostata. È un accesso separato: questo pacchetto non modifica né integra il login dell'applicazione, il cui codice attuale non è stato fornito.
8. Mettere l'applicazione di destinazione in manutenzione durante l'importazione, per evitare scritture concorrenti. Il lock della procedura coordina le importazioni, non le altre pagine del sito.

## Ogni importazione

1. Chiudere l'applicazione originale e creare una copia coerente del database SQLite (backup SQLite oppure applicazione chiusa e dati WAL consolidati). Non copiare solo il `.db` di un database in uso con modifiche ancora nel file `-wal`.
2. Selezionare il file `.db` e premere **1. Analizza file**.
3. L'analisi non scrive dati: controlla integrità SQLite, FK SQLite, struttura del sorgente, struttura MySQL, engine InnoDB, colonne, tipi, NULL, default, collation, vincoli univoci, chiavi esterne e CHECK applicati; controlla conversioni e lunghezze e che le tabelle siano vuote.
4. Controllare i conteggi e premere **2. Importa i dati verificati** entro 30 minuti. La procedura ricontrolla file e mappatura e ripete le verifiche.
5. L'importazione usa INSERT preparati, ID originali, ordine delle dipendenze e FK sempre attive. Gli hash delle righe rilette da MySQL sono confrontati con quelli SQLite, oltre ai conteggi, prima del COMMIT. Un errore comporta ROLLBACK di tutte le righe di questa importazione.
6. Prima della transazione dati vengono adeguati i contatori AUTO_INCREMENT ai massimi storici SQLite, per non riutilizzare ID cancellati. È richiesto il permesso ALTER. Queste sole modifiche ai contatori non sono annullate dal rollback dei dati. Nessuna tabella viene creata, eliminata o svuotata dal motore PHP.
7. In caso di successo viene scritto `importazioni.jsonl` nella directory privata: data UTC, versione mappa, SHA256 del file, conteggi ed esito. Nessun recapito o password viene registrato. Il file caricato è cancellato; anche Esci elimina il caricamento pendente. Se si abbandona il browser o il processo termina bruscamente, possono rimanere file `sqlite_*.db`: eliminarli manualmente dalla directory privata solo dopo essersi assicurati che non ci siano importazioni attive.
8. Verificare i dati dall'applicazione. Conservare un backup separato del file sorgente. Disabilitare/rimuovere la cartella pubblica di importazione quando non serve.

### Tabelle già popolate

La procedura si blocca sempre. Non fa TRUNCATE, DELETE, fusione, REPLACE o aggiornamento per ID. Per aggiornamenti dopo un primo caricamento serve una modalità di sostituzione/riconciliazione progettata separatamente con backup e regole per i dati già modificati nell'applicazione nuova. Per il caricamento finale pre-produzione, predisporre una destinazione nuovamente vuota con una procedura controllata: questo pacchetto non cancella dati.

## Aggiornamento obbligatorio ad ogni modifica strutturale

`mappa_v1.json` è il contratto versionato tra sorgente SQLite e destinazione MySQL. Non si aggiorna da solo: una nuova struttura richiede una revisione coordinata della procedura. Il controllo evita di importare silenziosamente con una mappatura vecchia. Indici non univoci aggiuntivi o loro nomi, contatori e nomi delle FK non fanno parte del contratto: non cambiano la trasformazione dei dati.

Ogni rilascio che modifica lo schema MySQL deve includere:

1. Migrazione di struttura separata dall'importazione dati.
2. Nuova versione della mappa: colonne destinazione, tipi, nullabilità, default, collation, auto_increment, vincoli UNIQUE/FK/CHECK e regole di corrispondenza con il vecchio SQLite. Il sorgente può restare invariato mentre la destinazione evolve.
3. Aggiornamento del motore per eventuali trasformazioni nuove e aggiornamento dei controlli. Oggi sono supportate `identity` e `integer`; ogni altra trasformazione è rifiutata. Una nuova colonna con valore calcolato, una rinomina, una tabella divisa o una fusione richiedono codice e test dedicati, non una copia automatica dei nomi.
4. Aggiornamento della versione del file mappa caricato da `mapping()` se viene rinominato. La UI mostra la versione e l'importazione confronta l'hash della mappa tra analisi e conferma.
5. Prova su database MySQL separato con copia SQLite aggiornata: importazione valida, NULL, caratteri UTF-8, unicità, FK, valori CHECK, rollback a metà importazione, blocco di una mappa obsoleta e confronto delle righe.
6. Aggiornamento delle istruzioni e distribuzione insieme di schema, mappa e motore.

Non rimuovere un controllo di compatibilità solo per far proseguire un'importazione. Se cambia il sorgente SQLite, anche il suo contratto `source_sql` va revisionato.

## Adattamenti della conversione iniziale

Campi testuali indicizzati VARBINARY(768) per preservare il confronto byte per byte SQLite; altri testi LONGTEXT utf8mb4, salvo TipoInvio VARCHAR(255) con default CCN. Runts.CodiceFiscale resta UNIQUE nullable anziché PRIMARY KEY per preservare la semantica SQLite. TAVOLO è convertito da numerico SQLite 0/1 a BIGINT. Nessuna FK aggiunta ai riferimenti solo impliciti o polimorfici. Le password sono copiate senza modificarne il contenuto: la compatibilità del successivo login va verificata nell'applicazione.

## Validazione del pacchetto

Controllata la corrispondenza della mappa con lo schema e le righe del file fornito. Nessun server AlterVista è stato contattato né modificato. Il collaudo reale PHP/MySQL dipende dalla disponibilità dei driver, dei permessi e della directory privata sull'hosting. Consultare VALIDAZIONE.txt per i controlli effettivamente eseguiti.
