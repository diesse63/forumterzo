# Invio email — destinatari dell’anagrafica

La procedura usa soltanto gli indirizzi selezionati nella rubrica. Non esistono più il selettore TEST/Produzione, un destinatario di prova separato o un pulsante di invio fuori rubrica. Per provare la spedizione creare un soggetto/ente di prova e selezionare soltanto il suo indirizzo.

## Aggiornamento del codice esistente

1. Fare il normale backup di database e cartella private, poi git pull --ff-only sul PC.
2. Caricare con WinSCP invio_email.php, email_rubrica.php, includes/email.php, includes/email_compose.php, includes/email_archivio.php, includes/email_config_form.php, assets/email.js, assets/email.css e assets/email_destinatari.js. Mantenere le altre parti dell'applicazione aggiornate. Non caricare tests, .github o credenziali pubbliche. Tenere disattivata l'eliminazione automatica di WinSCP.
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

Preparare una campagna congela testo, destinatari e configurazione. Non è modificabile: per cambiare selezione duplicarla come nuova bozza. Invio a lotti fino a 20 e nei limiti della quota restituita dal ponte. Ogni lotto richiede conferma esplicita; nessun invio avviene salvando una configurazione o una bozza.

Gli stati in_corso e da_verificare non vengono reinviati automaticamente: usare Verifica esiti senza reinviare. Non cancellare il registro job_* nel progetto Google mentre serve ad accertare un invio, perché previene duplicati dopo timeout. I link già spediti continuano a usare il relativo hash memorizzato; non cambiare security.php per modificare le impostazioni email.

Admin e ghost autorizzato possono configurare il ponte. User deve ricevere il permesso gestione_email; lettura consente consultazione, scrittura consente preparazione e invio. Configurazione rimane riservata ad admin/ghost. Tutti i POST hanno controllo autorizzativo e CSRF; gli invii hanno anche un nonce monouso.

I campi riservati usano includes/dati.php e il contratto di cifratura corrente. L'importazione SQLite non modifica le tabelle email. Conservare database, private/security.php, private/dati_security.php e private/email_*.php. Non sono previsti allegati, invii pianificati o reinvii automatici degli esiti incerti.

## Verifiche automatiche

Su database effimero con trasporto simulato: invio solo agli indirizzi selezionati, oggetto non alterato, configurazione sempre produzione, rifiuto dei vecchi moduli, blocco delle campagne storiche senza chiamare il ponte, nessuna riscrittura dei vecchi destinatari, rifiuto di indirizzi effettivi divergenti o invii fuori rubrica, duplicati, lotti, firme, nonce e conferme. Verifiche browser di editor, configurazione senza campi/modalità rimossi, gestione credenziale mancante, mobile e permessi. Regressioni anche dopo cifratura. Nessun invio reale da CI.


## Flusso libero e layout

La sezione Mail occupa tutta la riga iniziale. Sotto sono affiancati il riquadro Destinatari dalla rubrica e la lista Mail e nominativi selezionati; restano affiancati anche su mobile, con campi e testi compatti. I collegamenti Prepara prima la mail / Seleziona prima i nominativi permettono di scegliere da dove iniziare. Si può salvare una bozza con il solo messaggio, con i soli destinatari o con entrambi. Prima dell'elaborazione sono obbligatori oggetto, testo e almeno un indirizzo.

La rubrica mostra soltanto indirizzi formalmente validi e collegati a un ente. Visualizzazione per nominativo con ruolo LR/AS e relativo ente, oppure raggruppata per ente con i riferimenti LR/AS. Il nome completo è quello registrato in Soggetti.Nome: non vengono inventate separazioni tra nome e cognome. Restano selezionabili anche gli indirizzi propri degli enti. Persone senza email possono comparire come riferimenti descrittivi dell'ente, ma non come destinatari selezionabili.

La selezione è unica per indirizzo normalizzato in minuscolo. Scegliendo una riga, tutte le altre con lo stesso indirizzo sono evidenziate e non possono creare un secondo destinatario. La lista a destra indica il nominativo scelto, il ruolo, l'ente e l'email; Rimuovi libera quell'indirizzo. Seleziona visibili rispetta tutti i filtri e non duplica le email. I controlli vengono ripetuti sul server.

La rubrica è riletta ad ogni apertura, ogni 5 secondi mentre la pagina è visibile, durante la ricerca con una nuova lettura dopo 300 ms, al cambio dei filtri, al ritorno sulla pagina e con Aggiorna rubrica. Il testo in preparazione non viene sovrascritto. I destinatari selezionati seguono l'ID del recapito: nomi e indirizzi vengono aggiornati e i recapiti rimossi/non validi vengono tolti dalla selezione con avviso. Anche salvataggio e preparazione rileggono il database; se la rubrica cambia dopo il salvataggio, occorre rivedere e salvare la bozza prima di elaborarla. I dati di una mail già elaborata o spedita restano congelati per rappresentare ciò che è stato effettivamente inviato, distinti dalla rubrica attuale.

