<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\Test;

/**
 * powerbook.sql auf einem echten Server: Strict Mode und – unter MySQL –
 * sql_require_primary_key (Befunde I01 und I12 aus 3.0).
 */
final class SchemaMysqlTest extends MysqlTestCase
{
    private static string $database;

    public static function setUpBeforeClass(): void
    {
        self::$database = self::databaseName('schema');
    }

    public static function tearDownAfterClass(): void
    {
        self::dropDatabase(self::$database);
    }

    #[Test]
    public function schemaLoadsInStrictModeAndCanBeLoadedTwice(): void
    {
        self::freshDatabase(self::$database);
        $pdo = self::connect(self::$database, true);

        self::loadSqlFile($pdo, POWERBOOK_ROOT . '/powerbook.sql');
        self::loadSqlFile($pdo, POWERBOOK_ROOT . '/powerbook.sql');

        self::assertEqualsCanonicalizing(['pb_admins', 'pb_config', 'pb_entries', 'pb_login_attempts'], self::tables($pdo));
        $stmt = $pdo->query('SELECT table_name, engine, table_collation FROM information_schema.tables WHERE table_schema = DATABASE()');
        self::assertNotFalse($stmt);
        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as [$table, $engine, $collation]) {
            self::assertSame('InnoDB', $engine, (string) $table);
            self::assertSame('utf8mb4_unicode_ci', $collation, (string) $table);
        }

        $stmt = $pdo->query('SELECT * FROM pb_config');
        self::assertNotFalse($stmt);
        $config = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $config);
        self::assertSame(1, (int) $config[0]['id']);
        self::assertSame('Gästebuch', $config[0]['title']);
        self::assertSame('U', $config[0]['release']);
        self::assertSame('pbook.php', $config[0]['guestbook_name']);
        self::assertStringContainsString('(#TIME#) Uhr', (string) $config[0]['design']);
        self::assertStringContainsString("vielen Dank für Ihren Eintrag in unserem Gästebuch!\n\nMit freundlichen Grüßen", (string) $config[0]['thanks']);
        $stmt = $pdo->query('SELECT COUNT(*) FROM pb_admins');
        self::assertNotFalse($stmt);
        self::assertSame(0, (int) $stmt->fetchColumn());
    }

    #[Test]
    public function applicationInsertsWorkWithTheSchema(): void
    {
        self::freshDatabase(self::$database);
        $pdo = self::connect(self::$database, true);
        self::loadSqlFile($pdo, POWERBOOK_ROOT . '/powerbook.sql');

        // wie guestbook.inc.php (alle Spalten außer id) und admins.inc.php
        $pdo->prepare('INSERT INTO pb_entries (name, email, text, date, homepage, ip, status, icon, smilies, statement, statement_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute(['Gast', '', 'Hallo', 2208988800, str_repeat('h', 255), '2001:db8:85a3:8d3:1319:8a2e:370:7348', 'U', '', 'Y', '', '']);
        $pdo->exec("INSERT INTO pb_admins (name, email, password) VALUES ('Anke', 'anke@example.org', 'x')");
        $pdo->exec("INSERT INTO pb_login_attempts (ip, name, time) VALUES ('192.0.2.1', 'Anke', 1790000000)");

        $stmt = $pdo->query('SELECT config, `release`, entries, admins, pw_changed, reset_token FROM pb_admins');
        self::assertNotFalse($stmt);
        $admin = (array) $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['N', 'Y', 'Y', 'N'], [$admin['config'], $admin['release'], $admin['entries'], $admin['admins']]);
        self::assertSame(0, (int) $admin['pw_changed']);
        self::assertNull($admin['reset_token']);
    }
}
