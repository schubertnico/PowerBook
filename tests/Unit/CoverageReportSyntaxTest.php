<?php

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CoverageReportSyntaxTest extends TestCase
{
    public function testCoverageReportHasValidPhpSyntax(): void
    {
        $output = [];
        $code = 0;
        $cmd = 'php -l ' . escapeshellarg(POWERBOOK_ROOT . '/coverage_report.php') . ' 2>&1';
        exec($cmd, $output, $code);
        self::assertSame(
            0,
            $code,
            'coverage_report.php muss syntaktisch valide sein: ' . implode("\n", $output)
        );
    }
}
