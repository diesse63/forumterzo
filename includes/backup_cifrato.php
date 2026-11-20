<?php
declare(strict_types=1);

function backup_identificatore(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function backup_scrivi($stream, string $value): void
{
    if (fwrite($stream, $value) !== strlen($value)) {
        throw new RuntimeException('Scrittura del backup incompleta.');
    }
}

function backup_database(PDO $db, string $path): void
{
    $out = fopen($path, 'wb');
    if (!$out) {
        throw new RuntimeException('File temporaneo SQL non disponibile.');
    }
    try {
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        $tables = $db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        $engines = $db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_KEY_PAIR);
        $routines = $db->query('SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()')->fetchColumn();
        $events = $db->query('SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()')->fetchColumn();
        if ((int) $routines || (int) $events) {
            throw new RuntimeException('Il database contiene routine o eventi. Usare il backup del pannello hosting.');
        }
        backup_scrivi($out, "SET NAMES utf8mb4;\nSET TIME_ZONE='+00:00';\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        $db->exec("SET TIME_ZONE='+00:00'");
        $db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        foreach ($tables as [$table, $type]) {
            if ($type !== 'BASE TABLE' || ($engines[$table] ?? '') !== 'InnoDB') {
                throw new RuntimeException('Backup automatico disponibile per tabelle InnoDB. Per altri oggetti usare il pannello hosting.');
            }
            $quoted = backup_identificatore($table);
            $definition = $db->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM);
            $columns = $db->query('SHOW FULL COLUMNS FROM ' . $quoted)->fetchAll();
            $fields = [];
            $source = [];
            $types = [];
            foreach ($columns as $column) {
                if (preg_match('/(?:VIRTUAL|STORED) GENERATED/i', $column['Extra'])) {
                    continue;
                }
                $field = backup_identificatore($column['Field']);
                if (preg_match('/^(?:geometry|point|linestring|polygon|multi|geometrycollection)/i', $column['Type'])) {
                    throw new RuntimeException('Colonna spaziale rilevata. Usare il backup del pannello hosting.');
                }
                $fields[] = $field;
                $typePrefix = strtolower(strtok($column['Type'], '( '));
                $types[] = $typePrefix;
                $source[] = $typePrefix === 'bit' ? 'CAST(' . $field . ' AS UNSIGNED)' : $field;
            }
            $list = implode(', ', $fields);
            backup_scrivi($out, "\n" . $definition[1] . ";\n");
            $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $query = null;
            try {
                $query = $db->query('SELECT ' . implode(', ', $source) . ' FROM ' . $quoted);
                while ($row = $query->fetch(PDO::FETCH_NUM)) {
                    $values = [];
                    foreach ($row as $i => $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                            continue;
                        }
                        $hex = "UNHEX('" . bin2hex((string) $value) . "')";
                        $values[] = match ($types[$i]) {
                            'bit' => 'CAST(' . $hex . ' AS UNSIGNED)',
                            'json' => 'CONVERT(' . $hex . ' USING utf8mb4)',
                            default => $hex,
                        };
                    }
                    backup_scrivi($out, 'INSERT INTO ' . $quoted . ' (' . $list . ') VALUES (' . implode(', ', $values) . ");\n");
                }
            } finally {
                if ($query) {
                    $query->closeCursor();
                }
                $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
            }
        }
        foreach ($db->query('SHOW TRIGGERS')->fetchAll() as $trigger) {
            $definition = $db->query('SHOW CREATE TRIGGER ' . backup_identificatore($trigger['Trigger']))->fetch();
            $statement = preg_replace('/^CREATE\s+DEFINER\s*=\s*`(?:[^`]|\x60\x60)*`@`(?:[^`]|\x60\x60)*`\s+/i', 'CREATE ', $definition['SQL Original Statement']);
            if (!is_string($statement) || preg_match('/^CREATE\s+TRIGGER\s+/i', $statement) !== 1) {
                throw new RuntimeException('Definizione del trigger non esportabile.');
            }
            $delimiter = 'POIMANAGER_' . bin2hex(random_bytes(10));
            $sqlMode = $db->quote($definition['sql_mode']);
            backup_scrivi($out, "SET SQL_MODE=$sqlMode;\nDELIMITER $delimiter\n$statement$delimiter\nDELIMITER ;\n");
        }
        backup_scrivi($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        if (!fflush($out)) {
            throw new RuntimeException('Scrittura del backup incompleta.');
        }
        $db->commit();
    } finally {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fclose($out);
    }
}

function backup_archivio(PDO $db, string $sql, string $archive, string $password, array $config): void
{
    if (!class_exists(ZipArchive::class) || !defined('ZipArchive::EM_AES_256')
        || !method_exists(ZipArchive::class, 'setEncryptionName')) {
        throw new RuntimeException('Cifratura ZIP AES-256 non disponibile sul server.');
    }
    $version = $config['versione_app'] ?? '';
    if (!is_string($version) || trim($version) === '') {
        throw new RuntimeException('Indicare la versione effettivamente distribuita nella configurazione.');
    }
    $root = dirname(__DIR__);
    foreach (['config.php', 'security.php', '.htaccess'] as $required) {
        if (!is_readable($root . '/private/' . $required)) {
            throw new RuntimeException('File privati richiesti per il ripristino mancanti.');
        }
    }
    if (dc_state($db)) {
        dc_active($db);
    }
    backup_database($db, $sql);
    $zip = new ZipArchive();
    if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Creazione archivio non riuscita.');
    }
    try {
        $names = ['database.sql', 'ripristino.json'];
        if (!$zip->addFile($sql, 'database.sql') || !$zip->addFromString('ripristino.json', json_encode([
            'versione_app' => $version,
            'creato_utc' => gmdate(DATE_ATOM),
            'origine' => $db->query('SELECT DATABASE()')->fetchColumn(),
        ], JSON_THROW_ON_ERROR))) {
            throw new RuntimeException('Archivio incompleto.');
        }
        $directories = ['private'];
        foreach ($config['cartelle_dati'] ?? [] as $directory) {
            if (!is_string($directory) || !preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $directory)
                || in_array($directory, ['private', 'upload'], true)) {
                throw new RuntimeException('Cartella dati aggiuntiva non valida.');
            }
            $directories[] = $directory;
        }
        foreach (array_unique($directories) as $directory) {
            $path = $root . '/' . $directory;
            if (realpath($path) !== $path || !is_dir($path)) {
                throw new RuntimeException('Cartella del backup non disponibile.');
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $file) {
                $name = substr($file->getPathname(), strlen($root) + 1);
                if (in_array($name, ['private/supervisore.php', 'private/recupero_utenti.php'], true)) {
                    continue;
                }
                if ($file->isLink() || !$file->isFile() || !$file->isReadable() || !$zip->addFile($file->getPathname(), $name)) {
                    throw new RuntimeException('Impossibile includere tutti i file richiesti.');
                }
                $names[] = $name;
            }
        }
        foreach ($names as $name) {
            if (!$zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password)) {
                throw new RuntimeException('Cifratura archivio non riuscita.');
            }
        }
    } catch (Throwable $error) {
        @$zip->close();
        throw $error;
    }
    if (!$zip->close()) {
        throw new RuntimeException('Chiusura archivio non riuscita.');
    }
}
