<?php

declare(strict_types=1);

namespace PowerBook\Tests\Integration;

final class GuestbookLengthValidationTest extends GuestbookTestCase
{
    public function testGuestbookRejectsOverlongName(): void
    {
        foreach (['preview', 'save'] as $action) {
            $html = $this->render([], $this->form($action, ['name' => str_repeat('Ö', 101)]));
            self::assertStringContainsString('Der Name darf höchstens 100 Zeichen lang sein (jetzt 101).', $html, $action);
        }
        self::assertStringContainsString('id="pbEntryPreview"', $this->render([], $this->form('preview', ['name' => str_repeat('Ö', 100)])));
        self::assertSame(0, $this->countEntries());
    }

    public function testGuestbookRejectsOverlongText(): void
    {
        foreach (['preview', 'save'] as $action) {
            $html = $this->render([], $this->form($action, ['text' => str_repeat('x', 5001)]));
            self::assertStringContainsString('Der Text darf höchstens 5.000 Zeichen lang sein (jetzt 5.001).', $html, $action);
        }
        // B30: Zeilenumbrüche (CRLF) zählen als ein Zeichen
        $text = implode('
', array_fill(0, 50, str_repeat('x', 99)));
        self::assertStringContainsString('id="pbEntryPreview"', $this->render([], $this->form('preview', ['text' => $text])));
        self::assertSame(0, $this->countEntries());
    }

    public function testAdminEditValidatesLengths(): void
    {
        $source = file_get_contents(POWERBOOK_ROOT . '/pb_inc/admincenter/edit.inc.php');
        self::assertNotFalse($source);
        self::assertMatchesRegularExpression(
            '/mb_strlen\s*\(\s*\$edit_name\s*\)/',
            $source,
            'edit.inc.php muss mb_strlen für edit_name prüfen.'
        );
    }
}
