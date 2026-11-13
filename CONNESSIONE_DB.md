# POImanager: connessione e distribuzione

Repository: https://github.com/diesse63/forumterzo
Sito: https://poimanager.altervista.org/

## Configurazione iniziale

1. Aggiornare il checkout locale con `git pull --ff-only`.
2. Con WinSCP caricare `private/.htaccess` prima di inserire credenziali. Non sovrascrivere eventuali configurazioni esistenti senza confrontarle.
3. Creare temporaneamente `private/prova.txt` con testo non riservato: il suo URL deve restituire HTTP 403. Eliminare il file dopo la verifica. Se è leggibile, correggere la protezione prima di proseguire.
4. Copiare `private/config.esempio.php` in `private/config.php`. Il file reale è escluso da Git. Impostare password amministrativa e percorso del deposito; la password MySQL resta vuota come ricevuto, salvo diversa richiesta del servizio.
5. Verificare che anche `/private/config.php` risponda 403. Il file PHP contiene inoltre un controllo contro l'esecuzione diretta.
6. Caricare `includes` e la procedura aggiornata. Rimuovere il vecchio `importazione_sqlite/config.php`, che non viene più letto.
7. Aprire https://poimanager.altervista.org/importazione_sqlite/index.php tramite HTTPS.

La cartella `private` dentro la radice pubblica è protetta via Apache: la protezione dipende dall'effettiva applicazione di `.htaccess`. Se disponibile, preferire una directory fuori dalla radice pubblica e adattare il percorso del caricatore in `includes/database.php`.
I file SQLite e i registri restano obbligatoriamente fuori dalla radice pubblica, secondo il controllo già presente nell'importatore.

## Git e WinSCP

`.gitignore` riguarda Git, non WinSCP. Nella sincronizzazione ordinaria escludere `private/config.php` e il deposito privato di SQLite/log; trasferire la configurazione reale separatamente solo quando necessario. Disattivare la cancellazione dei file remoti assenti localmente oppure proteggere esplicitamente questi percorsi. Conservare e sincronizzare `private/.htaccess`: verificare che WinSCP mostri e trasferisca i file nascosti.

Non caricare la cartella `.git`, ZIP, backup, dump, roadmap o documentazione nella parte pubblica. Non salvare credenziali reali nel repository, che attualmente è pubblico. Se un file segreto fosse già tracciato, `.gitignore` non lo rimuove dalla cronologia.
Disabilitare `display_errors` in produzione e conservare i log PHP in una destinazione non pubblica.

## Uso applicativo

Nelle pagine PHP della radice:

```php
require_once __DIR__ . '/includes/database.php';
try {
    $pdo = poimanager_db();
    // Usare prepare/execute per query con input utente.
} catch (RuntimeException $error) {
    http_response_code(503);
    exit('Servizio temporaneamente non disponibile.');
}
```

La connessione non esegue migrazioni né importazioni. L'importatore mantiene controlli MySQL, transazioni e mappatura esistenti. Ogni futura modifica strutturale richiede ancora l'aggiornamento coordinato della procedura di importazione.
