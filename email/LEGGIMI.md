# Gestione email e ponte Gmail — installazione

## Cosa comprende

Servizi > Gestione email: scelta degli indirizzi di enti, legali rappresentanti e altri soggetti; filtri per nome/email, tipologia e mailing list; bozza con editor, personalizzazione {nome}, anteprima, preparazione, invio a lotti, registro e conferme volontarie.
Le quattro nuove tabelle email_messaggi, email_destinatari, email_invii, email_configurazione sono separate dalle anagrafiche. Nessuna FK punta alle tabelle importate da SQLite: nome, indirizzo e riferimento origine vengono copiati. L'importazione SQLite non legge, svuota o aggiorna le tabelle email. Non è creata una seconda rubrica modificabile: i contatti attuali sono solo letti.
I nuovi dati email non vengono inseriti in Mail/MailList/ListaMail. La sola registrazione nella tabella Pannelli rende il nuovo permesso disponibile senza concederlo agli user.

## 1. Aggiorna Altervista

Eseguire git pull e trasferire con WinSCP:
- gestione_email.php, email_installazione.php, email_conferma.php nella radice;
- includes/email.php, includes/email_http.php, includes/email_config_form.php, includes/layout.php;
- pannello.php, assets/email.css, assets/email.js;
- cartella email con .htaccess e migrazione_v1.sql. Il file Bridge.gs può restare sul PC: serve per Google.
Non trasferire tests, .github, .git o credenziali da GitHub. Non sovrascrivere private/config.php o private/security.php.
Eseguire un backup del database. Accedere come admin e aprire /email_installazione.php. Confermare il backup e premere Installa. La procedura crea soltanto le nuove tabelle e registra il nuovo pannello. È ripetibile su schema compatibile. DDL MySQL confermato per singola istruzione: in caso di errore verificare lo stato prima di ritentare.
Nessuna tabella gestionale o utente viene cancellata o modificata. Non ripetere la migrazione utenti.

## 2. Prepara un progetto Google di prova

1. Aprire https://script.google.com con l'account Gmail di prova.
2. Creare un NUOVO progetto, ad esempio Ponte Gestione email - TEST. Non sostituire il vecchio progetto collegato al foglio.
3. Nel file Codice.gs sostituire il contenuto iniziale con email/Bridge.gs del repository e salvare.
4. Su Altervista aprire Servizi > Gestione email > Configurazione. Premere Genera nuova credenziale. Copiarla senza comunicarla in chat.
5. In Apps Script aprire Impostazioni progetto > Proprietà script > Aggiungi proprietà. Nome BRIDGE_SECRET, valore la credenziale appena generata. Salvare.
6. Nell'editor scegliere la funzione autorizzaPonte e premere Esegui. Concedere le autorizzazioni al proprio progetto verificandone nome e account. Questa funzione legge account/quota e NON spedisce email.
7. Distribuisci > Nuovo deployment > tipo App web. Esegui come: Me; accesso: Chiunque. Alcuni account di organizzazione possono vietare l'accesso anonimo: in quel caso non usare un deployment che richiede il login come se fosse il ponte pubblico.
8. Copiare l'URL che inizia con https://script.google.com/macros/s/ e termina con /exec, non /dev.
Il ponte pubblico richiede POST firmati: conoscere l'URL non autorizza l'invio. Non condivide il Foglio Google e non richiede password Gmail sul sito.

## 3. Configura Altervista

Nella configurazione del modulo inserire:
- URL /exec del nuovo progetto;
- mittente: indirizzo dell'account che possiede ed esegue lo script (deve corrispondere);
- nome visualizzato;
- modalità TEST;
- destinatario di prova: un indirizzo controllato da te;
- lotto: da 1 a 20;
- la stessa credenziale impostata in BRIDGE_SECRET.

Salvare la configurazione. Il sito conserva la credenziale in un file PHP protetto private/email_<riferimento>.php, mai nelle tabelle email né nel repository. La cartella private deve essere scrivibile da PHP e mantenere .htaccess. Non modificare la chiave di security.php: serve anche a rendere validi i collegamenti delle conferme.
Premere Test connessione: verifica account, firma delle risposte e quota; non invia email. Questo è il test effettivo Altervista > Google, non una semplice verifica della whitelist. Seguendo i redirect di ContentService vengono accettate solo risposte da script.googleusercontent.com: il corpo con dati e firma non viene inoltrato ai redirect.
Solo se riesce e se lo desideri, confermare e premere Invia email di prova. Si tratta di un singolo invio reale al solo destinatario di prova, registrato separatamente. La costruzione e i test automatici NON inviano email reali.

Se il test fallisce: controllare URL /exec, accesso Chiunque, Esegui come Me, autorizzazione, credenziale identica, account esatto e connessioni server-to-server verso Google. Non disabilitare TLS e non inserire password Gmail come credenziale del ponte.