## Archivio, duplicazione ed eliminazione

Archivio e invii contiene tutte le mail, con ricerca per oggetto, filtro di stato e paginazione: Bozza, Elaborata, Parzialmente inviata, Inviata e Da verificare. I conteggi mostrano destinatari e invii; Apri mail permette di consultare il messaggio.

Duplica come nuova bozza copia soltanto il contenuto e, se selezionato, i destinatari ancora validi in rubrica aggiornandoli ai dati attuali. Non copia la configurazione congelata, i job di invio, le conferme o lo stato inviato. Non spedisce nulla. I destinatari spariti/non validi sono esclusi e vanno ricontrollati nella nuova bozza.

Elimina mail dal gestionale richiede conferma esplicita e rimuove messaggio, destinatari copiati e registro relativo in una transazione. Le email già spedite nelle caselle non vengono ritirate e i relativi link di conferma smettono di funzionare. L'eliminazione non modifica la rubrica né il registro idempotente Google. Viene bloccata durante un lotto o in presenza di esiti in_corso/da_verificare: accertare prima lo stato. Richiede il permesso di scrittura, come duplicazione e salvataggio. Versione della mail e lock impediscono cancellazioni su messaggi modificati o in invio contemporaneamente.

Nessuna modifica strutturale o migrazione SQL per questo flusso: usa le stesse tabelle email e le funzioni dc_* anche per le copie. Le verifiche automatiche coprono ordine libero, bozze parziali, rubrica live, raggruppamenti LR/AS/enti, deduplicazione immediata e server, copie senza invii, cancellazioni con lock e rollback, permessi e dati cifrati. I test non inviano email reali.


## Filtri nominativi e allineamento dei riquadri

Visualizzazione Per nominativo: ogni riga mostra nome completo, ruolo LR/AS, ente associato e indirizzo email. Visualizzazione Per ente: ogni riga mostra prima l'ente, poi il singolo nominativo collegato e il suo ruolo, sempre con l'email. Gli indirizzi generali degli enti sono indicati come tali, senza attribuirli a una persona. La ricerca confronta sempre ente, nominativo della singola riga e indirizzo, indipendentemente dalla visualizzazione; non include gli altri nominativi dell'ente quando non corrispondono al testo cercato. Il filtro Tipologia si combina con la ricerca e aggiorna immediatamente il risultato.

Ogni digitazione o cambio filtro ricalcola subito la lista e richiede una nuova lettura della rubrica dopo una breve pausa. Un aggiornamento periodico ogni 5 secondi acquisisce anche modifiche fatte da altre pagine, mantenendo testo e selezioni. Tipologie e liste nei filtri vengono rilette con la rubrica. Non occorre premere un pulsante per applicare i filtri.

Anagrafica destinatari e Mail selezionate restano sulla stessa riga, con bordi superiori allineati anche scorrendo la pagina. Scorrono separatamente i contenuti delle liste; non c'è più il riquadro selezionati sticky. I CSS e gli script hanno una versione basata sul contenuto per evitare che la cache mantenga una vecchia versione dopo l'upload.

Per questo aggiornamento caricare invio_email.php, email_rubrica.php, includes/email_compose.php, assets/email_destinatari.js e assets/email.css. Nessuna modifica alle tabelle o alle chiavi. Le prove browser includono ricerca per singolo nominativo in entrambe le viste, combinazione con la tipologia, lettura dal DB mentre si digita, aggiornamento automatico senza pulsanti e allineamento dei riquadri su desktop e mobile, prima e dopo cifratura.


## Selezione di una mail salvata nella sezione Mail

All'inizio del riquadro Mail il campo Mail salvata elenca tutte le mail, dalla più recente, con ID, oggetto (anche se vuoto) e stato. Scegliere una voce e premere Apri mail salvata per caricare testo e destinatari nella pagina. Nuova mail apre una composizione vuota. Il selettore usa una richiesta GET separata: non salva, non prepara e non invia messaggi.

Una bozza rimane modificabile secondo i permessi; una mail elaborata, parzialmente inviata o inviata resta in consultazione, con destinatari congelati. Per riutilizzarla creare una copia dall'archivio. Quando ci sono modifiche non salvate, l'apertura di un'altra mail chiede conferma. La normale scrittura della bozza continua ad aggiornare la mail corrente, anche se nel selettore è stata scelta un'altra voce senza aprirla.

Per questo aggiornamento caricare includes/email_compose.php, assets/email.js e assets/email.css dopo git pull. Nessuna modifica al database o alle credenziali. Le verifiche browser coprono riapertura di una bozza, annullamento dell'apertura con modifiche non salvate, associazione corretta del salvataggio e consultazione delle mail elaborate, anche con dati cifrati.
