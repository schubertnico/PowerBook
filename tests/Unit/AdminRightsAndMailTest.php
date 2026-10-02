<?php

/**
 * PowerBook - PHPUnit Tests
 * Rechte der Admins (A01), Mailtexte (A15), Passwort-Links (A25), CSRF-Meldung (A07)
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdminRightsAndMailTest extends TestCase
{
    private const ANKE = ['id' => 1, 'name' => 'Anke', 'config' => 'Y', 'admins' => 'Y', 'entries' => 'Y', 'release' => 'Y'];
    private const JANNIK = ['id' => 2, 'name' => 'Jannik', 'config' => 'N', 'admins' => 'N', 'entries' => 'Y', 'release' => 'Y'];
    private const OLE = ['id' => 3, 'name' => 'Ole', 'config' => 'N', 'admins' => 'Y', 'entries' => 'N', 'release' => 'N'];
    private const MODERATOR = ['id' => 4, 'name' => 'Mia', 'config' => 'N', 'admins' => 'Y', 'entries' => 'Y', 'release' => 'Y'];
    private const FRAUKE = ['id' => 5, 'name' => 'Frauke', 'config' => 'N', 'admins' => 'N', 'entries' => 'N', 'release' => 'Y'];
    private const KONFIG = ['id' => 6, 'name' => 'Karl', 'config' => 'Y', 'admins' => 'N', 'entries' => 'N', 'release' => 'N'];

    public static function setUpBeforeClass(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/layout.inc.php';
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php';
    }

    // ------------------------------------------------------------------
    // A01: Rechte
    // ------------------------------------------------------------------

    #[Test]
    public function superadminIsAccountOne(): void
    {
        self::assertTrue(pb_admin_is_superadmin(self::ANKE));
        self::assertFalse(pb_admin_is_superadmin(self::OLE));
        self::assertFalse(pb_admin_is_superadmin([]));
    }

    #[Test]
    public function onlySuperadminGrantsAdminRight(): void
    {
        self::assertTrue(pb_admin_can_grant(self::ANKE, 'admins'));
        self::assertFalse(pb_admin_can_grant(self::OLE, 'admins'));
        self::assertFalse(pb_admin_can_grant(self::MODERATOR, 'admins'));
    }

    #[Test]
    public function nobodyGrantsRightsHeLacks(): void
    {
        self::assertFalse(pb_admin_can_grant(self::OLE, 'config'));
        self::assertFalse(pb_admin_can_grant(self::OLE, 'release'));
        self::assertTrue(pb_admin_can_grant(self::MODERATOR, 'release'));
        self::assertTrue(pb_admin_can_grant(self::MODERATOR, 'entries'));
        self::assertFalse(pb_admin_can_grant(self::MODERATOR, 'config'));
        self::assertFalse(pb_admin_can_grant(self::ANKE, 'unbekannt'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, bool}>
     */
    public static function manageCases(): iterable
    {
        yield 'Superadmin ändert Moderator' => [self::ANKE, self::JANNIK, true];
        yield 'Superadmin ändert Konto mit Admins verwalten' => [self::ANKE, self::OLE, true];
        yield 'Superadmin ändert sich nicht hier' => [self::ANKE, self::ANKE, false];
        yield 'Ole ändert Superadmin nicht' => [self::OLE, self::ANKE, false];
        yield 'Ole ändert Jannik nicht (Rechte, die er nicht hat)' => [self::OLE, self::JANNIK, false];
        yield 'Ole ändert Konfig-Konto nicht' => [self::OLE, self::KONFIG, false];
        yield 'Mia ändert Jannik' => [self::MODERATOR, self::JANNIK, true];
        yield 'Mia ändert Frauke' => [self::MODERATOR, self::FRAUKE, true];
        yield 'Mia ändert Ole nicht (Admins verwalten)' => [self::MODERATOR, self::OLE, false];
        yield 'Mia ändert Konfig-Konto nicht' => [self::MODERATOR, self::KONFIG, false];
        yield 'Jannik ohne Recht ändert niemanden' => [self::JANNIK, self::FRAUKE, false];
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $target
     */
    #[Test]
    #[DataProvider('manageCases')]
    public function manageMatrix(array $actor, array $target, bool $expected): void
    {
        self::assertSame($expected, pb_admin_can_manage($actor, $target));
    }

    // ------------------------------------------------------------------
    // Prüfungen
    // ------------------------------------------------------------------

    #[Test]
    public function nameValidation(): void
    {
        self::assertNull(pb_admin_validate_name('Anke Feddersen'));
        self::assertNotNull(pb_admin_validate_name(''));
        self::assertStringContainsString('@', (string) pb_admin_validate_name('anke@example.org'));
        self::assertStringContainsString('100 Zeichen', (string) pb_admin_validate_name(str_repeat('ä', 101)));
        self::assertNull(pb_admin_validate_name(str_repeat('ä', 100)));
    }

    #[Test]
    public function passwordValidationDoesNotTrim(): void
    {
        self::assertNull(pb_admin_validate_new_password(' Möwe 2026 ', ' Möwe 2026 '));
        self::assertStringContainsString('8 Zeichen', (string) pb_admin_validate_new_password('kurz', 'kurz'));
        self::assertStringContainsString('Leerzeichen', (string) pb_admin_validate_new_password('        ', '        '));
        self::assertStringContainsString('nicht überein', (string) pb_admin_validate_new_password('Moewenblick2026', 'Moewenblick2027'));
    }

    // ------------------------------------------------------------------
    // Adressen und Links
    // ------------------------------------------------------------------

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urlCases(): iterable
    {
        yield 'leer' => ['', ''];
        yield 'mit index.php' => ['https://www.example.org/pb_inc/admincenter/index.php', 'https://www.example.org/pb_inc/admincenter/'];
        yield 'ohne Schrägstrich' => ['https://www.example.org/pb_inc/admincenter', 'https://www.example.org/pb_inc/admincenter/'];
        yield 'mit Parametern' => ['https://www.example.org/pb_inc/admincenter/?page=home#x', 'https://www.example.org/pb_inc/admincenter/'];
    }

    #[Test]
    #[DataProvider('urlCases')]
    public function adminUrlIsNormalized(string $input, string $expected): void
    {
        self::assertSame($expected, pb_admin_normalize_admin_url($input));
    }

    #[Test]
    public function passwordLinkUsesNormalizedAdminUrl(): void
    {
        $link = pb_admin_password_link(str_repeat('a', 64), true);

        self::assertSame(
            'https://www.example.org/pb_inc/admincenter/index.php?page=password&token=' . str_repeat('a', 64) . '&welcome=1',
            $link
        );
        self::assertStringNotContainsString('index.php/index.php', $link);
    }

    #[Test]
    public function tokenIsStoredHashedAndExpires(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE pb_admins (id INTEGER PRIMARY KEY, name TEXT, email TEXT, reset_token TEXT, reset_token_expires INTEGER)');
        $pdo->exec("INSERT INTO pb_admins (id, name, email) VALUES (2, 'Jannik', 'jannik@example.org')");

        $token = pb_admin_issue_password_token($pdo, 'pb_admins', 2, 1800);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $stored = $pdo->query('SELECT reset_token, reset_token_expires FROM pb_admins WHERE id = 2')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(hash('sha256', $token), $stored['reset_token']);
        self::assertNotSame($token, $stored['reset_token']);
        self::assertEqualsWithDelta(time() + 1800, (int) $stored['reset_token_expires'], 5);

        $found = pb_admin_find_by_token($pdo, 'pb_admins', $token);
        self::assertNotNull($found);
        self::assertSame('Jannik', $found['name']);

        self::assertNull(pb_admin_find_by_token($pdo, 'pb_admins', hash('sha256', $token)), 'Der gespeicherte Wert taugt nicht als Link.');
        self::assertNull(pb_admin_find_by_token($pdo, 'pb_admins', 'kein-token'));

        $pdo->exec('UPDATE pb_admins SET reset_token_expires = ' . (time() - 1) . ' WHERE id = 2');
        self::assertNull(pb_admin_find_by_token($pdo, 'pb_admins', $token), 'Abgelaufene Links gelten nicht.');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mailTypes(): iterable
    {
        yield 'added' => ['added', 'Ihr Zugang zum AdminCenter: bitte Passwort festlegen'];
        yield 'edited' => ['edited', 'Ihr Zugang zum AdminCenter wurde geändert'];
        yield 'email_changed' => ['email_changed', 'Ihre E-Mail-Adresse im AdminCenter wurde geändert'];
        yield 'password_link' => ['password_link', 'Neues Passwort für das AdminCenter festlegen'];
        yield 'reset' => ['reset', 'Passwort für das AdminCenter zurücksetzen'];
        yield 'password_changed' => ['password_changed', 'Ihr Passwort für das AdminCenter wurde geändert'];
        yield 'deleted' => ['deleted', 'Ihr Zugang zum AdminCenter wurde entfernt'];
    }

    #[Test]
    #[DataProvider('mailTypes')]
    public function mailTextsUseRealUmlautsAndNoSecrets(string $type, string $subject): void
    {
        $message = pb_admin_mail_message($type, self::mailData());

        self::assertNotNull($message);
        self::assertSame($subject, $message['subject']);
        $body = $message['body'];
        self::assertStringStartsWith('Hallo Ole,', $body);
        self::assertStringContainsString('„Gästebuch Ferienwohnung Möwenblick“', $body);
        self::assertStringEndsWith("PowerBook – Gästebuch · https://www.powerscripts.org\n", $body);
        self::assertDoesNotMatchRegularExpression(
            '/\b(fuer|ueber|koennen|muessen|Gaeste|Gaestebuch|Eintraege|loeschen|geaendert|gueltig|zurueck)\b/i',
            $subject . "\n" . $body,
            'Keine Ersatzschreibung für Umlaute.'
        );
        self::assertStringNotContainsStringIgnoringCase('Passwort:', $body, 'Kein Passwort im Klartext.');
        self::assertStringNotContainsString('&amp;', $body, 'Klartext ohne HTML-Escapes.');
        self::assertStringNotContainsString('AUTOMATISCH', $body);
    }

    #[Test]
    public function addedMailContainsWelcomeLinkRightsAndLogin(): void
    {
        $message = pb_admin_mail_message('added', self::mailData());
        self::assertNotNull($message);
        $body = $message['body'];

        self::assertStringContainsString('Anke hat für Sie einen Zugang zum AdminCenter', $body);
        self::assertStringContainsString('mit Ihrem Namen „Ole“ oder Ihrer E-Mail-Adresse ole@example.org an', $body);
        self::assertStringContainsString("- Einträge freischalten: Ja\n", $body);
        self::assertStringContainsString("- Einträge bearbeiten und löschen: Ja\n", $body);
        self::assertStringContainsString("- Konfiguration ändern: Nein\n", $body);
        self::assertStringContainsString("- Admins verwalten: Nein\n", $body);
        self::assertStringContainsString('48 Stunden gültig', $body);
        self::assertStringContainsString('token=' . str_repeat('b', 64), $body);
        self::assertStringContainsString("https://www.example.org/pb_inc/admincenter/\n", $body);
    }

    #[Test]
    public function resetMailSaysThirtyMinutes(): void
    {
        $message = pb_admin_mail_message('reset', self::mailData());
        self::assertNotNull($message);

        self::assertStringContainsString('30 Minuten gültig', $message['body']);
        self::assertStringContainsString('Ihr bisheriges Passwort gilt weiter', $message['body']);
        self::assertStringContainsString('token=' . str_repeat('b', 64), $message['body']);
    }

    #[Test]
    public function emailChangedMailNamesBothAddresses(): void
    {
        $message = pb_admin_mail_message('email_changed', self::mailData());
        self::assertNotNull($message);

        self::assertStringContainsString('Anke hat die E-Mail-Adresse', $message['body']);
        self::assertStringContainsString('bisher: ole.alt@example.org', $message['body']);
        self::assertStringContainsString('neu: ole@example.org', $message['body']);

        $own = pb_admin_mail_message('email_changed', array_merge(self::mailData(), ['by' => 'Ole']));
        self::assertNotNull($own);
        self::assertStringContainsString('Sie haben die E-Mail-Adresse', $own['body']);
    }

    #[Test]
    public function unknownMailTypeAndMissingRecipient(): void
    {
        self::assertNull(pb_admin_mail_message('gibtsnicht', self::mailData()));
        self::assertFalse(sendAdminEmail('added', array_merge(self::mailData(), ['to' => ''])));
        self::assertFalse(sendAdminEmail('gibtsnicht', self::mailData()));
        self::assertFalse(sendAdminEmail('added', array_merge(self::mailData(), ['to' => "x@example.org\r\nBcc: y@example.org"])));
    }

    #[Test]
    public function mailTitleFallsBackToGuestbook(): void
    {
        unset($GLOBALS['config_title']);
        $message = pb_admin_mail_message('deleted', self::mailData());
        self::assertNotNull($message);

        self::assertStringContainsString('von „Gästebuch“ entfernt', $message['body']);
    }

    // ------------------------------------------------------------------
    // A07: CSRF
    // ------------------------------------------------------------------

    #[Test]
    public function csrfFailedMessageIsFriendlyGerman(): void
    {
        self::assertSame('Das Formular war abgelaufen. Bitte senden Sie es erneut ab.', pb_csrf_failed_message());
    }

    #[Test]
    public function inlineMessageCarriesStableId(): void
    {
        $html = pb_admin_inline_message('Gespeichert <sofort>.', 'success');

        self::assertStringContainsString('id="pbMessage"', $html);
        self::assertStringContainsString('alert-success', $html);
        self::assertStringContainsString('&lt;sofort&gt;', $html);
    }

    protected function setUp(): void
    {
        $GLOBALS['config_title'] = 'Gästebuch Ferienwohnung Möwenblick';
        $GLOBALS['config_admin_url'] = 'https://www.example.org/pb_inc/admincenter/index.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['config_title'], $GLOBALS['config_admin_url']);
    }

    // ------------------------------------------------------------------
    // A15: Mailtexte
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function mailData(): array
    {
        return [
            'to' => 'ole@example.org',
            'name' => 'Ole',
            'email' => 'ole@example.org',
            'by' => 'Anke',
            'config' => 'N',
            'admins' => 'N',
            'entries' => 'Y',
            'release' => 'Y',
            'link' => 'https://www.example.org/pb_inc/admincenter/index.php?page=password&token=' . str_repeat('b', 64),
            'old_email' => 'ole.alt@example.org',
            'new_email' => 'ole@example.org',
        ];
    }
}
