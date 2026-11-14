# Regole permanenti dell'applicazione

Leggere e rispettare LAYOUT.md per ogni nuova pagina o modifica dell'interfaccia. Salvo richiesta specifica dell'utente:
- Intestazione comune Gestione: 30px, bold.
- Sotto l'intestazione: voce/sottovoce selezionata, 15px, corsivo.
- Menu bar con voci principali e sottovoci dropdown; chiusura al clic esterno o alla selezione di una voce.
- Menu: 15px, corsivo.
- Titolo di pagina: 25px, bold. Descrizione sintetica sotto: 15px, corsivo.
- Utenti si trova in Servizi > Utenti.
- Usare page_start() e assets/layout.css e assets/menu.js. Non duplicare il layout nelle pagine.
- Controllare i permessi sul server prima dell'output; nascondere il menu non sostituisce l'autorizzazione.
- Aggiornare sempre il repository GitHub con le modifiche al codice.

- Ogni nuova voce/pannello: admin ha tutti i poteri automaticamente; user nessun permesso fino ad assegnazione esplicita. Nessuna concessione automatica ai nuovi pannelli.

- Dopo l'attivazione della cifratura, tutte le letture/scritture di campi riservati passano da includes/dati.php (dc_rows/dc_find/dc_insert/dc_update). Vietato usare SQL diretto sui campi testuali cifrati come se fossero dati leggibili.
- Rispettare schema/cifratura_v1.json e CIFRATURA_DATI.md; ogni modifica strutturale aggiorna il contratto, la migrazione e la mappatura SQLite v4. Non creare nuove copie testuali o indici di ricerca non approvati.
- Chiavi solo in private, mai in Git, log o risposte utente. Non modificare chiavi utenti/token email per migrare i dati gestionali.
