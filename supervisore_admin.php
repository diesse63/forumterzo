<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/supervisore.php';

supervisore_https();
if (!is_file(__DIR__ . '/private/supervisore.php')) {
    http_response_code(404);
    exit;
}
if (!defined('POIMANAGER_INTERNAL')) {
    define('POIMANAGER_INTERNAL', true);
}
$message = '';
$name = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $config = supervisore_config();
        $token = (string) ($_POST['codice'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $adminPassword = (string) ($_POST['password_admin'] ?? '');
        $deadline = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', (string) ($config['scadenza_utc'] ?? ''), new DateTimeZone('UTC'));
        if (!$deadline || $deadline->getTimestamp() <= time()
            || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1
            || !hash_equals($config['token_hash'], hash('sha256', $token))
            || !supervisore_password($password, $config)) {
            throw new RuntimeException('Autorizzazione di emergenza non valida o scaduta.');
        }
        if (hash_equals($password, $adminPassword)) {
            throw new RuntimeException('Scegliere una password diversa per il nuovo amministratore.');
        }
        password_policy($adminPassword);
        $db = poimanager_db();
        $engines = $db->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('Utenti','SupervisoreRecuperi')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (($engines['Utenti'] ?? '') !== 'InnoDB' || ($engines['SupervisoreRecuperi'] ?? '') !== 'InnoDB') {
            throw new RuntimeException('Recupero non disponibile: il referente deve verificare le tabelle utenti.');
        }
        // Il nome casuale evita collisioni anche con profili storici illeggibili.
        $name = 'admin_rec_' . bin2hex(random_bytes(16));
        $index = user_index($name);
        $profile = user_encrypt(['nome' => $name, 'email' => '']);
        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $db->beginTransaction();
        try {
            $q = $db->prepare('INSERT INTO SupervisoreRecuperi (TokenHash, UtenteID, CreazioneUTC) VALUES (?, 0, UTC_TIMESTAMP())');
            $q->execute([$config['token_hash']]);
            $q = $db->prepare("INSERT INTO Utenti (NomeIndice, ProfiloCifrato, PasswordHash, Ruolo, Attivo) VALUES (?, ?, ?, 'admin', 1)");
            $q->execute([$index, $profile, $passwordHash]);
            $id = $db->lastInsertId();
            $q = $db->prepare('UPDATE SupervisoreRecuperi SET UtenteID=? WHERE TokenHash=?');
            $q->execute([$id, $config['token_hash']]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    } catch (Throwable $error) {
        $name = '';
        $message = $error instanceof PDOException
            ? 'Creazione non confermata. Il codice potrebbe essere già stato utilizzato. Il referente deve verificare il registro prima di riprovare.'
            : ($error instanceof RuntimeException ? $error->getMessage() : 'Creazione non riuscita.');
        error_log('POImanager: richiesta supervisore non completata (' . get_class($error) . ').');
    }
}
?>
<!doctype html>
<html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Amministratore di emergenza</title><link rel="stylesheet" href="/assets/layout.css"></head>
<body><main>
<h1>Amministratore di emergenza</h1>
<?php if ($message !== ''): ?><p role="alert"><?= uh($message) ?></p><?php endif; ?>
<?php if ($name !== ''): ?>
<p>Account creato. Nome di accesso <strong><?= uh($name) ?></strong>. Annota il nome e prova l’accesso con la password appena scelta.</p>
<p><a href="/login.php">Accedi a POImanager</a></p>
<?php else: ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= uh(csrf()) ?>">
<label>Password di emergenza <input type="password" name="password" required maxlength="72" autocomplete="off"></label>
<label>Codice monouso <input type="password" name="codice" required minlength="64" maxlength="64" autocomplete="off"></label>
<label>Password del nuovo amministratore <input type="password" name="password_admin" required minlength="7" maxlength="72" autocomplete="new-password"></label>
<button>Crea amministratore</button>
</form>
<?php endif; ?>
</main></body></html>
