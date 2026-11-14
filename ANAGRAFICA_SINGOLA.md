# Anagrafica singola e gestione anagrafica PHP

## Pagine e funzioni

- `anagrafica.php`: unico ingresso dal menu Anagrafiche > Anagrafica e dal pannello. Elenco a card su desktop e mobile, ricerca, filtri e inserimento enti. Ogni card mostra denominazione, CF, tipologia, incarichi, altre informazioni presenti, email/PEC, telefoni e persone LR/AS con relativi recapiti.
- `anagrafica_singola.php?id=...`: modifica denominazione, codice fiscale, tipologia e incarichi ETS; CRUD persone LR/AS; email/PEC e telefoni di enti e persone; predefinito unico per titolare; confronto RUNTS e importazione selettiva Cesvot.
- Le persone di entrambe le tipologie LR e AS si gestiscono anche dalle modali della relativa card. Non ci sono più dashboard intermedie o pagine separate per Referenti e Altri soggetti.

## Card e modali

Ogni card ha quattro sezioni con modale dedicata: Email / PEC, Recapiti telefonici, Legali rappresentanti, Altri soggetti. Il riquadro RUNTS resta separato e apre il confronto in una modale distinta; Dati ente apre i dati principali. Apri scheda resta disponibile come percorso completo e come alternativa senza JavaScript.

Le modali riutilizzano anagrafica_singola.php con il parametro modale (Mail, Telefono, LR, AS, runts oppure dati). Caricamento e salvataggi richiedono la stessa sessione, gli stessi permessi e il CSRF della scheda. Solo questa vista consente l'incorporamento dallo stesso sito; le altre pagine mantengono la protezione precedente. Il dialog si chiude con pulsante, Escape o clic sullo sfondo, chiedendo conferma per bozze non salvate e attendendo i salvataggi in corso. I messaggi tra modale ed elenco verificano origine e finestra mittente.

Dopo il salvataggio la card viene aggiornata mantenendo filtri e pagina. I recapiti della pagina vengono caricati in gruppo; il RUNTS viene letto una volta per la pagina, senza una scansione per ogni card. Le tabelle e la mappatura SQLite non cambiano.

Le pagine sono PHP con il layout comune Gestione. Stili e JavaScript sono locali, senza Tailwind, DaisyUI o vecchie API Node. Anche senza JavaScript i moduli funzionano con POST e redirect; con JavaScript i salvataggi indipendenti aggiornano le sezioni senza perdere una bozza nei dati principali. Uscendo con dati principali non salvati, il browser chiede conferma. In caso di errore di connessione verificare l'esito prima di ripetere.

## File di riferimento verificati

| Riferimento originale | Disponibilità / sostituzione |
|---|---|
| dashboard_anagrafica.html, anagrafica_singola.html | Allegati analizzati |
| anagrafica.html | Presente nello ZIP precedente; già convertito in anagrafica.php |
| gestione_referenti.html, gestione_altri.html | Presenti in public.zip; entrambe le tipologie si gestiscono nella scheda PHP del relativo ente |
| assets/js/auth.js, assets/js/ui.js, assets/js/config.js | Presenti in public.zip; autenticazione e componenti sostituiti da quelli PHP comuni |
| DaisyUI e Tailwind CDN | Sostituiti dagli stili locali, compatibili con la CSP attuale |
| /api/tipologie, /api/associazioni, /api/referenti, /api/altri-soggetti, /api/mail, /api/telefono | Implementazione server originale non fornita. Funzioni ricostruite sulle tabelle attuali con moduli PHP |
| /api/runts/check, /api/runts/align, /api/cesvot/check, /api/cesvot/import | Implementazione server originale non fornita. Confronti espliciti descritti sotto, senza supporre regole del vecchio server |

Non occorrono altri file per eseguire questa versione. Per confrontare esattamente eventuali algoritmi del vecchio server occorre il suo codice delle API: non è contenuto nello ZIP public.

## Tabelle, cifratura e vincoli

Si utilizzano Associazioni, Tipologia, Soggetti, Mail, Telefono, Runts, CesvotMail e CesvotTelefono. ListaMail viene coinvolta nelle cancellazioni dei recapiti tramite la FK esistente. AgoraPresenti e Runts.IDAssociazione vengono controllati prima di eliminare un ente. Le copie storiche in email_destinatari/email_invii non vengono alterate.

Nessuna modifica strutturale: contratto cifratura e mappa SQLite v4 restano validi. I campi riservati passano dalle funzioni dc_*; le query dirette operano su ID, collegamenti, indicatori o cancellazioni. La scheda si blocca durante la migrazione e se manca la chiave dei dati. Nessuna chiave viene generata o modificata da queste pagine.

