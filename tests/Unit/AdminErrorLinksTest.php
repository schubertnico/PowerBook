<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminErrorLinksTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function fileProvider(): array
    {
        return [
            'edit.inc.php' => [POWERBOOK_ROOT . '/pb_inc/admincenter/edit.inc.php'],
            'statement.inc.php' => [POWERBOOK_ROOT . '/pb_inc/admincenter/statement.inc.php'],
        ];
    }

    #[DataProvider('fileProvider')]
    public function testIdUnknownErrorDoesNotUseHistoryBack(string $file): void
    {
        $source = file_get_contents($file);
        self::assertNotFalse($source);

        // Verify the whole file no longer contains history.back() in the ID-unknown path.
        self::assertStringNotContainsString(
            'javascript:history.back()',
            $source,
            sprintf('%s darf kein javascript:history.back() mehr verwenden.', basename($file))
        );
    }

    #[DataProvider('fileProvider')]
    public function testIdUnknownErrorLinksToEntries(string $file): void
    {
        $source = file_get_contents($file);
        self::assertNotFalse($source);
        // Find the message that contains 'ID unbekannt' and verify link to entries page
        self::assertMatchesRegularExpression(
            '/ID unbekannt.*?\?page=entries/s',
            $source,
            sprintf('%s muss bei ID-unbekannt auf ?page=entries verlinken.', basename($file))
        );
    }
}
