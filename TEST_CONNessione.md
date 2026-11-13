# Test di connessione

Aprire https://poimanager.altervista.org/test_connessione.php dopo la sincronizzazione con WinSCP. Il test esegue soltanto SELECT 1: nessuna tabella o dato viene modificato. La risposta non espone credenziali o dettagli degli errori. Non verifica schema, permessi di scrittura o protezione del deposito SQLite. Dopo la prova rimuovere il file dal server ed escluderlo dalla sincronizzazione ordinaria; il sorgente resta disponibile nel repository per le verifiche successive.

Test PHP/MySQL sul server non eseguito in fase di creazione.
