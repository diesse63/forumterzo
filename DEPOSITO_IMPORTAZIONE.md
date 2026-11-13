# Deposito importazione

Il deposito standard è importazione_sqlite/data. Il codice lo crea, se manca, e crea il relativo .htaccess con Require all denied. Non sovrascrive regole già presenti. Prima di acquisire database effettua comunque la verifica HTTPS 403.
La cartella standard viene verificata direttamente e non dipende dalla presenza di DOCUMENT_ROOT. Per depositi esterni personalizzati resta richiesta una radice web verificabile.

In private/config.php utilizzare:
```php
'private_dir' => dirname(__DIR__) . '/importazione_sqlite/data',
```
Sono riconosciuti anche i valori relativi importazione_sqlite/data e data. La configurazione privata non viene sovrascritta dagli aggiornamenti.

Se manca il permesso di creare data, crearla con WinSCP sotto importazione_sqlite e caricare data/.htaccess. Se PHP non può scrivere, verificare proprietario, permessi e spazio disponibile senza rendere indiscriminatamente scrivibile il sito.
Per aggiornare: git pull e sincronizzazione di importazione_sqlite/deposito.php. Nessuna migrazione SQL.
