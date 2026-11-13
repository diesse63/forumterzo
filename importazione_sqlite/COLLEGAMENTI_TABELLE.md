# Elenco tabelle e collegamenti

La pagina mostra l'unione delle tabelle realmente presenti nel file SQLite e delle tabelle previste dalla mappa MySQL. Le assenti hanno conteggio — e selezione disattivata: non vengono create, popolate o ricostruite per supposizione.
Soggetti resta quindi visibile anche nella variante che contiene Referenti e AltriSoggetti separati.

Per ogni tabella sono mostrati collegamenti in uscita e in ingresso, con campi e origine:
- FK SQLite: lette con PRAGMA foreign_key_list anche per tabelle non mappate.
- FK MySQL prevista: descritta dalla mappa; il contratto reale è verificato in anteprima, non presentato come una lettura live del database.
- Vincolo logico applicato: per esempio ListaMail.IDListaMail verso MailList.ID.

Mail e Telefono segnalano inoltre il riferimento polimorfico TipoSoggetto + IDSoggetto, non dichiarato da una FK. Non viene inventata la destinazione nella variante legacy.
Se non ci sono collegamenti dichiarati viene indicato esplicitamente. Ciò non esclude riferimenti impliciti ancora da analizzare nel codice originale.
L'inventario in sessione viene riletto dal file già caricato, così le nuove colonne sono disponibili senza obbligare a un nuovo caricamento. Nessun dato importato o modificato da questa visualizzazione.

Distribuire index.php e tabelle.php nella cartella importazione_sqlite dopo git pull. Nessuna migrazione SQL richiesta.
