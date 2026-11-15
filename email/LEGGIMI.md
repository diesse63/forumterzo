# Invio email — destinatari dell’anagrafica

La procedura usa soltanto gli indirizzi selezionati nella rubrica. Non esistono più il selettore TEST/Produzione, un destinatario di prova separato o un pulsante di invio fuori rubrica. Per provare la spedizione creare un soggetto/ente di prova e selezionare soltanto il suo indirizzo.

## Aggiornamento del codice esistente

1. Fare il normale backup di database e cartella private, poi git pull --ff-only sul PC.
2. Caricare con WinSCP invio_email.php, includes/email.php e includes/email_config_form.php. Mantenere le altre parti dell'applicazione aggiornate. Non caricare tests, .github o credenziali pubbliche. Tenere disattivata l'eliminazione automatica di WinSCP.
3. In Configurazione premere Salva nuova versione configurazione, quindi Verifica connessione — nessuna email. La nuova versione è automaticamente di produzione; non occorre selezionare o confermare una modalità.
4. Se manca il file privato della credenziale, la pagina rimane apribile: copiare BRIDGE_SECRET dalle proprietà del progetto Google corretto nel campo Credenziale del ponte, quindi salvare. Non è la password Gmail e non va comunicata in chat. Non modificare le chiavi utenti o dati.
5. Le vecchie campagne preparate nella modalità rimossa non vengono convertite: restano nello storico e non possono spedire nuovi messaggi. Creare una nuova bozza con i destinatari scelti. Gli esiti incerti precedenti si possono verificare senza reinviare e i link già ricevuti restano validi.

Non servono migrazioni SQL, una nuova chiave di cifratura o modifiche al deployment Google per questo aggiornamento. Modalita ed EmailTest restano soltanto per compatibilità con le configurazioni e i dati storici già cifrati; ogni nuova configurazione salva produzione e nessun indirizzo alternativo. Il codice non può reindirizzare l'invio a quel vecchio campo. Le strutture e la mappatura SQLite restano invariate.

## Collegamento iniziale del ponte Google

Per un'installazione nuova aprire email_installazione.php come amministratore: la procedura crea le tabelle email dedicate senza modificare le anagrafiche o gli utenti. Non ripetere la migrazione utenti.

1. Creare un progetto Apps Script separato con l'account Gmail mittente e inserire email/Bridge.gs in Codice.gs.
2. In Invio email > Configurazione generare una credenziale di 64 caratteri esadecimali e copiarla nella proprietà script BRIDGE_SECRET dello stesso progetto.
3. Eseguire autorizzaPonte nell'editor Google e concedere le autorizzazioni. La funzione legge account e quota e non invia messaggi.
4. Pubblicare come App web, Esegui come Me, accesso Chiunque. Copiare l'URL https://script.google.com/macros/s/.../exec; non usare /dev.
5. Su Altervista inserire URL, indirizzo del medesimo account Gmail, nome visualizzato, dimensione lotto da 1 a 20 e la stessa credenziale del ponte.
6. Salvare e usare Verifica connessione — nessuna email. L'impronta mostrata deve corrispondere a quella stampata da autorizzaPonte, senza pubblicare il segreto.

Il ponte richiede richieste POST firmate HMAC, controlla mittente, timestamp e nonce e restituisce risposte firmate. Il trasporto verifica TLS e accetta i redirect di risposta solo sul dominio Google previsto, senza inoltrare la richiesta firmata al redirect. La credenziale sta in private/email_<riferimento>.php, non nel database. Fare backup di questi file e conservarli per le campagne pendenti. La cartella private deve rimanere protetta da .htaccess.

Cambiare solo nome o lotto non richiede rigenerare la credenziale: lasciare vuoto il campo per conservare quella attuale. Per cambiare account/progetto salvare una nuova configurazione e mantenere i vecchi progetti e segreti finché servono agli invii già preparati. Le campagne mantengono la configurazione associata al momento della preparazione.

## Prova tramite ente o soggetto di prova

1. In Anagrafica creare o aprire un ente di prova. Nella modale Email / PEC aggiungere un indirizzo di posta ordinaria controllato da chi effettua la prova. In alternativa associare quell'indirizzo al soggetto di prova LR o AS.
2. Aprire Invio email > Destinatari e messaggio > Nuovo messaggio. Premere Nessuno, cercare l'ente/soggetto di prova e selezionare soltanto il suo indirizzo.
3. Inserire oggetto e testo, per esempio PROVA INVIO GESTIONALE e Buongiorno {nome}. Aprire l'anteprima se necessario.
4. Premere Salva bozza e destinatari. Controllare che il destinatario sia uno solo e che l'indirizzo sia quello desiderato.
5. Confermare testo e destinatari, quindi Prepara campagna senza inviare. Non viene ancora spedita nessuna email.
6. In Invii e conferme controllare l'Indirizzo effettivo della campagna. Confermare e premere Invia prossimo lotto. Per questa prova è possibile impostare lotto 1 prima di preparare la campagna.
7. Verificare il registro e la ricezione nella casella, anche nello spam. Lo stato inviato conferma la spedizione accettata dal ponte, non il recapito nella casella.
8. Aprire il link ricevuto e premere Confermo la lettura. Ricaricare il registro per vedere la data. La sola apertura del link non registra conferme e non prova automaticamente chi lo ha utilizzato.

## Uso e conservazione

La selezione legge Mail, Associazioni, Soggetti e le liste attuali; non li modifica. Ogni bozza copia nominativi e indirizzi in email_destinatari, separata dai dati importati. Gli indirizzi uguali vengono accorpati; gli indirizzi invalidi o senza collegamento all'ente non sono selezionabili. Il segnaposto {nome} viene sostituito all'invio; il link di conferma viene aggiunto dal server.

Preparare una campagna congela testo, destinatari e configurazione. Non è modificabile: per cambiare selezione creare una nuova bozza. Invio a lotti fino a 20 e nei limiti della quota restituita dal ponte. Ogni lotto richiede conferma esplicita; nessun invio avviene salvando una configurazione o una bozza.

Gli stati in_corso e da_verificare non vengono reinviati automaticamente: usare Verifica esiti senza reinviare. Non cancellare il registro job_* nel progetto Google mentre serve ad accertare un invio, perché previene duplicati dopo timeout. I link già spediti continuano a usare il relativo hash memorizzato; non cambiare security.php per modificare le impostazioni email.

Admin e ghost autorizzato possono configurare il ponte. User deve ricevere il permesso gestione_email; lettura consente consultazione, scrittura consente preparazione e invio. Configurazione rimane riservata ad admin/ghost. Tutti i POST hanno controllo autorizzativo e CSRF; gli invii hanno anche un nonce monouso.

I campi riservati usano includes/dati.php e il contratto di cifratura corrente. L'importazione SQLite non modifica le tabelle email. Conservare database, private/security.php, private/dati_security.php e private/email_*.php. Non sono previsti allegati, invii pianificati o reinvii automatici degli esiti incerti.

## Verifiche automatiche

Su database effimero con trasporto simulato: invio solo agli indirizzi selezionati, oggetto non alterato, configurazione sempre produzione, rifiuto dei vecchi moduli, blocco delle campagne storiche senza chiamare il ponte, nessuna riscrittura dei vecchi destinatari, rifiuto di indirizzi effettivi divergenti o invii fuori rubrica, duplicati, lotti, firme, nonce e conferme. Verifiche browser di editor, configurazione senza campi/modalità rimossi, gestione credenziale mancante, mobile e permessi. Regressioni anche dopo cifratura. Nessun invio reale da CI.
