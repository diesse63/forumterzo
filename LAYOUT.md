# Layout comune — regola generale fissa

Queste regole valgono per tutta l'applicazione salvo indicazioni specifiche dell'utente.

| Elemento | Dimensione | Stile |
|---|---:|---|
| Nome applicazione Gestione nella barra superiore | 30px | Bold |
| Voce/sottovoce selezionata sotto Gestione | 15px | Corsivo |
| Voci del menu bar e dropdown | 15px | Corsivo |
| Intestazione della pagina | 25px | Bold |
| Descrizione sintetica sotto il titolo | 15px | Corsivo |

Il menu bar ha voci principali e sottovoci a tendina. Un clic esterno o la selezione di un collegamento chiude le tendine. Aprire una tendina chiude le altre; Escape le chiude e restituisce il focus al pulsante. ArrowDown apre e porta al primo collegamento; Tab segue la normale navigazione dei collegamenti.
Su mobile il menu va a capo mantenendo le dimensioni prescritte.

## Componente unico

Le pagine includono includes/auth.php, verificano i permessi e chiamano page_start(). La funzione usa includes/layout.php, assets/layout.css e assets/menu.js. Non creare intestazioni, CSS o menu duplicati.
Il registro in layout.php definisce titolo, descrizione e percorso delle pagine esistenti. Per nuove pagine passare descrizione e contesto oppure aggiungerle al registro:

```php
require_once __DIR__ . '/includes/auth.php';
$utente = require_access('associazioni', 'lettura');
page_start('Associazioni', 'Consulta e aggiorna le associazioni registrate.', ['Anagrafiche', 'Associazioni']);
// Contenuto della pagina
echo '</main></body></html>';
```

Il menu mostra i collegamenti implementati consentiti all'utente. Servizi > Utenti apre /utenti.php; Servizi include anche Importazione SQLite e Cambia password secondo i permessi. Le future schermate vanno collegate al menu centrale con il controllo autorizzativo appropriato.
Le risposte tecniche senza interfaccia (API, redirect, download, test di connessione testuale) non producono il layout HTML.

## Distribuzione

Aggiornare il PC con git pull e sincronizzare con WinSCP includes, assets e pagine modificate. Non cambiano tabelle, dati o credenziali. Nessuna migrazione SQL necessaria per questo aggiornamento. Non caricare documentazione, AGENTS.md, tests o .github sul sito pubblico.
