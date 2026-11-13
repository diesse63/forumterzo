# Utenti e permessi — versione 2

## Installazione sul sito

1. Fare backup del database e dei file privati. Attivare la manutenzione.
2. Aggiornare il PC con git pull e sincronizzare il codice con WinSCP. Trasferire i file .htaccess; escludere tests, .github, documentazione, SQL e .git dallo spazio pubblico. Non sovrascrivere private/config.php.
3. In phpMyAdmin eseguire UNA SOLA VOLTA utenti/migrazione_v2.sql. Rinomina la vecchia Utenti in Utenti_legacy_v1 e crea le nuove tabelle. Non decifra, copia o cancella gli utenti precedenti. Il DDL MySQL non è transazionale: in caso di interruzione controllare le tabelle prima di ripetere. Per database nuovi usare invece struttura_solo_mysql8.sql, non entrambi gli script.
4. Aprire https://poimanager.altervista.org/installa_utenti.php. Richiede la password amministrativa dell'importazione presente in private/config.php (almeno 16 caratteri e non il segnaposto). Servono PHP Sodium e cURL. Il deposito private deve essere scrivibile da PHP e negare l'accesso via HTTP 403.
5. Inserire la password iniziale admin e quella user. Sono considerate comprensive del punto finale: rispettivamente 123456. e 1234567.; se il punto era solo punteggiatura, scegliere password conformi. Non sono codificate nel repository.
6. Solo durante sviluppo selezionare Modalità sviluppo e inserire la password ghost desiderata (123 come richiesto). Ghost non viene inserito in Utenti o UtentiPermessi; l'hash è nel file protetto private/security.php. Non usa la politica delle password ordinarie e non cambia password dall'interfaccia. È un accesso esplicito di sviluppo, segnalato nel pannello.
7. L'installatore genera la chiave di cifratura e inizializza admin e user. User nasce senza permessi. I ruoli esternobase ed esternoevoluto sono previsti ma non vengono creati come account finché non servono; ogni account di quei ruoli è obbligatoriamente inattivo.
8. Accedere da https://poimanager.altervista.org/login.php e aprire Gestione utenti. Rimuovere installa_utenti.php dal server dopo la configurazione ed escluderlo dalle sincronizzazioni ordinarie. È comunque bloccato dalla presenza di security.php.

## Produzione

In private/security.php impostare development=false e ghost_enabled=false; le sessioni ghost vengono invalidate alla richiesta successiva. Cambiare le password iniziali da Cambia password. Non lasciare ghost abilitato in produzione.
Conservare security.php e la sua chiave in un backup separato protetto: perderla rende illeggibili nome ed email. Non rigenerarla a ogni distribuzione. Escludere security.php da Git e dalla sincronizzazione con cancellazione o sovrascrittura; è generato sul server. La cifratura usa Sodium secretbox con nonce casuale; il nome di accesso è cercato tramite HMAC. PasswordHash usa password_hash/password_verify: le password non sono recuperabili. ID, ruolo, stato e permessi restano in chiaro come metadati autorizzativi.

private/config.php risulta già tracciato nel repository pubblico. .gitignore non rimuove ciò che è già tracciato o nella cronologia: eventuali credenziali reali pubblicate vanno sostituite, quindi il file va rimosso dall'indice mantenendone una copia privata locale/server. Questo rilascio non riscrive la cronologia e non modifica le credenziali esistenti.

## Gestione

Admin e ghost possono creare e modificare utenti, reimpostare password, disattivare account e assegnare i permessi. Non si può disattivare o declassare l'ultimo admin attivo. Le modifiche invalidano le sessioni dell'utente interessato. Per rimuovere accesso usare Disattivo: nessuna cancellazione distruttiva di account.
User non può aprire Gestione utenti né l'importazione, neppure con permessi inseriti manualmente. Per ogni altra schermata: Nessuno, Sola lettura, Lettura e scrittura. Scrittura include lettura. I ruoli esterni non possono accedere anche se ricevessero righe di permesso.
Il cambio password richiede la password attuale; minimo 7 caratteri e uno speciale, massimo 72 byte. I tentativi di login sono limitati per IP: dopo cinque fallimenti attendere 15 minuti. Sessione inattiva scade dopo 30 minuti.

## Collegamento delle schermate applicative

Il repository contiene l'importatore e questo modulo utenti; non contiene ancora tutte le schermate applicative. I pannelli registrati sono voci di permesso, non pagine già implementate. La pagina Pannello lo indica senza collegamenti inesistenti. Ogni nuova pagina deve richiamare all'inizio:

```php
require_once __DIR__ . '/includes/auth.php';
$utente = require_access('associazioni', 'lettura');
// Per ogni operazione di scrittura, PRIMA di modificare dati:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_access('associazioni', 'scrittura');
    check_csrf();
}
```

Anche API, export, allegati e azioni AJAX devono applicare il controllo. Nascondere il menu non basta. Registrare nuove schermate in Pannelli con codice stabile e titolo, poi richiamare lo stesso codice nella pagina. L'importatore ora richiede l'accesso comune admin/ghost: la password di importazione resta solo come credenziale di installazione.

## Importazione SQLite

Il motore carica mappa_v2.json (2.0.0). Utenti SQLite è ignorata integralmente, cifrata o meno, ed è ammessa anche la sua assenza; non viene controllata contro lo schema vecchio, né decifrata, conteggiata, inserita o sovrascritta. Le altre 15 tabelle mantengono tutti i controlli e devono essere vuote. Nuovi utenti, permessi e ruoli non vengono toccati dall'importazione e possono essere già popolati.
La mappa v1 resta solo come storico. Qualsiasi futura modifica strutturale richiede aggiornamento di migrazione, schema, mappa e test insieme.

## Verifica e rollback

GitHub Actions esegue sintassi PHP e test su MySQL 8 effimero: migrazione senza importare utenti legacy, policy password, cifratura e manomissione, accessi esterni, ghost vietato in produzione, lettura/scrittura, revoca sessioni e importazione che preserva account e permessi.
Restano da verificare su Altervista: driver, Sodium/cURL, permessi filesystem, HTTP 403, login da browser, CSRF, creazione e modifica utenti, menu mobile, schema live e importazione completa di una copia aggiornata. Non sono state eseguite migrazioni o scritture sul database Altervista.
Per rollback: manutenzione e backup; ripristinare codice e database dallo stesso punto temporale. Non rinominare alla cieca tabelle con dati nuovi. Conservare Utenti_legacy_v1 fino alla verifica, senza esporla o riutilizzare le sue password.