Ogni salvataggio usa una transazione con blocco della riga ente e verifica della revisione dell'intera scheda. Una scheda obsoleta viene rifiutata. Anche le proposte RUNTS/Cesvot hanno un'impronta verificata al salvataggio: cambiamenti del sorgente richiedono un nuovo confronto. Non è possibile modificare persone/recapiti di altri enti passando un ID estraneo. Il titolare di un recapito esistente non si può cambiare; creare un nuovo recapito sul titolare corretto.

Eliminare una persona rimuove i suoi recapiti e le relative iscrizioni alle liste; eliminare un ente rimuove persone e recapiti soltanto se non ci sono vincoli che lo impediscono. Gli errori annullano l'intera operazione.

## Permessi

Lettura della scheda: pannello associazioni. Ogni POST controlla il permesso di scrittura sul pannello interessato, oltre all'accesso alla scheda e al CSRF:

| Operazione | Scrittura richiesta |
|---|---|
| Dati principali | associazioni |
| Persona | soggetti; anche mail se si aggiunge un'email insieme alla persona |
| Email / telefono | mail / telefono |
| Elimina email | mail e maillist, perché può rimuovere iscrizioni |
| Elimina persona | soggetti, mail, telefono, maillist |
| Elimina ente | associazioni, soggetti, mail, telefono, maillist |
| Allinea RUNTS | associazioni e runts; anche soggetti per aggiungere una persona |
| Importa Cesvot | mail oppure telefono |

Admin e ghost autorizzato hanno tutti i poteri secondo le regole esistenti; nessun permesso viene assegnato automaticamente agli user. Le azioni non autorizzate sono nascoste e vengono negate anche inviando richieste manuali.

## RUNTS e Cesvot

RUNTS: prima confronto per CF normalizzato; se l'ente non ha CF, confronto per denominazione esatta. Nessun abbinamento automatico ambiguo. La ricerca manuale usa il RUNTS già caricato. Ogni candidato mostra denominazione e CF attuali/proposti; conferma esplicita prima di sostituirli. La persona è facoltativa e il ruolo LR/AS va scelto: non viene attribuito automaticamente il ruolo di rappresentante. Le persone esistenti non vengono sovrascritte. Il confronto segnala anche l'eventuale rappresentante RUNTS non presente tra i LR, senza attribuirgli automaticamente tale ruolo.

Cesvot: confronto per denominazione esatta ignorando maiuscole e spazi esterni; propone soltanto recapiti validi non presenti sull'ente. L'utente sceglie i record; il server ricontrolla provenienza e duplicati. Le tabelle sorgenti restano invariate. Tabelle vuote significano nessuna proposta, non un errore. Nessuna consultazione di siti esterni.

## Distribuzione

1. Fare il normale backup del database e dei file privati.
2. `git pull --ff-only` sul PC.
3. Caricare con WinSCP: anagrafica.php, anagrafica_singola.php, pannello.php; includes/layout.php, includes/anagrafica_scheda.php, includes/anagrafica_scheda_sezioni.php, includes/anagrafica_cards.php, includes/anagrafica_cards_view.php; assets/anagrafica.css, assets/anagrafica.js, assets/anagrafica_scheda.css, assets/anagrafica_scheda.js e assets/anagrafica_modali.js.
4. Eliminare manualmente dal server i vecchi file `gestione_referenti.php`, `gestione_altri.php`, `dashboard_anagrafica.php` e il componente non più utilizzato `includes/anagrafica_persone.php`. Nessuna migrazione SQL. Non caricare tests o documentazione nel sito. Non eliminare o sovrascrivere private: disattivare l'eliminazione automatica WinSCP, soprattutto per security.php e dati_security.php.
5. Aprire Anagrafiche > Anagrafica e scegliere un ente dall'elenco. Se la migrazione della cifratura è ancora in corso, completarla dalla procedura esistente prima di usare le anagrafiche.

## Verifiche automatiche

Test di dominio su database effimero prima e dopo la cifratura: modifiche, ID estranei, duplicati, recapito predefinito, revisioni obsolete, rollback persona/email e RUNTS, selezione Cesvot, FK e cancellazioni collegate. Browser desktop/mobile prima e dopo la cifratura: accesso diretto all’anagrafica, scheda, salvataggi indipendenti, bozza principale conservata, persone/recapiti, persistenza, CSRF e rifiuto dei POST senza permessi. Nessuna modifica al database Altervista e nessuna email reale nei test.


Le prove browser delle card coprono lettura di tutti i dati, quattro modali, RUNTS separato, aggiornamento della card, scarto delle bozze, mobile e accesso in sola lettura prima e dopo cifratura.
