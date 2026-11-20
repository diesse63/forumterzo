<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/dati.php';
require_once __DIR__ . '/includes/supervisore.php';
require_once __DIR__ . '/includes/backup_cifrato.php';

supervisore_https();
$user = current_user();
if (!$user || $user['Ruolo'] !== 'admin') {
    http_response_code(403);
    exit('Accesso riservato agli amministratori.');
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sql = $archive = null;
    try {
        check_csrf();
        $config = supervisore_config();
        $password = (string) ($_POST['password'] ?? '');
        if (!supervisore_password($password, $config)) {
            throw new RuntimeException('Password di emergenza non valida.');
        }
        $temp = realpath(sys_get_temp_dir());
        $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if (!$temp || !$web || $temp === $web || str_starts_with($temp, $web . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Cartella temporanea non pubblica non disponibile.');
        }
        $sql = tempnam($temp, 'poi-sql-');
        $archive = tempnam($temp, 'poi-zip-');
        register_shutdown_function(static function () use ($sql, $archive): void {
            foreach ([$sql, $archive] as $file) {
                if (is_string($file) && is_file($file)) {
                    @unlink($file);
                }
            }
        });
        if (!$sql || !$archive || dirname($sql) !== $temp || dirname($archive) !== $temp
            || !chmod($sql, 0600) || !chmod($archive, 0600)) {
            throw new RuntimeException('Spazio temporaneo protetto non disponibile.');
        }
        session_write_close();
        backup_archivio(poimanager_db(), $sql, $archive, $password, $config);
        unlink($sql);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="poimanager-' . gmdate('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($archive));
        readfile($archive);
        exit;
    } catch (Throwable $error) {
        $message = $error instanceof RuntimeException && !$error instanceof PDOException
            ? $error->getMessage() : 'Esportazione non riuscita. Nessun archivio è stato consegnato.';
        error_log('POImanager: esportazione non completata (' . get_class($error) . ').');
    }
}
page_start('Backup cifrato', 'Scarica una copia per il ripristino di POImanager.', ['Servizi', 'Backup cifrato']);
?>
<?php if ($message !== ''): ?><p role="alert"><?= uh($message) ?></p><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= uh(csrf()) ?>">
<label>Password di emergenza <input type="password" name="password" required maxlength="72" autocomplete="off"></label>
<button>Scarica backup cifrato</button>
</form>
</main></body></html>
