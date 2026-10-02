<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * powerbook.sql – die einzige Quelle des Schemas – und die Helfer, die sie lesen.
 */
final class SchemaFileTest extends TestCase
{
    private string $sql;

    #[Test]
    public function schemaHasTheFourTablesInTheRightOrder(): void
    {
        $statements = pb_setup_schema_statements($this->sql);

        self::assertCount(9, $statements);
        self::assertSame([
            'DROP TABLE IF EXISTS pb_login_attempts',
            'DROP TABLE IF EXISTS pb_entries',
            'DROP TABLE IF EXISTS pb_config',
            'DROP TABLE IF EXISTS pb_admins',
        ], array_slice($statements, 0, 4));
        self::assertSame(PB_SETUP_TABLES, array_keys(pb_setup_schema_tables($this->sql)));
        self::assertStringStartsWith('INSERT INTO pb_config (id, design, thanks_title, thanks) VALUES (1,', $statements[8]);
    }

    #[Test]
    public function everyTableHasPrimaryKeyInnoDbAndExplicitCollation(): void
    {
        $tables = pb_setup_schema_tables($this->sql);

        foreach ($tables as $name => $table) {
            self::assertMatchesRegularExpression('/^\s*PRIMARY KEY \(id\)/m', $table['create'], $name);
            self::assertStringContainsString('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $table['create'], $name);
            self::assertArrayHasKey('id', $table['columns'], $name);
        }
    }

    #[Test]
    public function noDefaultOnTextColumnsAndNoDisplayWidths(): void
    {
        foreach (pb_setup_schema_tables($this->sql) as $name => $table) {
            foreach ($table['columns'] as $column => $definition) {
                if (preg_match('/^`?\w+`?\s+(TEXT|BLOB|MEDIUMTEXT|LONGTEXT|JSON)\b/i', $definition) === 1) {
                    self::assertStringNotContainsStringIgnoringCase('DEFAULT', $definition, $name . '.' . $column);
                }
                self::assertDoesNotMatchRegularExpression('/\bINT\(\d+\)/i', $definition, $name . '.' . $column);
            }
        }
    }

    #[Test]
    public function columnsNeededByVersion31AreThere(): void
    {
        $tables = pb_setup_schema_tables($this->sql);

        foreach (['reset_token', 'reset_token_expires', 'pw_changed'] as $column) {
            self::assertArrayHasKey($column, $tables['pb_admins']['columns']);
        }
        self::assertStringContainsString('BIGINT', $tables['pb_admins']['columns']['reset_token_expires']);
        foreach (['id', 'title', 'mail_from'] as $column) {
            self::assertArrayHasKey($column, $tables['pb_config']['columns']);
        }
        self::assertSame('date BIGINT NOT NULL DEFAULT 0', $tables['pb_entries']['columns']['date']);
        self::assertSame("homepage VARCHAR(255) NOT NULL DEFAULT ''", $tables['pb_entries']['columns']['homepage']);
        self::assertSame("ip VARCHAR(45) NOT NULL DEFAULT ''", $tables['pb_entries']['columns']['ip']);
        self::assertArrayNotHasKey('icq', $tables['pb_entries']['columns']);
        self::assertSame(['idx_status', 'idx_ip'], array_keys($tables['pb_entries']['keys']));
    }

    #[Test]
    public function germanDefaultsWithoutDefaultAdministrator(): void
    {
        $config = pb_setup_schema_tables($this->sql)['pb_config']['columns'];

        self::assertStringContainsString("DEFAULT 'Gästebuch'", $config['title']);
        self::assertStringContainsString("DEFAULT 'd.m.Y'", $config['date']);
        self::assertStringContainsString("DEFAULT 'H:i'", $config['time']);
        self::assertStringContainsString("DEFAULT 'ger1'", $config['language']);
        self::assertStringContainsString("DEFAULT 'U'", $config['release']);
        self::assertStringContainsString("DEFAULT 'Y'", $config['send_email']);
        self::assertDoesNotMatchRegularExpression('/INSERT\s+INTO\s+pb_admins/i', $this->sql);
    }

    #[Test]
    public function everyInsertNamesItsColumns(): void
    {
        foreach (pb_setup_schema_statements($this->sql) as $statement) {
            if (stripos($statement, 'INSERT') === 0) {
                self::assertMatchesRegularExpression('/^INSERT INTO \w+ \([\w, ]+\) VALUES/', $statement);
            }
        }
    }

    #[Test]
    public function standardTextsUseRealUmlautsAndUhr(): void
    {
        $insert = pb_setup_schema_statements($this->sql)[8];
        [$design, $thanksTitle, $thanks] = pb_setup_sql_strings($insert);

        self::assertStringContainsString('(#TIME#) Uhr', $design);
        self::assertStringNotContainsString('(#TIME#)h', $design);
        self::assertSame('Danke für Ihren Eintrag!', $thanksTitle);
        self::assertStringContainsString("\n\nvielen Dank für Ihren Eintrag in unserem Gästebuch!", $thanks);
        self::assertStringEndsWith('Mit freundlichen Grüßen', $thanks);
        self::assertDoesNotMatchRegularExpression('/\b(fuer|Gaeste|Gruesse|Gruessen|ueber)\b/', $this->sql);
    }

    #[Test]
    public function tableNamesCanBeReplaced(): void
    {
        $statements = pb_setup_schema_statements($this->sql, pb_setup_table_names(['pb_admin' => 'gb_admins', 'pb_config' => 'gb_config', 'pb_entries' => 'gb_entries']));

        self::assertSame('DROP TABLE IF EXISTS pb_login_attempts', $statements[0]);
        self::assertSame('DROP TABLE IF EXISTS gb_entries', $statements[1]);
        self::assertStringStartsWith('CREATE TABLE gb_admins (', $statements[4]);
        self::assertStringStartsWith('INSERT INTO gb_config (id,', $statements[8]);
        self::assertStringContainsString('pb-entry-card', $statements[8], 'CSS-Klassen bleiben');
    }

    #[Test]
    public function sqlStringsAreUnescaped(): void
    {
        self::assertSame(["a'b", "Zeile\nZeile", 'C:\\x', 'it\'s'], pb_setup_sql_strings("SELECT 'a''b', 'Zeile\\nZeile', 'C:\\\\x', 'it\\'s'"));
    }

    #[Test]
    public function fixturesOfOldVersionsExist(): void
    {
        foreach (['1.21', '2.0', '3.0'] as $version) {
            $file = POWERBOOK_ROOT . '/tests/Fixtures/schema-' . $version . '.sql';
            self::assertFileExists($file);
            self::assertCount(3, pb_setup_schema_tables((string) file_get_contents($file)), $version);
        }
    }

    protected function setUp(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/setup.inc.php';
        $this->sql = (string) file_get_contents(POWERBOOK_ROOT . '/powerbook.sql');
    }
}
