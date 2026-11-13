# Importazione per tabella — mappa 3.0.0

## Procedura attiva

1. Fare backup MySQL e chiudere le altre funzioni che modificano i dati.
2. Aprire Servizi > Importazione SQLite e caricare una copia coerente del .db.
3. La pagina mostra tutte le tabelle del file, conteggi e compatibilità. Gli utenti originali sono esclusi. Tabelle assenti o non mappate non bloccano le tabelle indipendenti.
4. Selezionare una o più tabelle. Le dipendenze FK vengono aggiunte ricorsivamente e ordinate; anche ListaMail richiede MailList, benché il vincolo sia solo logico nello schema storico.
5. Preparare l'anteprima senza scritture. La lista esplicita tutte le tabelle incluse, comprese quelle aggiunte automaticamente, con nuovi, aggiornati e invariati.
6. Confermare entro 30 minuti dal caricamento. La destinazione viene ricontrollata: se è cambiata dopo l'anteprima, questa va rifatta. Tutte le tabelle incluse sono importate in una transazione.
7. Il file resta disponibile per importare altre tabelle; al termine usare Elimina file temporaneo. Non sono effettuate cancellazioni dei dati destinazione.

## Identità e vincoli

Se esiste ID univoco, il record viene cercato solo per quell'ID. Si presuppone che gli ID della fonte originale mantengano la stessa identità nella destinazione; se le due basi hanno riutilizzato ID per soggetti diversi, serve una riconciliazione separata prima di importare.
Una collisione su altra chiave univoca appartenente a un ID diverso blocca l'operazione. Non vengono usati REPLACE o ON DUPLICATE KEY UPDATE indiscriminati.
Se manca ID, si usa una chiave univoca completamente valorizzata; chiavi tutte nulle o assenti bloccano l'aggiornamento ambiguo. Temporanea, senza chiave, non è selezionabile. RUNTS richiede CodiceFiscale valorizzato.
I campi mappati vengono sovrascritti, anche con NULL ammessi. Gli altri record restano intatti. Gli ID non cambiano. I contatori MySQL non vengono abbassati né sostituiti con sqlite_sequence.
Le FK restano sempre abilitate. Errori di FK/CHECK/UNIQUE in conferma causano rollback dell'intero gruppo, comprese modifiche precedenti alle tabelle padre. L'anteprima controlla struttura, identità e collisioni, ma l'applicazione completa dei vincoli avviene anche nelle scritture della transazione.

## Compatibilità delle strutture

Si verificano solo le tabelle selezionate e le dipendenze: il sorgente deve contenere le colonne necessarie alla mappatura. Colonne aggiuntive SQLite non vengono importate. La destinazione deve ancora corrispondere esattamente al contratto MySQL per colonne, tipi, nullabilità, indici, FK e CHECK; tabelle già popolate sono ammesse.
La mappa v3 descrive questi contratti; qualsiasi modifica strutturale MySQL richiede aggiornamento della mappa e della procedura insieme. I vecchi motori/mappa v1-v2 restano per regressione storica; la pagina attiva usa tabelle.php e mappa_v3.json.
Referenti, AltriSoggetti e Agora_old sono visibili ma non selezionabili: per convertirle servono colonne e relazioni effettive. Soggetti assente nel file non viene ricostruita per supposizione. Non si decifrano né si importano Utenti legacy. I riferimenti polimorfici Mail/Telefono non hanno FK nel vecchio schema: la conversione Referenti/AltriSoggetti richiederà una mappatura coordinata dei rispettivi ID, non la fusione automatica.
Una dipendenza sorgente assente o incompatibile blocca il gruppo; non viene omessa silenziosamente.

## Aggiornamento

git pull e sincronizzazione di index.php, motore.php, tabelle.php e mappa_v3.json nella cartella importazione_sqlite, oltre a includes/layout.php. Non richiede migrazioni SQL né modifiche di credenziali. Caricare nuovamente il file dopo l'aggiornamento: i vecchi piani di importazione non sono riutilizzabili.
La gestione attiva sostituisce le precedenti istruzioni di importazione globale in tabelle vuote.

## Test

GitHub Actions verifica importazione padre/figlio, update di righe già presenti, insert, reimportazione idempotente, record destinazione preservati, utenti immutati, collisione su chiave univoca, rollback su FK e blocco di modifiche successive all'anteprima. Restano da provare il file reale dell'utente e il flusso su Altervista.
