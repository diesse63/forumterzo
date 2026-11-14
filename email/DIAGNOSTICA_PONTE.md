# Risposta del ponte non autenticata

Non significa necessariamente password Gmail errata: il ponte non usa la password Gmail.
Il controllo distingue:
- risposta HTML di Google: possibile richiesta di login, autorizzazioni o errore di deployment;
- Ponte non configurato: BRIDGE_SECRET assente o non esattamente 64 caratteri hex minuscoli;
- JSON di altro formato: codice diverso, vecchia versione distribuita o progetto errato;
- JSON firmato con firma diversa: credenziali non corrispondenti o URL di altro progetto;
- risposta del doGet: il POST è stato trasformato in GET.

## Confronto senza mostrare le credenziali
La sezione Configurazione sul sito mostra l'impronta SHA-256 troncata a 16 caratteri della credenziale salvata.
Aggiornare Codice.gs con email/Bridge.gs, salvare e selezionare autorizzaPonte: il registro Google mostra la stessa impronta della proprietà BRIDGE_SECRET. La funzione non invia email.
Le due impronte devono coincidere. Non inserire l'impronta al posto della credenziale: è solo un indicatore di confronto. Non divulgare la credenziale e non modificare quelle dei vecchi progetti con campagne pendenti.

## Deployment
Salvare il codice nell'editor non aggiorna automaticamente un deployment esistente. In Apps Script: Distribuisci > Gestisci deployment > matita > Versione: Nuova versione > Distribuisci. Esegui come Me; accesso Chiunque. Usare l'URL del progetto corretto terminante /exec.
Se l'accesso Chiunque non è consentito dal proprio account, non sostituirlo con un deployment che richiede il login e aspettarsi lo stesso funzionamento dal server.
Aggiornare git e trasferire includes/email_http.php e includes/email_config_form.php su Altervista. Aggiornare il progetto Google con Bridge.gs soltanto per la diagnostica impronta. Riprova Test connessione, senza inviare email.