## 4. Uso ordinario

1. Destinatari e messaggio: selezionare gli indirizzi desiderati. Seleziona visibili opera soltanto sui risultati dei filtri. Nessuno azzera tutta la selezione.
2. Indirizzi uguali (normalizzati in minuscolo) sono accorpati nella bozza; prevale il primo nominativo secondo ID Mail. Indirizzi non validi o senza ente collegato sono disabilitati.
3. Inserire oggetto e testo, usare {nome}, formattare e visualizzare l'anteprima. Il testo incollato è trattato come testo semplice; il server applica una whitelist HTML. Non sono supportati immagini/allegati.
4. Salvare bozza e destinatari. L'anteprima è dimostrativa e non registra conferme.
5. Preparare la campagna: congela testo, nominativi, indirizzi e versione configurazione. NON invia.
6. Aprire Invii e conferme, verificare modalità/mittente, confermare e premere Invia prossimo lotto.
7. In TEST, ogni messaggio è inviato esclusivamente all'indirizzo di prova, con prefisso TEST. Le email dei nominativi non vengono usate come destinatari effettivi.
8. Riprendere i lotti rimanenti. La quota è quella restituita da Google; un account consumer ha limiti più bassi di Workspace, e le quote possono variare.
9. Gli stati in_corso o da_verificare non vengono reinviati automaticamente. Verifica esiti interroga il registro del ponte senza spedire. Se il ponte non può accertare l'esito, controllare le esecuzioni Google prima di qualsiasi eventuale nuovo messaggio.
10. Il link personale porta a email_conferma.php, senza login e senza mostrare dati della rubrica. GET non conferma; occorre premere il pulsante POST. La conferma è idempotente; i nuovi link scadono dopo 365 giorni. Non è prova automatica di lettura/consegna e non identifica chi usa un link inoltrato.

## 5. Modificare la configurazione in seguito

Ogni salvataggio crea una nuova versione; le campagne già preparate restano associate a quella precedente.
Per cambiare solo modalità, nome o lotto si può riutilizzare la credenziale (campo vuoto). Per cambiare account o ruotare la credenziale, creare un progetto Google SEPARATO con un nuovo deployment e salvare una nuova versione su Altervista. Non modificare BRIDGE_SECRET dello stesso progetto mentre vecchie campagne devono ancora essere completate: i vecchi deployment condividono le proprietà del progetto.
Conservare i vecchi deployment e private/email_*.php per le campagne pendenti. Le conferme dei messaggi già spediti rimangono su Altervista e non dipendono dal vecchio deployment.
In PRODUZIONE è richiesta conferma esplicita in configurazione, poi una distinta conferma per ciascun lotto ai destinatari reali. Nessun automatismo converte le campagne di test già preparate.
Le modifiche a anagrafiche, indirizzi e liste successive al salvataggio della bozza non alterano le sue copie; salvare nuovamente la bozza rilegge gli indirizzi selezionati. Un messaggio preparato non è modificabile.

## Permessi e conservazione

Admin e ghost di sviluppo gestiscono configurazione e installazione. Un user parte senza alcun diritto alla nuova voce: assegnare lettura/scrittura da Servizi > Utenti. Lettura consente consultazione; scrittura anche preparazione/invio. La configurazione resta riservata ad admin/ghost anche se user ha scrittura.
Tutti i POST amministrativi hanno CSRF; le azioni di invio hanno anche nonce monouso per bloccare doppio clic/replay. Il ponte usa HMAC SHA-256, timestamp e nonce, controlla il mittente e conserva un registro idempotente per ID invio.
Non cancellare le proprietà job_* del progetto Google mentre potrebbero essere necessarie per verificare esiti incerti: il loro mantenimento evita reinvii. Anche Apps Script ha limiti di spazio e quote: in caso di saturazione il modulo si ferma, non cancella automaticamente il registro per far proseguire invii.
Registro invii con date UTC. Google registra account e metadati necessari alla spedizione; il contenuto email transita su Google. Nessun foglio di contatti viene creato. Fare backup di nuove tabelle, security.php e private/email_*.php.
Non sono previsti invio pianificato, allegati, editor di rubrica separata o ripristino automatico di invii incerti in questa versione.

## Validazione

CI: MySQL effimero e trasporto simulato, nessuna email reale; controlli su schema dedicato, anagrafiche immutate, sanitizzazione, bozze, copie destinatari, configurazioni versionate, isolamento TEST, lotti, firme/redirect, esiti incerti, nonce e conferme. Il ponte Apps Script viene eseguito in un ambiente simulato con MailApp fittizio. Il browser verifica editor, filtri, anteprima, salvataggio, mobile e permessi.
Restano da eseguire dal proprietario: deployment Google, Test connessione sul server Altervista e singolo invio di prova autorizzato. Nessuna configurazione Gmail reale è stata inventata, nessun deployment Google è stato creato durante questa consegna.
