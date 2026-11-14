# Cifratura dei dati gestionali — contratto 1.0.0

## Stato del rilascio
Il codice introduce la migrazione, ma NON cifra automaticamente il database Altervista al semplice git pull. L'operazione va avviata da un amministratore dopo backup e distribuzione completa. Nessuna email reale viene inviata durante le verifiche automatiche.

## Campi protetti
schema/cifratura_v1.json elenca precisamente tabelle e campi:
- Enti: denominazione, codice fiscale e COLONNA3.
- Persone: nomi.
- Email/telefoni: indirizzi e numeri.
- Liste: descrizione e note.
- RUNTS: codice fiscale, denominazione, cognome, nome e provincia.
- CESVOT: denominazioni, email e telefoni.
- Agorà: data, evento, ODG, verbale, riferimenti documenti e nomi rappresentanti.
- Tipologie e intestazioni: descrizioni.
- Temporanea e Report: valori testuali, compresa l'eventuale password legacy del Report, che non viene usata come hash di accesso.
- Nuovo modulo email: mittente/URL/nome/email di prova, oggetto/HTML/testo, copie di nome/email/ente dei destinatari e indirizzo effettivo degli invii.
- Utenti_legacy_v1, se presente: Nome, Email, Password. Non viene ripristinato il vecchio accesso.
Gli utenti attuali conservano la propria cifratura del profilo e gli hash password esistenti. Non vengono ricifrati con la nuova chiave.
ID, FK, ruoli, tipo soggetto, indicatori, stati, scadenze, contatori e hash dei codici restano tecnici e leggibili. ListaMail contiene solo collegamenti e tipo invio, quindi non riceve un payload riservato.

## Architettura
Chiave dati e chiave HMAC indipendenti vengono generate in private/dati_security.php, separato da database, Git, chiave utenti e credenziali del ponte.
La cifratura autenticata usa Sodium se disponibile oppure AES-256-GCM tramite OpenSSL, coerentemente con il motore registrato. Non cambia motore né chiave a ogni richiesta.
Ogni riga ha _Cifrato, con payload autenticato associato a tabella e ID: scambiarlo fra righe viene rilevato. I campi sensibili non indicizzati vengono svuotati o impostati a NULL; quelli coinvolti in UNIQUE vengono sostituiti da HMAC-SHA256 per tabella/campo. La presenza di NULL continua a rispettare i vincoli originali.
Runts e Temporanea ricevono _CryptoID per identificare in modo stabile anche righe prive di una PK affidabile; gli ID applicativi esistenti non cambiano.
Le uguaglianze/unicità restano verificabili tramite HMAC. Questo rivela le uguaglianze dei valori all'interno dello stesso campo, non il contenuto senza chiave. Non viene promesso occultamento di quantità, relazioni o frequenze.
Le ricerche parziali leggono e decifrano i record nell'applicazione DOPO il controllo dei permessi. Non restano copie testuali o indici di sottostringhe nel database. Questo costa più CPU rispetto a LIKE: con dataset RUNTS molto grandi servono verifiche prestazionali sul piano hosting, prima di uso intensivo.

