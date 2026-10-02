<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Grundlage für Tests gegen einen echten MySQL- bzw. MariaDB-Server.
 *
 * Läuft nur, wenn PB_TEST_DB_HOST gesetzt ist (in der CI der MySQL-Dienst),
 * sonst werden die Tests übersprungen. Weitere Variablen: PB_TEST_DB_PORT
 * (3306), PB_TEST_DB_USER (root), PB_TEST_DB_PASSWORD (leer) und
 * PB_TEST_DB_PREFIX (pbtest_) für die Namen der Testdatenbanken. Der Benutzer
 * muss Datenbanken und Benutzer anlegen dürfen.
 */
abstract class MysqlTestCase extends TestCase
{
    /** Sitzungsvariablen, die Hoster mit verwalteten MySQL-Diensten oft setzen. */
    private const string STRICT_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

    protected function setUp(): void
    {
        parent::setUp();
        if (self::credentials() === null) {
            self::markTestSkipped('Kein Datenbankserver für Tests (PB_TEST_DB_HOST ist nicht gesetzt).');
        }
    }

    /**
     * @return array{host: string, port: int, user: string, password: string}|null
     */
    protected static function credentials(): ?array
    {
        $host = getenv('PB_TEST_DB_HOST');
        if ($host === false || $host === '') {
            return null;
        }

        return [
            'host' => $host,
            'port' => (int) (getenv('PB_TEST_DB_PORT') ?: 3306),
            'user' => (string) (getenv('PB_TEST_DB_USER') ?: 'root'),
            'password' => (string) getenv('PB_TEST_DB_PASSWORD'),
        ];
    }

    /**
     * Name einer Testdatenbank, z. B. pbtest_update.
     */
    protected static function databaseName(string $suffix): string
    {
        $prefix = (string) (getenv('PB_TEST_DB_PREFIX') ?: 'pbtest_');

        return $prefix . $suffix;
    }

    /**
     * Verbindung ohne Datenbank (zum Anlegen und Löschen).
     */
    protected static function server(): PDO
    {
        $c = self::credentials();
        self::assertNotNull($c);

        return new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']), $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    /**
     * Legt eine leere Datenbank an (eine vorhandene wird vorher gelöscht).
     */
    protected static function freshDatabase(string $name): void
    {
        $server = self::server();
        $server->exec('DROP DATABASE IF EXISTS `' . $name . '`');
        $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    protected static function dropDatabase(string $name): void
    {
        if (self::credentials() !== null) {
            self::server()->exec('DROP DATABASE IF EXISTS `' . $name . '`');
        }
    }

    /**
     * Verbindung zu einer Datenbank mit Strict Mode und – unter MySQL –
     * sql_require_primary_key, wie bei vielen Hostern.
     */
    protected static function connect(string $database, bool $requirePrimaryKey = true): PDO
    {
        $c = self::credentials();
        self::assertNotNull($c);
        $pdo = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $database), $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET SESSION sql_mode = '" . self::STRICT_MODE . "'");
        self::setRequirePrimaryKey($pdo, $requirePrimaryKey);

        return $pdo;
    }

    /**
     * sql_require_primary_key gibt es nur in MySQL ab 8.0.13.
     */
    protected static function setRequirePrimaryKey(PDO $pdo, bool $on): void
    {
        if (self::isMariaDb($pdo)) {
            return;
        }

        try {
            $pdo->exec('SET SESSION sql_require_primary_key = ' . ($on ? '1' : '0'));
        } catch (PDOException) {
            // ältere Server kennen die Variable nicht
        }
    }

    protected static function isMariaDb(PDO $pdo): bool
    {
        $stmt = $pdo->query('SELECT VERSION()');

        return $stmt !== false && stripos((string) $stmt->fetchColumn(), 'mariadb') !== false;
    }

    /**
     * Spielt eine SQL-Datei ein (Anweisungen wie in powerbook.sql getrennt).
     */
    protected static function loadSqlFile(PDO $pdo, string $file): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/setup.inc.php';
        foreach (pb_setup_schema_statements((string) file_get_contents($file)) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    protected static function columns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
        self::assertNotFalse($stmt);

        return array_map(static fn (array $row): string => (string) $row['Field'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    protected static function columnType(PDO $pdo, string $table, string $column): string
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . "` LIKE '" . $column . "'");
        self::assertNotFalse($stmt);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? strtolower((string) $row['Type']) : '';
    }

    /**
     * @return list<string>
     */
    protected static function tables(PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');
        self::assertNotFalse($stmt);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Neues temporäres Verzeichnis für Sperrdatei, mysql.inc.php usw.
     */
    protected static function tempDir(string $name): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pbtest-' . $name . '-' . getmypid();
        self::removeDir($dir);
        mkdir($dir . DIRECTORY_SEPARATOR . 'pb_inc', 0o777, true);

        return $dir;
    }

    protected static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
