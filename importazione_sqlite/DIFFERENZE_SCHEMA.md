# Tabelle SQLite diverse dalla mappa

Il confronto resta vincolante: la procedura non importa una struttura non supportata e non elimina tabelle per farla coincidere.
L'errore mostra versione della mappa, tabelle mancanti, tabelle aggiuntive, elenco atteso e rilevato. Utenti è esclusa deliberatamente dal confronto.

1. Aggiornare motore.php tramite git pull e WinSCP.
2. Selezionare nuovamente la copia SQLite aggiornata e premere Analizza file.
3. Riportare il messaggio completo per identificare la variante del database. Non cancellare tabelle, non rinominarle e non modificare i dati del sorgente.
4. Se il file è corretto, adattare mappa, trasformazioni e schema MySQL in modo coordinato, con test sulla struttura effettiva. Tabelle non riconosciute non vengono ignorate automaticamente.
Nessuna migrazione o modifica dei dati è inclusa in questo aggiornamento diagnostico.
