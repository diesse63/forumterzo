# Clone anagrafica enti

Pagina anagrafica.php, menu Anagrafiche > Enti. Conserva le funzioni della pagina originale: elenco, filtro tipologia, ricerca per ente/CF/persona, conteggio, 50 record per pagina, incarichi, apertura scheda e nuovo ente con precompilazione RUNTS. Le persone sono lette da Soggetti, con Tipo LR e AS, senza vecchi endpoint Referenti/AltriSoggetti.
La ricerca e la paginazione avvengono lato MySQL; JavaScript aggiorna i risultati durante la digitazione senza scaricare tutte le persone. I filtri e l'inserimento manuale funzionano anche senza JavaScript. Su mobile l'elenco diventa una serie di schede.
La scheda anagrafica_singola.php è in consultazione e mostra dati, persone, email e telefoni; la gestione completa di modifiche/recapiti/allineamenti dell'originale resta per la fase dedicata già proposta.

## Permessi e integrità
Tutte le richieste richiedono associazioni.lettura oppure associazioni.scrittura tramite i controlli esistenti. User in lettura non vede Nuovo ente e riceve 403 tentando POST o l'API di ricerca RUNTS. Admin/ghost hanno accesso secondo le regole comuni.
Non vengono aggiunti privilegi né modificati quelli esistenti. Il pannello associazioni è già registrato dall'installazione utenti: assegnare eventuali diritti user da Servizi > Utenti.
Salvataggio POST con CSRF e redirect; ente ed eventuale persona RUNTS sono inseriti nella stessa transazione. Il nominativo RUNTS viene riletto sul server e richiede scelta esplicita LR/AS. Nessuna attribuzione automatica del ruolo legale.
La tipologia ETS è riconosciuta dal nome ENTI DEL TERZO SETTORE, non da un ID fisso. Fuori da ETS gli incarichi vengono azzerati anche sul server; cambiando tipologia si azzerano nel modulo.
Denominazione obbligatoria e univoca secondo il vincolo esistente; CF facoltativo, massimo 16 caratteri alfanumerici, senza introdurre un nuovo vincolo univoco. Il testo è codificato in output; tutte le query con input usano parametri.
Non è aggiunta cifratura ai dati gestionali già esistenti: la protezione dei profili utenti resta separata e invariata.

## Tabelle e distribuzione
Associazioni, Tipologia, Soggetti per elenco e creazione; Runts per ricerca facoltativa; Mail e Telefono per la scheda consultabile. Se Runts è vuota, la ricerca non ha risultati e l'inserimento manuale funziona.
Nessuna migrazione SQL né modifica alla procedura di importazione: lo schema non cambia.
Dopo git pull distribuire anagrafica.php, anagrafica_singola.php, runts_ricerca.php, pannello.php, includes/anagrafica.php, includes/ente_nuovo.php, includes/layout.php, assets/anagrafica.css e assets/anagrafica.js.
Conservare i file privati. Non distribuire tests, .github, documentazione o .git. Verificare sul sito reale accesso, elenco con i dati importati e inserimento concordato. Il rilascio GitHub non distribuisce automaticamente su Altervista.
