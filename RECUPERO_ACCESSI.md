# Recupero accessi dopo perdita di security.php

Usare solo quando private/security.php è definitivamente perduto e gli utenti erano già installati. Non rinominare security.esempio.php, non ripetere utenti/migrazione_v2.sql e non svuotare tabelle.

1. Fare una copia completa del database con phpMyAdmin e della cartella private con WinSCP. Chiudere le sessioni applicative e sospendere sincronizzazioni automatiche che possano cancellare file privati.
2. Aggiornare il PC con git pull --ff-only e caricare con WinSCP recupera_utenti.php, installa_utenti.php, includes/auth.php, includes/email.php e includes/recupero_utenti.php. Mantenere private/.htaccess.
3. Generare sul PC una credenziale temporanea con PowerShell:

   $bytes = New-Object byte[] 32
   $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
   $rng.GetBytes($bytes)
   $rng.Dispose()
   -join ($bytes | ForEach-Object { $_.ToString("x2") })

4. Copiare private/recupero_utenti.esempio.php come private/recupero_utenti.php e sostituire il segnaposto token con il risultato di 64 caratteri. Caricare il file sul server nella cartella private. Non pubblicarlo su Git e non comunicarlo in chat. La credenziale temporanea NON è la nuova password admin.
5. Aprire https://poimanager.altervista.org/recupera_utenti.php. Inserire la credenziale temporanea e premere **Verifica senza modificare**. Nessuna tabella o chiave viene scritta da questo controllo.
6. Se la cifratura era stata avviata, la procedura richiede il dati_security.php originale e ne verifica la corrispondenza col database. In assenza o con chiave diversa si blocca: una nuova chiave non recupera i dati precedenti. Segnala anche righe _Cifrato prive del registro. Non serve eseguire manualmente query per questo controllo.
7. Se la verifica riesce, inserire una nuova password admin (minimo 7 caratteri e uno speciale), confermare il backup e premere **Archivia accessi e crea nuovo admin**.
8. Entrare con nome admin e nuova password. Ricreare user e gli altri account da Servizi > Utenti e assegnare nuovamente i permessi. Ghost resta disabilitato; esterni restano inattivi secondo le regole comuni.
9. Copiare subito il nuovo private/security.php in un backup protetto e verificare che WinSCP non lo elimini. Il file temporaneo recupero_utenti.php in private viene eliminato dopo il successo; verificare con WinSCP ed eliminarlo se ancora presente. La pagina pubblica resta bloccata quando security.php esiste.

## Conservazione

La procedura conserva utenti e permessi in due tabelle con nomi Utenti_rec_<casuale> e Permessi_rec_<casuale>, quindi sostituisce i soli accessi correnti con un amministratore. Copia, cancellazione dei soli account correnti e inserimento admin avvengono nella stessa transazione InnoDB; gli archivi vengono creati prima. I profili archiviati restano indecifrabili senza la vecchia chiave: non sono un recupero dei nomi o delle password.

Nuovo ID amministratore senza azzerare AUTO_INCREMENT e versione sessione casuale invalidano gli accessi precedenti. La chiave originale non viene sovrascritta, se presente. Una verifica HTTP 403 della cartella privata precede la scrittura della nuova chiave. La password di installazione già configurata non abilita questo recupero: occorre la nuova credenziale privata temporanea.

Enti, persone, recapiti, registri email, configurazioni e chiavi ponte Gmail non vengono modificati. dati_security.php non viene creato, sostituito o eliminato.

## Email preparate con la vecchia chiave

I collegamenti nelle email già inviate restano confermabili tramite il loro hash già memorizzato. Gli invii ancora da spedire preparati con la chiave perduta vengono bloccati prima di inviare: ricreare il messaggio come nuova bozza e prepararlo nuovamente. Gli invii con esito incerto non vanno reinviati automaticamente: usare la verifica esiti del ponte.

## Errori o interruzioni

Se il recupero fallisce prima del tentativo di COMMIT, la transazione torna allo stato precedente e la nuova chiave eventualmente scritta viene rimossa. Gli archivi appena creati possono rimanere vuoti. Se il COMMIT ha esito incerto, la chiave viene conservata: non eliminarla e non ripetere l'installazione; verificare gli account e gli archivi dal backup. Dopo un'interruzione improvvisa con security.php presente, non cancellarlo per riprovare: preservarlo e verificare lo stato. L'operazione non recupera dati cifrati con una chiave perduta.