## Procedura prima della migrazione
1. Fare un backup completo del database e dei file privati attuali e verificarne il ripristino su una copia.
2. Sospendere utilizzo del sito, importazioni e invii. Attendere che le richieste già avviate terminino.
3. git pull e distribuzione COMPLETA dei file PHP modificati, della cartella schema con .htaccess, assets/cifratura.js e mappa_v4.json. Non distribuire soltanto la pagina di migrazione lasciando vecchie pagine che scrivono valori in chiaro.
4. Conservare private/security.php e private/email_*.php originali. Non sostituirli con modelli, non cambiare chiavi dei profili o BRIDGE_SECRET.
5. Aprire Servizi > Cifratura dati (/cifratura_dati.php), come admin.
6. Confermare backup e sospensione attività, poi Prepara chiave e migrazione.
7. Fare immediatamente una copia protetta del nuovo private/dati_security.php: NON pubblicarlo, inviarlo in chat o committarlo.
8. Premere Completa automaticamente per lotti, oppure eseguire un lotto per volta. Ogni lotto converte fino a 100 righe in transazione, verifica la decifratura e conserva i progressi.
9. Durante la migrazione i gestionali restano bloccati. Fermare l'automatismo attende il termine del lotto attuale. Un errore o una disconnessione non annulla i lotti già completati.
10. Se la preparazione DDL è stata interrotta, usare Riprendi preparazione con la STESSA chiave. Non ripetere la migrazione utenti, non ricreare il database e non cancellare colonne o chiavi.
11. Proseguire fino a Stato pronto. La schermata mostra righe iniziali e convertite per tabella. Le tabelle opzionali assenti sono saltate.
12. Verificare le tabelle dalla pagina, poi provare anagrafica, ricerca persone, recapiti, bozza, Test connessione e conferma di un vecchio messaggio. Gli invii di prova richiedono sempre autorizzazione esplicita.
13. Fare un nuovo backup completo cifrato e delle chiavi, conservandoli in luoghi separati e protetti.

Il database contiene inizialmente dati in chiaro: il backup precedente, eventuali binlog/snapshot del provider e i file SQLite originali restano da proteggere separatamente. La cifratura delle righe correnti non cancella retroattivamente quelle copie.

## Importazione
La pagina usa mappa_v4.json (4.0.0) e il contratto di cifratura. Il file SQLite resta una fonte logica, non va cifrato dal client con queste chiavi.
Anteprima, collisioni, aggiornamento per chiave e confronto finale lavorano sui valori decifrati; gli INSERT/UPDATE passano dal livello cifratura. Le FK restano attive e gli errori mantengono il rollback del gruppo.
I payload _Cifrato/_CryptoID non sono colonne del sorgente e non vanno importati. I controlli schema riconoscono soltanto queste colonne tecniche aggiunte. Le vecchie procedure globali vengono bloccate se la cifratura è attiva.
Se una futura struttura aggiunge un campo riservato, aggiornare contratto cifratura, migrazione, mapping e procedure insieme. Nuove tabelle non possono scrivere valori riservati tramite SQL diretto.

## Backup, chiavi e rollback
Non cancellare o rigenerare dati_security.php. La perdita della chiave rende i campi riservati illeggibili. La copia del database da sola non basta a ripristinare il servizio; non conservare la sua unica chiave soltanto nello stesso hosting.
La chiave dei profili utenti e dei token email resta invariata, quindi gli account e i link già spediti continuano a funzionare.
Il ripristino richiede codice, database e file privati dello stesso punto temporale, in manutenzione. Non esiste un pulsante che rimetta i valori in chiaro nelle colonne. Non usare il vecchio codice dopo la migrazione.
La cifratura applicativa protegge una copia del database senza chiavi; chi controlla contemporaneamente applicazione, chiavi e accesso al server può ancora leggere i dati. Anche il provider hosting può avere accesso ai file: non è una promessa di cifratura end-to-end esclusiva del proprietario.

## Dati di prova
Per vedere valori direttamente in phpMyAdmin durante lo sviluppo usare un database SEPARATO con dati fittizi. Non disattivare la cifratura sul database reale per fare debug. Mai mettere copie decifrate o chiavi nei log.

## Test del rilascio
CI su MySQL effimero: lotti interrotti/ripresi, uguaglianza dei dati logici e conteggi prima/dopo, assenza di valori testuali nei campi originali protetti, HMAC e UNIQUE, autenticazione della riga, ricerche, scritture, invio simulato, token esistenti, import/update e idempotenza. Browser: anagrafica, ricerca, recapiti, inserimento e bozza su dati cifrati; chiave mancante blocca lettura e non restituisce segnaposti come se fossero dati validi. Nessuna email reale.
