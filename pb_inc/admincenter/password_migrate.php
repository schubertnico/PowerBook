<?php

/**
 * PowerBook - Auto-Migration
 *
 * Ergaenzt die powerbook_admin-Tabelle um die Spalten reset_token und
 * reset_token_expires, falls diese noch nicht vorhanden sind (BUG-010).
 * Idempotent und funktioniert mit MySQL und SQLite (Testumgebung).
 */

declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $pb_admin */

if (!isset($pdo) || !isset($pb_admin)) {
    return;
}

try {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    $hasColumn = static function (string $column) use ($pdo, $pb_admin, $driver): bool {
        if ($driver === 'sqlite') {
            $stmt = $pdo->query("PRAGMA table_info({$pb_admin})");
            if ($stmt === false) {
                return false;
            }
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ((string) $row['name'] === $column) {
                    return true;
                }
            }

            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns '
            . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$pb_admin, $column]);

        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$hasColumn('reset_token')) {
        $pdo->exec("ALTER TABLE {$pb_admin} ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL");
    }
    if (!$hasColumn('reset_token_expires')) {
        $pdo->exec("ALTER TABLE {$pb_admin} ADD COLUMN reset_token_expires INT DEFAULT NULL");
    }
} catch (PDOException $e) {
    if (function_exists('logDbError')) {
        logDbError('password_migrate: ' . $e->getMessage());
    }
}
