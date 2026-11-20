# POImanager — Amministratore di emergenza e backup cifrato

Il supervisore può creare un nuovo amministratore dal browser, anche quando tutti gli accessi ordinari sono bloccati. È un intervento sugli accessi che richiede PHP, MySQL, la configurazione utenti e il relativo motore di cifratura funzionanti. L’esportazione si avvia da Servizi → Backup cifrato con un amministratore autenticato.

## Hosting di prova e sito definitivo

Usare il sito di prova per il collaudo. I nuovi percorsi sono relativi al dominio del sito e presuppongono che l’applicazione sia installata nella cartella principale. L’indirizzo definitivo può essere scelto dopo il collaudo.

Per il nuovo hosting Altervista, impostare la connessione di produzione in `private/config.php` e verificare HTTPS, PHP, PDO MySQL, ZIP AES-256 e la protezione della cartella privata. Generare credenziali del supervisore distinte da quelle di prova. Per ripristinare dati cifrati da un backup, usare invece le chiavi originali abbinate a quel database. Prima del passaggio, controllare anche gli eventuali URL assoluti configurati nelle altre funzioni del gestionale.

## Preparazione del referente tecnico

1. Installare la modifica su una copia isolata con la versione di PHP e MySQL della produzione. Applicare `utenti/migrazione_supervisore.sql`. La migrazione aggiunge il registro dei codici utilizzati. `Utenti` e `SupervisoreRecuperi` devono usare InnoDB.
2. Verificare che `/private/` sia protetta da `Require all denied` con una risorsa di prova priva di segreti. Copiare il modello `private/supervisore.esempio.php` come `private/supervisore.php` solo nell’hosting. Conservare `private/security.php` e `private/dati_security.php`.
3. Scegliere una password di emergenza casuale fra 20 e 72 byte e generare un codice monouso casuale di 32 byte. Generare gli hash sul PC, senza inserire password o token nei comandi salvati nella cronologia. Esempio con PowerShell 7 e PHP CLI locale:

   ```powershell
   $p = Read-Host -MaskInput 'Password di emergenza'
   $p | php -r '$p=rtrim(stream_get_contents(STDIN), chr(13).chr(10)); if(strlen($p)<20 || strlen($p)>72){exit(1);} echo password_hash($p, PASSWORD_DEFAULT), PHP_EOL;'
   Remove-Variable p
   php -r '$t=bin2hex(random_bytes(32)); echo $t, PHP_EOL, hash("sha256", $t), PHP_EOL;'
   ```

   Inserire gli hash in `password_hash` e `token_hash`. Custodire il codice in chiaro separatamente dalla password. Impostare `versione_app` al commit distribuito e `cartelle_dati` alle directory relative degli allegati effettivamente presenti. Evitare sincronizzazioni che sovrascrivono la configurazione privata.
4. Lasciare `scadenza_utc` vuota durante l’uso ordinario. Per abilitare la creazione, impostare in emergenza una scadenza UTC a breve termine nel formato `AAAA-MM-GGThh:mm:ssZ`. Dopo l’intervento, svuotare la scadenza e generare un nuovo codice prima di una futura attivazione. Dopo ogni ripristino del database usare un codice nuovo, perché una copia più vecchia del registro potrebbe precedere il consumo del codice precedente.

## Creare l’amministratore dal browser

1. Aprire `/supervisore_admin.php` via HTTPS. Inserire password di emergenza e codice monouso, quindi una password distinta per il nuovo amministratore.
2. Salvare e annotare il nome generato. Il prefisso `admin_rec_` e 128 bit casuali riducono il rischio di collisione anche se alcuni profili storici sono illeggibili. Il vincolo univoco sull’indice del nome blocca una collisione rilevabile con la chiave attiva.
3. Entrare dal login normale con il nome mostrato e verificare Servizi → Utenti. Se l’esito del salvataggio è incerto, il referente verifica `SupervisoreRecuperi` prima di generare un altro codice.

## Scaricare e verificare il backup

1. Accedere come amministratore e aprire **Servizi → Backup cifrato**. Sospendere modifiche al sito, agli allegati e allo schema durante l’esportazione.
2. Immettere la password di emergenza. Il download contiene il dump SQL, un manifest con la versione distribuita e i file privati e allegati configurati. Il file con l’autorizzazione di emergenza e il token dell’eventuale procedura precedente sono esclusi.
3. Aprire il file con un programma compatibile con ZIP AES-256, per esempio 7-Zip, e usare la funzione **Test**. I contenuti sono cifrati. I nomi dei file sono visibili. Conservare la password a parte e custodire il download come una copia del database e delle chiavi.
4. Su una copia isolata ripristinare la versione del sito indicata, il database SQL in uno schema vuoto, i file privati e gli allegati. Configurare la connessione della copia e rigenerare l’autorizzazione del supervisore se necessaria. Tenere disabilitati invii, cron e sincronizzazioni reali. Verificare login, permessi, anagrafica cifrata, allegati e conteggi.

L’esportazione richiede PHP con PDO MySQL, ZIP con supporto AES-256, spazio temporaneo fuori dalla cartella pubblica e permessi per leggere le definizioni dei trigger. Supporta tabelle InnoDB e trigger. In presenza di viste, routine, eventi, colonne spaziali o altre tabelle non supportate usare il backup dell’hosting. Misurare il tempo di esportazione con una copia realistica prima di abilitarla in produzione.

## Collaudo prima della produzione

- Simulare il blocco di tutti gli account su una copia. Verificare rifiuto di password errata, data scaduta e codice già usato. Verificare che un tentativo riuscito aggiunga un solo admin e una riga al registro e che gli utenti e i permessi precedenti restino disponibili.
- Verificare che un account con ruolo user riceva un rifiuto sul backup e che una password errata non produca alcun file.
- Provare il ripristino completo con l’archivio scaricato. Verificare anche il caso di database o chiave non disponibili e usare il ripristino tramite hosting per quei guasti.
