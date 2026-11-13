# Verifica delle cartelle protette

L'installazione verifica un file temporaneo non riservato in private, eliminandolo sempre dopo la prova. Il deposito SQLite usa lo stesso controllo.
Il controllo usa GET HTTPS, senza seguire redirect, e prova cURL, TLS socket e stream HTTPS disponibili finché ottiene 403. TLS verifica certificato e nome del server. Un errore di connessione non viene interpretato come cartella protetta.

Se l'installazione si blocca, il messaggio riporta ogni tentativo:
- nessuna risposta HTTPS valida: possibile blocco di rete in uscita, certificato non verificabile o timeout. Controllare sul pannello Altervista se il piano consente connessioni HTTPS verso il proprio sito; non disabilitare la verifica TLS.
- HTTP 200: verificare private/.htaccess (contenuto Require all denied), nome completo e trasferimento dei file nascosti.
- HTTP 301/302: redirect non seguito intenzionalmente; verificare che il sito sia accessibile direttamente all'origine HTTPS configurata.
- HTTP 404: percorso pubblicato non corrispondente o risposta mascherata; non basta a confermare la protezione.
- HTTP 500: verificare i log del server e la compatibilità delle direttive .htaccess.

Un controllo esterno su un URL casuale inesistente non sostituisce la verifica del file temporaneo realmente creato. Il browser può raggiungere il sito anche quando le connessioni PHP in uscita sono bloccate.

Aggiornare git e sincronizzare includes/protezione_http.php, installa_utenti.php e importazione_sqlite/deposito.php. Non ripetere una migrazione già completata, non eliminare security.php e non cambiare credenziali per questo errore.
