# Correzioni invio email: concorrenza, esiti e schema

## Comportamento

- Modifica, preparazione, invio, recupero esiti ed eliminazione usano lo stesso blocco della campagna. Una campagna con email inviate, in corso o da verificare non può essere riscritta come bozza; resta possibile duplicarla.
- Nel Registro, per le campagne con esiti incerti e permesso di scrittura, compare **Recupera esiti dal ponte**. Il comando verifica gli stati senza spedire email. Gli invii interrotti ancora `in_corso` diventano verificabili dopo cinque minuti.
- `sent` conferma l'invio. Solo `not_sent`, senza precedenti prove di invio o ricezione, rimette il lavoro in `da_inviare`: occorre poi premere manualmente **Invia**. `unknown`, `uncertain`, errori di rete e risposte non definitive non autorizzano un reinvio.
- Il ponte conserva il mancato invio per quota esaurita e permette un successivo tentativo con lo stesso identificativo e lo stesso contenuto. Mantiene la protezione dai duplicati per invii riusciti o incerti.
- Esiti e PDF leggono tutte le righe della campagna; i filtri si applicano all'elenco completo, anche oltre 500 destinatari. Il Registro non carica inutilmente il dettaglio di tutti gli invii.
- La normale apertura delle pagine verifica lo schema senza modificarlo. L'eventuale aggiunta della colonna già prevista `ConfermaLettura` avviene esclusivamente dalla procedura amministrativa di installazione/aggiornamento, tramite POST con CSRF e conferma del backup.

## Aggiornamento del sito

Sincronizzare con WinSCP questi file, mantenendo i percorsi:

- `includes/email.php`
- `includes/email_archivio.php`
- `includes/email_esiti.php`
- `invio_email.php`
- `configurazione_mail.php`
- `email_installazione.php`

Nel progetto Google Apps Script del ponte già in uso, sostituire il codice con `email/Bridge.gs`, salvare e aggiornare il deployment esistente scegliendo una **Nuova versione**. Conservare URL del deployment, account e proprietà esistenti. Non creare un nuovo progetto: il registro degli invii è nelle proprietà del progetto attuale.

Con il vecchio ponte gli esiti sconosciuti rimangono prudentemente bloccati; il mancato invio per quota viene conservato soltanto dai nuovi tentativi dopo l'aggiornamento del ponte. Non convertire manualmente i vecchi `unknown` in mancati invii.

Se il modulo è già aggiornato non occorre eseguire SQL o reinstallarlo. Se compare l'avviso di schema obsoleto, un amministratore deve aprire `email_installazione.php`, eseguire il backup e utilizzare **Installa o aggiorna le tabelle email**.

Non caricare `tests`, `.github` o questa documentazione sul sito pubblico. Questa correzione non cambia il contratto dei dati, le chiavi, le credenziali o la mappatura dell'importazione SQLite.

## Verifica

`tests/email_anomalie.php` richiede `POIMANAGER_TEST=1` e un database effimero predisposto dai test esistenti. Verifica i blocchi con una seconda connessione PDO, la conservazione degli esiti incerti, il recupero senza invii, 525 righe di esiti e report PDF, i permessi del nuovo comando e l'aggiornamento esplicito dello schema. La suite esegue la prova prima e dopo la cifratura. `tests/bridge.cjs` simula il ponte e controlla quota esaurita, recupero del mancato invio, nuovo tentativo e protezione dai duplicati.
