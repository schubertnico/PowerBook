<?php

/**
 * PowerBook - PHPUnit Tests
 * Seiten „Admins verwalten“, „Mein Konto“, „Passwort vergessen“ und „Konfiguration“
 *
 * Bindet die Seiten mit einer eigenen SQLite-Datenbank im Schema von 3.1 ein.
 * Nach erfolgreichem Speichern leiten die Seiten weiter (PbAdminRedirect aus
 * layout.inc.php); render() fängt das ab und merkt sich das Ziel.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AdminAccountPagesTest extends TestCase
{
    private const PASSWORD = 'Moewenblick2026';

    private static PDO $pdo;

    private static string $hash;

    private ?string $redirect = null;

    public static function setUpBeforeClass(): void
    {
        self::$hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);
    }

    // ==================================================================
    // Admins verwalten (A01, A15, A22, A28)
    // ==================================================================

    #[Test]
    public function adminsPageDeniedWithoutRight(): void
    {
        $html = $this->render('admins.inc.php', [], [], $this->session(2));

        self::assertStringContainsString('keine Berechtigung', $html);
        self::assertStringNotContainsString('pbAdminAddForm', $html);
    }

    #[Test]
    public function adminsPageShowsSuperadminWithoutForm(): void
    {
        $html = $this->render('admins.inc.php', [], [], $this->session(1));

        self::assertStringContainsString('id="pbAdmin1"', $html);
        self::assertStringContainsString('Superadmin – hat immer alle Rechte und lässt sich nicht löschen', $html);
        self::assertStringNotContainsString('id="pb_admin_perm_config_1"', $html);
        self::assertStringNotContainsString('id="pbAdminSave1"', $html);
        self::assertStringContainsString('id="pbAdminSave2"', $html);
        self::assertStringContainsString('id="pbAdminDelete2"', $html);
        self::assertStringContainsString('id="pb_admin_perm_admins_2"', $html);
        self::assertStringContainsString('id="pb_admin_link_2"', $html);
        self::assertStringContainsString('id="pbAdminAddSubmit"', $html);
        self::assertStringContainsString('Zurzeit gibt es <b id="pbAdminCount">3</b> Admins', $html);
        self::assertStringNotContainsString('pb_admin_pw1_', $html, 'Passwörter werden nicht mehr eingetippt.');
        self::assertStringNotContainsString('Speichern / Update', $html);
        self::assertStringNotContainsString('SuperAdmin', $html);
    }

    #[Test]
    public function oleCannotTouchSuperadminOrRicherAccounts(): void
    {
        $html = $this->render('admins.inc.php', [], [], $this->session(3));
        self::assertStringNotContainsString('id="pbAdminSave1"', $html);
        self::assertStringNotContainsString('id="pbAdminSave2"', $html, 'Jannik hat Rechte, die Ole nicht hat.');
        self::assertStringContainsString('Name, E-Mail-Adresse und Passwort ändert nur der Superadmin selbst.', $html);

        $html = $this->render('admins.inc.php', [
            'action' => 'save',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '1',
            'edit_name' => 'Anke',
            'edit_email' => 'ole.privat@example.org',
        ], [], $this->session(3));

        self::assertStringContainsString('Den Superadmin ändert nur er selbst', $html);
        self::assertSame('anke@example.org', $this->column(1, 'email'));
    }

    #[Test]
    public function addAdminSendsLinkAndNeverGrantsMoreThanActorHas(): void
    {
        // Mia: Admins verwalten + freischalten, nicht Konfiguration
        self::$pdo->exec("INSERT INTO pb_admins (id, name, email, password, config, \"release\", entries, admins) VALUES (4, 'Mia', 'mia@example.org', 'x', 'N', 'Y', 'N', 'Y')");

        $html = $this->render('admins.inc.php', [
            'action' => 'add',
            'csrf_token' => generateCsrfToken(),
            'add_name' => 'Frauke',
            'add_email' => 'frauke@example.org',
            'add_release' => 'Y',
            'add_config' => 'Y',
            'add_admins' => 'Y',
            'add_entries' => 'Y',
        ], [], $this->session(4));

        $row = self::$pdo->query("SELECT * FROM pb_admins WHERE name = 'Frauke'")->fetch();
        self::assertIsArray($row);
        self::assertSame(['Y', 'N', 'N', 'N'], [$row['release'], $row['entries'], $row['config'], $row['admins']]);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $row['reset_token']);
        self::assertEqualsWithDelta(time() + 172800, (int) $row['reset_token_expires'], 10);
        self::assertFalse(password_verify('', (string) $row['password']));

        $this->assertSavedOrLinkShown($html, 'Der Zugang für Frauke ist angelegt');
    }

    #[Test]
    public function addAdminValidatesAndKeepsInput(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'add',
            'csrf_token' => generateCsrfToken(),
            'add_name' => 'jannik@example.org',
            'add_email' => 'keine-adresse',
        ], [], $this->session(1));

        self::assertStringContainsString('Der Admin wurde nicht angelegt', $html);
        self::assertStringContainsString('Der Name darf kein @ enthalten', $html);
        self::assertStringContainsString('Bitte geben Sie eine gültige E-Mail-Adresse ein.', $html);
        self::assertStringContainsString('Bitte wählen Sie mindestens ein Recht aus.', $html);
        self::assertStringContainsString('value="keine-adresse"', $html);
        self::assertStringContainsString('is-invalid', $html);
        self::assertSame(3, (int) self::$pdo->query('SELECT COUNT(*) FROM pb_admins')->fetchColumn());

        $html = $this->render('admins.inc.php', [
            'action' => 'add',
            'csrf_token' => generateCsrfToken(),
            'add_name' => 'JANNIK',
            'add_email' => 'ANKE@example.org',
            'add_release' => 'Y',
        ], [], $this->session(1));
        self::assertStringContainsString('Es gibt bereits einen Admin mit diesem Namen.', $html);
        self::assertStringContainsString('Diese E-Mail-Adresse gehört schon zu einem anderen Admin.', $html);
    }

    #[Test]
    public function saveChangesRightsWithoutTouchingPassword(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'save',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '2',
            'edit_name' => 'Jannik',
            'edit_email' => 'jannik@example.org',
            'edit_release' => 'Y',
            'edit_config' => 'Y',
        ], [], $this->session(1));

        self::assertSame('Y', $this->column(2, 'config'));
        self::assertSame('N', $this->column(2, 'entries'));
        self::assertSame(self::$hash, $this->column(2, 'password'));
        $this->assertSavedOrLinkShown($html, 'Die Angaben für Jannik sind gespeichert.');
    }

    #[Test]
    public function saveWithoutChangesSaysSo(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'save',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '2',
            'edit_name' => 'Jannik',
            'edit_email' => 'jannik@example.org',
            'edit_release' => 'Y',
            'edit_entries' => 'Y',
        ], [], $this->session(1));

        if ($this->redirect === null) {
            self::assertStringContainsString('Es gab nichts zu speichern', $html);
        } else {
            self::assertSame('?page=admins', $this->redirect);
            self::assertStringContainsString('Es gab nichts zu speichern', (string) ($_SESSION['pb_flash']['text'] ?? ''));
        }
    }

    #[Test]
    public function sendLinkCreatesFreshToken(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'save',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '2',
            'edit_name' => 'Jannik',
            'edit_email' => 'jannik@example.org',
            'edit_release' => 'Y',
            'edit_entries' => 'Y',
            'send_link' => '1',
        ], [], $this->session(1));

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $this->column(2, 'reset_token'));
        self::assertSame(self::$hash, $this->column(2, 'password'), 'Das alte Passwort gilt weiter.');
        if ($this->redirect === null) {
            self::assertStringContainsString('id="pbAdminLink"', $html);
        }
    }

    #[Test]
    public function deleteAsksFirstThenDeletes(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'delete',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '2',
        ], [], $this->session(1));

        self::assertStringContainsString('id="pbDeleteConfirm"', $html);
        self::assertStringContainsString('id="pbDeleteCancel"', $html);
        self::assertStringContainsString('Soll der Zugang von Jannik', $html);
        self::assertSame('Jannik', $this->column(2, 'name'));

        $html = $this->render('admins.inc.php', [
            'action' => 'delete_confirm',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '2',
        ], [], $this->session(1));

        self::assertSame(0, (int) self::$pdo->query('SELECT COUNT(*) FROM pb_admins WHERE id = 2')->fetchColumn());
        $this->assertSavedOrLinkShown($html, 'Der Zugang von Jannik ist gelöscht.');
    }

    #[Test]
    public function superadminAndSelfCannotBeDeleted(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'delete_confirm',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '1',
        ], [], $this->session(3));
        self::assertStringContainsString('Der Superadmin lässt sich nicht löschen.', $html);

        $html = $this->render('admins.inc.php', [
            'action' => 'delete',
            'csrf_token' => generateCsrfToken(),
            'edit_id' => '3',
        ], [], $this->session(3));
        self::assertStringContainsString('Sie können sich nicht selbst löschen.', $html);
        self::assertSame(3, (int) self::$pdo->query('SELECT COUNT(*) FROM pb_admins')->fetchColumn());
    }

    #[Test]
    public function invalidCsrfTokenChangesNothing(): void
    {
        $html = $this->render('admins.inc.php', [
            'action' => 'save',
            'csrf_token' => 'falsch',
            'edit_id' => '2',
            'edit_name' => 'Jannik X',
            'edit_email' => 'jannik@example.org',
            'edit_release' => 'Y',
        ], [], $this->session(1));

        self::assertStringContainsString(pb_csrf_failed_message(), $html);
        self::assertStringContainsString('id="pbMessage"', $html);
        self::assertStringContainsString('value="Jannik X"', $html, 'Eingaben bleiben stehen.');
        self::assertSame('Jannik', $this->column(2, 'name'));
    }

    // ==================================================================
    // Mein Konto (A29)
    // ==================================================================

    #[Test]
    public function accountPageShowsFormAndRights(): void
    {
        $html = $this->render('account.inc.php', [], [], $this->session(2));

        foreach (['pbAccountName', 'pbAccountEmail', 'pbAccountPassword', 'pbAccountPassword2', 'pbAccountCurrent', 'pbAccountSubmit', 'pbAccountRights'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html);
        }
        self::assertStringContainsString('Einträge freischalten, Einträge bearbeiten und löschen', $html);
        self::assertStringContainsString('value="jannik@example.org"', $html);
    }

    #[Test]
    public function accountRequiresCurrentPasswordAndCountsFailures(): void
    {
        $html = $this->render('account.inc.php', [
            'action' => 'account',
            'csrf_token' => generateCsrfToken(),
            'account_name' => 'Jannik Hansen',
            'account_email' => 'jannik@example.org',
            'account_current' => 'falsch',
        ], [], $this->session(2));

        self::assertStringContainsString('Das aktuelle Passwort stimmt nicht.', $html);
        self::assertStringContainsString('value="Jannik Hansen"', $html);
        self::assertSame('Jannik', $this->column(2, 'name'));
        self::assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_login_attempts WHERE name = 'account:2'")->fetchColumn());
    }

    #[Test]
    public function accountThrottlesAfterTenFailures(): void
    {
        $stmt = self::$pdo->prepare('INSERT INTO pb_login_attempts (ip, name, time) VALUES (?, ?, ?)');
        for ($i = 0; $i < 10; $i++) {
            $stmt->execute(['192.0.2.50', 'Jannik', time()]);
        }

        $html = $this->render('account.inc.php', [
            'action' => 'account',
            'csrf_token' => generateCsrfToken(),
            'account_name' => 'Jannik',
            'account_email' => 'jannik@example.org',
            'account_current' => self::PASSWORD,
        ], [], $this->session(2));

        self::assertStringContainsString('Zu viele Fehlversuche. Bitte warten Sie 15 Minuten.', $html);
    }

    #[Test]
    public function accountChangesPasswordAndKeepsOwnSession(): void
    {
        $html = $this->render('account.inc.php', [
            'action' => 'account',
            'csrf_token' => generateCsrfToken(),
            'account_name' => 'Jannik',
            'account_email' => 'jannik.neu@example.org',
            'account_password' => 'Strandkorb 2026',
            'account_password2' => 'Strandkorb 2026',
            'account_current' => self::PASSWORD,
        ], [], $this->session(2));

        self::assertTrue(password_verify('Strandkorb 2026', (string) $this->column(2, 'password')));
        self::assertSame('jannik.neu@example.org', $this->column(2, 'email'));
        $changed = (int) $this->column(2, 'pw_changed');
        self::assertEqualsWithDelta(time(), $changed, 5);
        self::assertSame($changed, $_SESSION['pb_login_time'] ?? null, 'Eigene Sitzung bleibt angemeldet.');
        $this->assertSavedOrLinkShown($html, 'Andere Geräte, auf denen Sie angemeldet waren, sind jetzt abgemeldet.', '?page=account');
    }

    #[Test]
    public function accountRejectsShortOrMismatchedPassword(): void
    {
        $html = $this->render('account.inc.php', [
            'action' => 'account',
            'csrf_token' => generateCsrfToken(),
            'account_name' => 'Jannik',
            'account_email' => 'jannik@example.org',
            'account_password' => 'kurz',
            'account_password2' => 'kurz',
            'account_current' => self::PASSWORD,
        ], [], $this->session(2));

        self::assertStringContainsString('mindestens 8 Zeichen', $html);
        self::assertSame(0, (int) $this->column(2, 'pw_changed'));
    }

    // ==================================================================
    // Passwort vergessen / festlegen (A25)
    // ==================================================================

    #[Test]
    public function recoverFormHasOneField(): void
    {
        $html = $this->render('password.inc.php');

        self::assertStringContainsString('id="pb_recover_name"', $html);
        self::assertStringContainsString('Name oder E-Mail-Adresse', $html);
        self::assertStringContainsString('id="pbRecoverSubmit"', $html);
        self::assertStringContainsString('id="pbBackToLogin"', $html);
    }

    #[Test]
    public function recoverByEmailStoresHashedTokenAndAnswersGenerically(): void
    {
        $html = $this->render('password.inc.php', [
            'action' => 'recover',
            'csrf_token' => generateCsrfToken(),
            'name' => 'JANNIK@example.org',
        ]);

        $token = (string) $this->column(2, 'reset_token');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertEqualsWithDelta(time() + 1800, (int) $this->column(2, 'reset_token_expires'), 10);
        $this->assertSavedOrLinkShown($html, 'Falls ein Konto zu diesem Namen oder dieser E-Mail-Adresse existiert', '?page=login');

        // Unbekannter Name: gleiche Antwort
        $this->redirect = null;
        $html = $this->render('password.inc.php', [
            'action' => 'recover',
            'csrf_token' => generateCsrfToken(),
            'name' => 'Gibtsnicht',
        ]);
        $this->assertSavedOrLinkShown($html, 'Falls ein Konto zu diesem Namen oder dieser E-Mail-Adresse existiert', '?page=login');
    }

    #[Test]
    public function recoverSendsAtMostOneMailPerAccountAndFivePerIp(): void
    {
        $post = ['action' => 'recover', 'name' => 'Jannik'];
        $this->render('password.inc.php', $post + ['csrf_token' => generateCsrfToken()]);
        $first = $this->column(2, 'reset_token');

        $this->render('password.inc.php', $post + ['csrf_token' => generateCsrfToken()]);
        self::assertSame($first, $this->column(2, 'reset_token'), 'Zweite Anforderung innerhalb von 5 Minuten erzeugt keinen neuen Link.');

        for ($i = 0; $i < 3; $i++) {
            $this->render('password.inc.php', $post + ['csrf_token' => generateCsrfToken()]);
        }
        $this->redirect = null;
        $html = $this->render('password.inc.php', $post + ['csrf_token' => generateCsrfToken()]);
        self::assertStringContainsString('Zu viele Anfragen. Bitte warten Sie 15 Minuten.', $html);
        self::assertNull($this->redirect);
    }

    #[Test]
    public function tokenLinkShowsFormAndSetsPassword(): void
    {
        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/admin_email_helpers.inc.php';
        $token = pb_admin_issue_password_token(self::$pdo, 'pb_admins', 2, 172800);

        $html = $this->render('password.inc.php', [], ['token' => $token, 'welcome' => '1']);
        self::assertStringContainsString('Willkommen, <b>Jannik</b>!', $html);
        self::assertStringContainsString('id="pbSetPasswordSubmit"', $html);

        $html = $this->render('password.inc.php', [
            'action' => 'set_password',
            'csrf_token' => generateCsrfToken(),
            'token' => $token,
            'new_password1' => '        ',
            'new_password2' => '        ',
        ]);
        self::assertStringContainsString('nicht nur aus Leerzeichen', $html);

        $html = $this->render('password.inc.php', [
            'action' => 'set_password',
            'csrf_token' => generateCsrfToken(),
            'token' => $token,
            'new_password1' => ' Möwenblick am Meer ',
            'new_password2' => ' Möwenblick am Meer ',
        ]);
        self::assertTrue(password_verify(' Möwenblick am Meer ', (string) $this->column(2, 'password')), 'Passwort wird nicht getrimmt.');
        self::assertNull($this->column(2, 'reset_token'));
        self::assertEqualsWithDelta(time(), (int) $this->column(2, 'pw_changed'), 5);
        $this->assertSavedOrLinkShown($html, 'Ihr Passwort ist gespeichert.', '?page=login');

        // Der Link funktioniert nur einmal.
        $this->redirect = null;
        $html = $this->render('password.inc.php', [], ['token' => $token]);
        self::assertStringContainsString('Der Link ist ungültig oder abgelaufen.', $html);
    }

    // ==================================================================
    // Konfiguration (A09, A16, A17, A18, A19, A20)
    // ==================================================================

    #[Test]
    public function configurationShowsSectionsWithoutDeadFields(): void
    {
        $html = $this->render('configuration.inc.php', [], [], $this->session(1));

        foreach (['pbConfigGeneral', 'pbConfigEntries', 'pbConfigMail', 'pbConfigDesign', 'pbConfigSubmit', 'cfg_title', 'cfg_mail_from', 'cfg_guestbook', 'cfg_admin_url'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $html);
        }
        self::assertStringNotContainsString('id="cfg_color"', $html);
        self::assertStringNotContainsString('id="cfg_language"', $html);
        self::assertStringContainsString('Adresse Ihrer eigenen Domain, sonst landen Mails im Spam.', $html);
        self::assertStringContainsString('Skripte (&lt;script&gt;, onclick= und Ähnliches) werden entfernt.', $html);
        self::assertStringContainsString('Datum und Uhrzeit', $html);
        self::assertStringContainsString('value="Gästebuch Möwenblick"', $html);
        self::assertDoesNotMatchRegularExpression('/\b(unterstuetzt|Durchblaettern|fuer|ueber)\b/', $html);
    }

    #[Test]
    public function configurationDeniedWithoutRight(): void
    {
        $html = $this->render('configuration.inc.php', [], [], $this->session(2));

        self::assertStringContainsString('keine Berechtigung', $html);
        self::assertStringNotContainsString('id="cfg_title"', $html);
    }

    #[Test]
    public function configurationSavesAndNormalizes(): void
    {
        $html = $this->render('configuration.inc.php', $this->configPost([
            'change_title' => 'Gästebuch Ferienwohnung Möwenblick',
            'change_mail_from' => 'gaestebuch@moewenblick.example',
            'change_admin_url' => 'https://www.moewenblick.example/pb_inc/admincenter/index.php',
            'change_show_entries' => '5',
        ]), [], $this->session(1));

        $row = self::$pdo->query('SELECT * FROM pb_config')->fetch();
        self::assertSame('Gästebuch Ferienwohnung Möwenblick', $row['title']);
        self::assertSame('gaestebuch@moewenblick.example', $row['mail_from']);
        self::assertSame('https://www.moewenblick.example/pb_inc/admincenter/', $row['admin_url']);
        self::assertSame(5, (int) $row['show_entries']);
        self::assertSame('#FF0000', $row['color'], 'Akzentfarbe bleibt unberührt.');
        self::assertSame('ger1', $row['language'], 'Sprache bleibt unberührt.');
        $this->assertSavedOrLinkShown($html, 'Die Konfiguration ist gespeichert.', '?page=configuration');
    }

    #[Test]
    public function configurationKeepsInputOnErrors(): void
    {
        $html = $this->render('configuration.inc.php', $this->configPost([
            'change_title' => 'Neuer Titel',
            'change_email' => 'anke(at)example',
            'change_send_email' => 'Y',
            'change_date' => str_repeat('d', 25),
            'change_spam_check' => '-5',
            'change_show_entries' => '0',
            'change_guestbook_name' => 'https://www.example.org/gaestebuch.php',
            'change_admin_url' => 'www.example.org/admin',
        ]), [], $this->session(1));

        self::assertStringContainsString('Nichts gespeichert. Bitte prüfen Sie die markierten Felder.', $html);
        self::assertStringContainsString('value="Neuer Titel"', $html);
        self::assertStringContainsString('Das Datumsformat darf höchstens 20 Zeichen lang sein.', $html);
        self::assertStringContainsString('Bitte geben Sie eine gültige E-Mail-Adresse ein.', $html);
        self::assertStringContainsString('ganze Zahl von 0 bis 86400', $html);
        self::assertStringContainsString('ganze Zahl von 1 bis 100', $html);
        self::assertStringContainsString('ohne Ordner und ohne https://', $html);
        self::assertStringContainsString('vollständige Adresse mit https://', $html);
        self::assertStringNotContainsString('Datenbankfehler', $html);
        self::assertSame('Gästebuch Möwenblick', self::$pdo->query('SELECT title FROM pb_config')->fetchColumn());
    }

    #[Test]
    public function configurationRejectsMissingGuestbookFile(): void
    {
        $html = $this->render('configuration.inc.php', $this->configPost([
            'change_guestbook_name' => 'gaestebuch.php',
        ]), [], $this->session(1));

        self::assertStringContainsString('Die Datei gaestebuch.php gibt es im PowerBook-Ordner nicht.', $html);
    }

    #[Test]
    public function configurationWarnsAboutScriptsAndMissingPlaceholders(): void
    {
        $html = $this->render('configuration.inc.php', $this->configPost([
            'change_design' => '<div onclick="x()">(#TEXT#)</div><script>alert(1)</script>',
        ]), [], $this->session(1));
        $text = $this->redirect !== null ? (string) ($_SESSION['pb_flash']['text'] ?? '') : $html;
        self::assertStringContainsString('Diese werden im Gästebuch entfernt.', $text);

        $this->redirect = null;
        $html = $this->render('configuration.inc.php', $this->configPost([
            'change_design' => '<table bgcolor="#ffffff"><tr><td>Hallo</td></tr></table>',
        ]), [], $this->session(1));
        $text = $this->redirect !== null ? (string) ($_SESSION['pb_flash']['text'] ?? '') : $html;
        self::assertStringContainsString('Standardvorlage', $text);
    }

    #[Test]
    public function configurationCsrfFailureKeepsInput(): void
    {
        $html = $this->render('configuration.inc.php', array_merge($this->configPost(['change_title' => 'Zweiter Tab']), ['csrf_token' => 'alt']), [], $this->session(1));

        self::assertStringContainsString(pb_csrf_failed_message(), $html);
        self::assertStringContainsString('value="Zweiter Tab"', $html);
        self::assertSame('Gästebuch Möwenblick', self::$pdo->query('SELECT title FROM pb_config')->fetchColumn());
    }

    protected function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('CREATE TABLE pb_config (
            id INTEGER PRIMARY KEY, title TEXT DEFAULT "Gästebuch", mail_from TEXT DEFAULT "",
            "release" TEXT DEFAULT "U", send_email TEXT DEFAULT "N", email TEXT DEFAULT "",
            date TEXT DEFAULT "d.m.Y", time TEXT DEFAULT "H:i", spam_check INTEGER DEFAULT 30,
            color TEXT DEFAULT "#FF0000", show_entries INTEGER DEFAULT 10, guestbook_name TEXT DEFAULT "pbook.php",
            admin_url TEXT DEFAULT "", text_format TEXT DEFAULT "Y", icons TEXT DEFAULT "Y", smilies TEXT DEFAULT "Y",
            pages TEXT DEFAULT "D", use_thanks TEXT DEFAULT "N", language TEXT DEFAULT "ger1",
            design TEXT DEFAULT "(#DATE#) (#TEXT#)", thanks_title TEXT DEFAULT "", thanks TEXT DEFAULT "",
            statements TEXT DEFAULT "Y")');
        $pdo->exec('CREATE TABLE pb_admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, password TEXT NOT NULL,
            config TEXT DEFAULT "N", "release" TEXT DEFAULT "Y", entries TEXT DEFAULT "Y", admins TEXT DEFAULT "N",
            reset_token TEXT DEFAULT NULL, reset_token_expires INTEGER DEFAULT NULL, pw_changed INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE pb_login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT DEFAULT "", name TEXT DEFAULT "", time INTEGER DEFAULT 0)');
        $pdo->exec("INSERT INTO pb_config (id, title, email) VALUES (1, 'Gästebuch Möwenblick', 'anke@example.org')");

        $insert = $pdo->prepare('INSERT INTO pb_admins (id, name, email, password, config, "release", entries, admins) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([1, 'Anke', 'anke@example.org', self::$hash, 'Y', 'Y', 'Y', 'Y']);
        $insert->execute([2, 'Jannik', 'jannik@example.org', self::$hash, 'N', 'Y', 'Y', 'N']);
        $insert->execute([3, 'Ole', 'ole@example.org', self::$hash, 'N', 'N', 'N', 'Y']);
        self::$pdo = $pdo;

        $_POST = [];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '192.0.2.50';
        unset($_SESSION['pb_flash'], $_SESSION['pb_login_time'], $_SESSION['admin_id'], $_SESSION['admin_name']);
        $GLOBALS['config_title'] = 'Gästebuch Möwenblick';
        $GLOBALS['config_admin_url'] = 'http://localhost/pb_inc/admincenter/';
        $this->redirect = null;
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        unset($_SESSION['pb_flash'], $_SESSION['pb_login_time'], $_SESSION['admin_id'], $_SESSION['admin_name']);
        unset($GLOBALS['config_title'], $GLOBALS['config_admin_url']);
    }

    // ==================================================================
    // Hilfen
    // ==================================================================

    /**
     * @param array<string, string> $post
     * @param array<string, string> $get
     * @param array<string, mixed>  $adminSession
     */
    private function render(string $file, array $post = [], array $get = [], array $adminSession = []): string
    {
        $_POST = $post;
        $_GET = $get;
        $pdo = self::$pdo;
        $pb_admin = 'pb_admins';
        $pb_config = 'pb_config';
        $pb_entries = 'pb_entries';
        $admin_session = $adminSession;
        $this->redirect = null;

        $level = ob_get_level();
        ob_start();

        try {
            include POWERBOOK_ROOT . '/pb_inc/admincenter/' . $file;
        } catch (\RuntimeException $e) {
            if (get_class($e) !== 'PbAdminRedirect') {
                while (ob_get_level() > $level) {
                    ob_end_clean();
                }

                throw $e;
            }
            /** @var object{location: string} $e */
            $this->redirect = $e->location;
        }

        $output = '';
        while (ob_get_level() > $level) {
            $output = (string) ob_get_clean() . $output;
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    private function session(int $id): array
    {
        $row = self::$pdo->query('SELECT * FROM pb_admins WHERE id = ' . $id)->fetch();
        self::assertIsArray($row);

        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'config' => $row['config'],
            'release' => $row['release'],
            'entries' => $row['entries'],
            'admins' => $row['admins'],
        ];
    }

    private function column(int $id, string $column): mixed
    {
        return self::$pdo->query('SELECT "' . $column . '" FROM pb_admins WHERE id = ' . $id)->fetchColumn();
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function configPost(array $overrides = []): array
    {
        return array_merge([
            'action' => 'update',
            'csrf_token' => generateCsrfToken(),
            'change_title' => 'Gästebuch Möwenblick',
            'change_release' => 'U',
            'change_send_email' => 'N',
            'change_email' => 'anke@example.org',
            'change_mail_from' => '',
            'change_date' => 'd.m.Y',
            'change_time' => 'H:i',
            'change_spam_check' => '30',
            'change_show_entries' => '10',
            'change_guestbook_name' => 'pbook.php',
            'change_admin_url' => '',
            'change_text_format' => 'Y',
            'change_icons' => 'Y',
            'change_smilies' => 'Y',
            'change_pages' => 'D',
            'change_use_thanks' => 'N',
            'change_design' => '(#DATE#) (#TIME#) Uhr (#TEXT#)',
            'change_thanks_title' => '',
            'change_thanks' => '',
            'change_statements' => 'Y',
        ], $overrides);
    }

    /**
     * Erfolg: entweder Weiterleitung mit Meldung (PRG) oder Meldung auf der Seite.
     * Kommt die Mail nicht an (Testumgebung), zeigt admins.inc.php stattdessen den Link.
     */
    private function assertSavedOrLinkShown(string $html, string $text, string $target = '?page=admins'): void
    {
        if ($this->redirect !== null) {
            self::assertSame($target, $this->redirect);
            self::assertStringContainsString($text, (string) ($_SESSION['pb_flash']['text'] ?? ''));

            return;
        }

        self::assertStringContainsString('id="pbMessage"', $html);
        self::assertStringContainsString($text, $html);
    }
}
