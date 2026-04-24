<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SessionFixationTest extends TestCase
{
    public function testLoginFlowContainsSessionRegenerate(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/index.php');
        self::assertNotFalse($source);

        self::assertMatchesRegularExpression(
            '/session_regenerate_id\s*\(\s*true\s*\)/',
            $source,
            'Der Login-Handler muss session_regenerate_id(true) nach erfolgreichem Login aufrufen.'
        );
    }
}
