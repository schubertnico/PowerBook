<?php

/**
 * PowerBook - PHPUnit Tests
 * Seiten des AdminCenters: Start, Anmelden, Abmelden, Lizenz, Datums-Hilfe,
 * Einträge, Bearbeiten, Antwort, Freischalten und Blätterleiste.
 *
 * Die Seiten werden mit eigener SQLite-Datenbank (Schema 3.1) eingebunden.
 * Weiterleitungen (pb_admin_redirect()) und Meldungen (pb_admin_flash())
 * fängt der Helfer render() ab.
 *
 * Die Seiten von Agent D (Admins, Konfiguration, Passwort, Mein Konto) testet
 * tests/Unit/AdminAccountPagesTest.php.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace PowerBook\Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdminPagesTest extends TestCase
{
    private const FULL_RIGHTS = ['id' => 1, 'name' => 'Anke', 'email' => 'anke@example.org', 'config' => 'Y', 'release' => 'Y', 'entries' => 'Y', 'admins' => 'Y'];

    private const MODERATOR = ['id' => 2, 'name' => 'Jannik', 'email' => 'jannik@example.org', 'config' => 'N', 'release' => 'Y', 'entries' => 'Y', 'admins' => 'N'];

    private static PDO $pdo;

    private ?string $redirect = null;

    public static function setUpBeforeClass(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $pdo->exec('CREATE TABLE pb_config (
            id INTEGER PRIMARY KEY,
            title TEXT DEFAULT "Gästebuch",
            mail_from TEXT DEFAULT "",
            "release" TEXT DEFAULT "U",
            send_email TEXT DEFAULT "N",
            email TEXT DEFAULT "admin@test.com",
            date TEXT DEFAULT "d.m.Y",
            time TEXT DEFAULT "H:i",
            spam_check INTEGER DEFAULT 60,
            color TEXT DEFAULT "#FF0000",
            show_entries INTEGER DEFAULT 10,
            guestbook_name TEXT DEFAULT "pbook.php",
            admin_url TEXT DEFAULT "",
            text_format TEXT DEFAULT "Y",
            icons TEXT DEFAULT "Y",
            smilies TEXT DEFAULT "Y",
            pages TEXT DEFAULT "D",
            use_thanks TEXT DEFAULT "N",
            language TEXT DEFAULT "ger1",
            design TEXT DEFAULT "",
            thanks_title TEXT DEFAULT "",
            thanks TEXT DEFAULT "",
            statements TEXT DEFAULT "Y"
        )');
        $pdo->exec('CREATE TABLE pb_admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            password TEXT NOT NULL,
            config TEXT DEFAULT "N",
            admins TEXT DEFAULT "N",
            entries TEXT DEFAULT "Y",
            "release" TEXT DEFAULT "Y",
            reset_token TEXT DEFAULT NULL,
            reset_token_expires INTEGER DEFAULT NULL,
            pw_changed INTEGER DEFAULT 0
        )');
        $pdo->exec('CREATE TABLE pb_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT DEFAULT "",
            text TEXT NOT NULL,
            date INTEGER DEFAULT 0,
            homepage TEXT DEFAULT "",
            ip TEXT DEFAULT "",
            status TEXT DEFAULT "R",
            icon TEXT DEFAULT "",
            smilies TEXT DEFAULT "Y",
            statement TEXT DEFAULT "",
            statement_by TEXT DEFAULT ""
        )');
        $pdo->exec('CREATE TABLE pb_login_attempts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT DEFAULT "",
            name TEXT DEFAULT "",
            time INTEGER DEFAULT 0
        )');
        $pdo->exec('INSERT INTO pb_config (id) VALUES (1)');
        $pdo->exec("INSERT INTO pb_admins (id, name, email, password, config, admins, entries, \"release\")
            VALUES (1, 'Anke', 'anke@example.org', '" . password_hash('test123', PASSWORD_DEFAULT) . "', 'Y', 'Y', 'Y', 'Y')");

        self::$pdo = $pdo;

        require_once POWERBOOK_ROOT . '/pb_inc/admincenter/layout.inc.php';
    }

    // ========================================================================
    // Startseite
    // ========================================================================

    #[Test]
    public function homeShowsWelcomeCountsAndPendingHint(): void
    {
        $this->insertEntry(['status' => 'R']);
        $this->insertEntry(['status' => 'R']);
        $this->insertEntry(['status' => 'U']);

        $html = $this->render('home.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('Willkommen im AdminCenter', $html);
        $this->assertStringContainsString('Hallo Anke, hier verwalten Sie Ihr Gästebuch.', $html);
        $this->assertMatchesRegularExpression('~id="pbOverviewPublic"[^>]*>2<~', $html);
        $this->assertMatchesRegularExpression('~id="pbOverviewPending"[^>]*>1<~', $html);
        $this->assertStringContainsString('id="pbHomePending"', $html);
        $this->assertStringContainsString('1 Eintrag wartet auf Freischaltung.', $html);
        $this->assertStringContainsString('Superadmin – Sie haben immer alle Rechte.', $html);
    }

    #[Test]
    public function homeQuickLinksFollowRights(): void
    {
        $html = $this->render('home.inc.php', ['admin_session' => self::MODERATOR]);

        $this->assertStringContainsString('id="pbQuickEntries"', $html);
        $this->assertStringContainsString('id="pbQuickRelease"', $html);
        $this->assertStringContainsString('id="pbQuickAccount"', $html);
        $this->assertStringNotContainsString('id="pbQuickAdmins"', $html);
        $this->assertStringNotContainsString('id="pbQuickConfig"', $html);
        $this->assertStringContainsString('&#10003; Einträge freischalten', $html);
        $this->assertStringContainsString('&ndash; Konfiguration ändern', $html);
    }

    #[Test]
    public function homeDoesNotLeakVersions(): void
    {
        $html = $this->render('home.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringNotContainsString(PHP_VERSION, $html);
        $this->assertStringNotContainsString(PB_VERSION, $html);
    }

    // ========================================================================
    // Anmelden / Abmelden / Lizenz
    // ========================================================================

    #[Test]
    public function loginFormHasStableIdsAndKeepsName(): void
    {
        $html = $this->render('login.inc.php', ['loggedIn' => false, 'loginName' => 'Anke']);

        foreach (['pb_login_name', 'pb_login_password', 'pbLoginSubmit', 'pbForgotLink'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html);
        }
        $this->assertStringContainsString('value="Anke"', $html);
        $this->assertStringContainsString('Name oder E-Mail-Adresse', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('60 Minuten ohne Aktivität', $html);
        $this->assertStringNotContainsString('>Login<', $html);
    }

    #[Test]
    public function loginPageWhenAlreadyLoggedIn(): void
    {
        $html = $this->render('login.inc.php', ['loggedIn' => true, 'loginName' => '']);

        $this->assertStringContainsString('Sie sind bereits angemeldet.', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    #[Test]
    public function logoutPageOffersPostButton(): void
    {
        $html = $this->render('logout.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('Möchten Sie sich abmelden, Anke?', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('id="pbLogoutConfirm"', $html);
        $this->assertStringNotContainsString('logout=yes', $html);
    }

    #[Test]
    public function licensePageShowsMitLicense(): void
    {
        $html = $this->render('license.inc.php');

        $this->assertStringContainsString('Lizenz', $html);
        $this->assertStringContainsString('MIT License', $html);
        $this->assertStringContainsString('Permission is hereby granted', $html);
        $this->assertStringContainsString('Axel', $html);
        $this->assertStringContainsString('Nico Schubert', $html);
        $this->assertStringContainsString('powerscripts.org', $html);
    }

    // ========================================================================
    // Datums-Hilfe
    // ========================================================================

    #[Test]
    public function dateHelpShowsGermanNamesForDateSection(): void
    {
        $_GET['section'] = 'date';
        $html = $this->render('date-help.php');

        $this->assertStringContainsString('id="pbHelpDate"', $html);
        $this->assertStringNotContainsString('id="pbHelpTime"', $html);
        $this->assertStringContainsString(PB_MONTHS[(int) date('n')], $html);
        $this->assertStringContainsString(PB_WEEKDAYS[(int) date('w')], $html);
        $this->assertStringContainsString('bootstrap', $html);
        $this->assertStringNotContainsString('1st', $html);
        $this->assertStringNotContainsString('Montag, 1. Januar 2025', $html);
    }

    #[Test]
    public function dateHelpShowsTimeSection(): void
    {
        $_GET['section'] = 'time';
        $html = $this->render('date-help.php');

        $this->assertStringContainsString('id="pbHelpTime"', $html);
        $this->assertStringNotContainsString('id="pbHelpDate"', $html);
        $this->assertStringContainsString('Stunde', $html);
        $this->assertStringContainsString('Minute', $html);
    }

    #[Test]
    public function dateHelpWithoutSectionShowsBoth(): void
    {
        $html = $this->render('date-help.php');

        $this->assertStringContainsString('id="pbHelpDate"', $html);
        $this->assertStringContainsString('id="pbHelpTime"', $html);
    }

    // ========================================================================
    // Einträge (Liste)
    // ========================================================================

    #[Test]
    public function entriesPageDeniesWithoutPermission(): void
    {
        $html = $this->render('entries.inc.php', ['admin_session' => ['entries' => 'N']]);

        $this->assertStringContainsString('keine Berechtigung', $html);
    }

    #[Test]
    public function entriesPageShowsEmptyMessage(): void
    {
        $html = $this->render('entries.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('Es gibt noch keine Einträge.', $html);
    }

    #[Test]
    public function entriesPageShowsStatusAnswerDateAndMail(): void
    {
        $ts = mktime(18, 42, 0, 8, 16, 2026);
        $released = $this->insertEntry([
            'name' => 'Familie Brandt', 'email' => 'brandt@example.org', 'date' => $ts,
            'statement' => 'Vielen Dank!', 'statement_by' => 'Anke',
        ]);
        $pending = $this->insertEntry(['name' => 'Kredit-Express', 'status' => 'U', 'date' => $ts + 3600]);

        $html = $this->render('entries.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('id="pbEntry' . $released . '"', $html);
        $this->assertMatchesRegularExpression('~id="pbEntryStatus' . $pending . '"[^>]*>Wartet auf Freischaltung<~', $html);
        $this->assertMatchesRegularExpression('~id="pbEntryStatus' . $released . '"[^>]*>Freigeschaltet<~', $html);
        $this->assertStringContainsString('Antwort von Anke:', $html);
        $this->assertStringContainsString('href="mailto:brandt@example.org"', $html);
        $this->assertStringContainsString('16.08.2026', $html);
        $this->assertStringContainsString('18:42 Uhr', $html);
        $this->assertStringContainsString('id="pbEntryEdit' . $released . '"', $html);
        $this->assertMatchesRegularExpression('~id="pbEntryAnswer' . $released . '"[^>]*>Antwort bearbeiten<~', $html);
        $this->assertMatchesRegularExpression('~id="pbEntryAnswer' . $pending . '"[^>]*>Antworten<~', $html);
        $this->assertStringContainsString('id="pbEntriesPendingHint"', $html);
        $this->assertStringContainsString('href="?page=release"', $html);
        // Neueste zuerst
        $this->assertLessThan(strpos($html, 'id="pbEntry' . $released . '"'), strpos($html, 'id="pbEntry' . $pending . '"'));
        $this->assertStringNotContainsString('Statement', $html);
    }

    #[Test]
    public function entriesPageUsesConfiguredGermanDate(): void
    {
        $this->insertEntry(['date' => mktime(12, 0, 0, 9, 20, 2026)]);

        $html = $this->render('entries.inc.php', ['admin_session' => self::FULL_RIGHTS, 'config_date' => 'l, j. F Y']);

        $this->assertStringContainsString('Sonntag, 20. September 2026', $html);
    }

    #[Test]
    public function entriesPageWithoutReleaseRightHasNoReleaseLink(): void
    {
        $this->insertEntry(['status' => 'U']);

        $html = $this->render('entries.inc.php', ['admin_session' => ['entries' => 'Y', 'release' => 'N']]);

        $this->assertStringContainsString('id="pbEntriesPendingHint"', $html);
        $this->assertStringNotContainsString('href="?page=release"', $html);
    }

    #[Test]
    public function entriesPageBeyondLastPageShowsHint(): void
    {
        $this->insertEntry();
        $_GET['tmp_start'] = '99999';

        $html = $this->render('entries.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('Auf dieser Seite stehen keine Einträge.', $html);
    }

    #[Test]
    public function entriesPagePaginatesWithPageNumbers(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->insertEntry(['date' => 1_700_000_000 + $i]);
        }

        $html = $this->render('entries.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame(15, substr_count($html, 'class="card pb-entry-card'));
        $this->assertStringContainsString('id="pbEntriesPagerTop"', $html);
        $this->assertStringContainsString('id="pbEntriesPagerBottom"', $html);
        $this->assertStringContainsString('href="?page=entries&amp;tmp_start=15">2</a>', $html);
    }

    #[Test]
    public function entriesPageNotesHiddenAnswers(): void
    {
        $this->insertEntry();

        $html = $this->render('entries.inc.php', ['admin_session' => self::FULL_RIGHTS, 'config_statements' => 'N']);

        $this->assertStringContainsString('Antworten sind im Gästebuch zurzeit ausgeblendet', $html);
    }

    // ========================================================================
    // entry.inc.php (Aufbereitung eines Eintrags)
    // ========================================================================

    #[Test]
    public function entryHelperBuildsBasicValues(): void
    {
        $vars = $this->entryVars(['name' => '<b>Gast</b>', 'ip' => '', 'date' => mktime(9, 5, 0, 1, 2, 2026)]);

        $this->assertSame('&lt;b&gt;Gast&lt;/b&gt;', $vars['email_name']);
        $this->assertSame('unbekannt', $vars['ip']);
        $this->assertSame('02.01.2026', $vars['date']);
        $this->assertSame('09:05 Uhr', $vars['time']);
        $this->assertStringContainsString('Freigeschaltet', $vars['statusBadge']);
        $this->assertSame('', $vars['show_icq']);
        $this->assertSame('', $vars['answerHtml']);
    }

    #[Test]
    public function entryHelperOnlyShowsKnownIcons(): void
    {
        $this->assertStringContainsString('../smilies/happy1.gif', $this->entryVars(['icon' => 'happy1'])['show_icon']);
        $this->assertSame('', $this->entryVars(['icon' => '../../assets/x'])['show_icon']);
        $this->assertSame('', $this->entryVars(['icon' => 'happy1'], ['config_icons' => 'N'])['show_icon']);
    }

    #[Test]
    public function entryHelperRendersBbcodeAndSmileysLikeGuestbook(): void
    {
        $vars = $this->entryVars(['text' => '[b]Fett[/b] und [i]offen :)', 'smilies' => 'Y']);

        $this->assertStringContainsString('<b>Fett</b>', $vars['entryText']);
        $this->assertStringContainsString('[i]offen', $vars['entryText']);
        $this->assertStringContainsString('../smilies/happy1.gif', $vars['entryText']);
        $this->assertStringNotContainsString('happy1.gif', $this->entryVars(['text' => 'Hallo :)', 'smilies' => 'N'])['entryText']);
    }

    #[Test]
    public function entryHelperNormalizesHomepage(): void
    {
        $vars = $this->entryVars(['homepage' => 'www.example.org/seite']);
        $this->assertStringContainsString('href="https://www.example.org/seite"', $vars['homepage_link']);
        $this->assertStringContainsString('>www.example.org</a>', $vars['homepage_link']);
        $this->assertSame($vars['homepage_link'], $vars['url']);

        $this->assertStringContainsString('keine Homepage', $this->entryVars(['homepage' => 'javascript:alert(1)'])['homepage_link']);
    }

    #[Test]
    public function entryHelperShowsAnswerAndPendingStatus(): void
    {
        $vars = $this->entryVars(['id' => 7, 'status' => 'U', 'statement' => "Danke!\nBis bald", 'statement_by' => 'Anke']);

        $this->assertStringContainsString('id="pbEntryAnswerText7"', $vars['answerHtml']);
        $this->assertStringContainsString('Antwort von Anke:', $vars['answerHtml']);
        $this->assertStringContainsString('Danke!<br>', $vars['answerHtml']);
        $this->assertStringContainsString('Antwort von Anke:', $vars['entry']['text']);
        $this->assertStringContainsString('id="pbEntryStatus7"', $vars['statusBadge']);
        $this->assertStringContainsString('Wartet auf Freischaltung', $vars['statusBadge']);
        $this->assertSame('', $this->entryVars(['statement' => '   '])['answerHtml']);
    }

    #[Test]
    public function entryHelperShowsMailtoOnlyWithAddress(): void
    {
        $vars = $this->entryVars(['name' => 'Ines', 'email' => 'ines@example.org']);

        $this->assertStringContainsString('href="mailto:ines@example.org"', $vars['email_name']);
        $this->assertStringContainsString('ines@example.org</a>', $vars['entryEmailLink']);
        $this->assertSame('', $this->entryVars(['email' => ''])['entryEmailLink']);
    }

    // ========================================================================
    // Bearbeiten
    // ========================================================================

    #[Test]
    public function editPageDeniesWithoutPermission(): void
    {
        $html = $this->render('edit.inc.php', ['admin_session' => ['entries' => 'N', 'release' => 'Y']]);

        $this->assertStringContainsString('keine Berechtigung', $html);
    }

    #[Test]
    public function editPageRejectsUnknownIds(): void
    {
        $this->assertStringContainsString('ID unbekannt', $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]));

        $_GET['edit_id'] = '-1';
        $this->assertStringContainsString('ID unbekannt', $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]));

        $_GET['edit_id'] = '999';
        $this->assertStringContainsString('Diesen Eintrag gibt es nicht mehr.', $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]));
    }

    #[Test]
    public function editPageShowsFormWithStableIds(): void
    {
        $id = $this->insertEntry(['name' => 'Familie Özdemir', 'homepage' => 'https://www.example.org/']);
        $_GET['edit_id'] = (string) $id;

        $html = $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        foreach (['pb_edit_name', 'pb_edit_email', 'pb_edit_homepage', 'pb_edit_status', 'pb_edit_text', 'pb_edit_icon_no', 'pb_edit_smilies', 'pbEditSubmit', 'pbEditReset', 'pbEditDelete', 'pbBackToEntries'] as $field) {
            $this->assertStringContainsString('id="' . $field . '"', $html, $field);
        }
        $this->assertStringContainsString('Familie Özdemir', $html);
        $this->assertStringContainsString('>Freigeschaltet</option>', $html);
        $this->assertStringContainsString('>Wartet auf Freischaltung</option>', $html);
        $this->assertStringNotContainsString('input-group-text', $html);
        $this->assertStringNotContainsString('Veroeffentlicht', $html);
        $this->assertStringNotContainsString('Button', $html);
    }

    #[Test]
    public function editPageHidesStatusWithoutReleaseRight(): void
    {
        $id = $this->insertEntry();
        $_GET['edit_id'] = (string) $id;

        $html = $this->render('edit.inc.php', ['admin_session' => ['entries' => 'Y', 'release' => 'N']]);

        $this->assertStringNotContainsString('id="pb_edit_status"', $html);
        $this->assertStringContainsString('Den Status ändert nur, wer Einträge freischalten darf.', $html);
    }

    #[Test]
    public function editPageSavesAndRedirectsToForm(): void
    {
        $id = $this->insertEntry(['name' => 'Alt', 'text' => 'Alter Text']);
        $_POST = $this->editPost($id, ['edit_name' => 'Neu', 'edit_text' => 'Neuer Text', 'edit_homepage' => 'example.org', 'edit_status' => 'U']);

        $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('?page=edit&edit_id=' . $id, $this->redirect);
        $this->assertSame(['type' => 'success', 'text' => 'Der Eintrag wurde gespeichert.'], $this->flash());
        $row = $this->row($id);
        $this->assertSame('Neu', $row['name']);
        $this->assertSame('Neuer Text', $row['text']);
        $this->assertSame('https://example.org', $row['homepage']);
        $this->assertSame('U', $row['status']);
    }

    #[Test]
    public function editPageKeepsInputOnValidationError(): void
    {
        $id = $this->insertEntry(['name' => 'Alt', 'text' => 'Alter Text']);
        $_POST = $this->editPost($id, ['edit_name' => '', 'edit_text' => 'Geänderter Text bleibt']);

        $html = $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertSame('Bitte geben Sie einen Namen ein.', $this->flash()['text'] ?? '');
        $this->assertStringContainsString('Geänderter Text bleibt', $html);
        $this->assertStringContainsString('form-control is-invalid', $html);
        $this->assertSame('Alt', $this->row($id)['name']);
    }

    #[Test]
    public function editPageReportsSeveralErrors(): void
    {
        $id = $this->insertEntry();
        $_POST = $this->editPost($id, ['edit_name' => '', 'edit_email' => 'kein-mail', 'edit_homepage' => 'javascript:alert(1)']);

        $html = $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('Bitte prüfen Sie die markierten Felder.', $this->flash()['text'] ?? '');
        $this->assertStringContainsString('Die E-Mail-Adresse ist ungültig.', $html);
        $this->assertStringContainsString('Die Homepage-Adresse ist ungültig.', $html);
    }

    #[Test]
    public function editPageIgnoresStatusWithoutReleaseRight(): void
    {
        $id = $this->insertEntry(['status' => 'U']);
        $_POST = $this->editPost($id, ['edit_status' => 'R']);

        $this->render('edit.inc.php', ['admin_session' => ['entries' => 'Y', 'release' => 'N']]);

        $this->assertNotNull($this->redirect);
        $this->assertSame('U', $this->row($id)['status']);
    }

    #[Test]
    public function editPageKeepsIconAndSmileysWhenDisabled(): void
    {
        $id = $this->insertEntry(['icon' => 'happy1', 'smilies' => 'Y']);
        $post = $this->editPost($id);
        unset($post['edit_icon'], $post['edit_smilies']);
        $_POST = $post;

        $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS, 'config_icons' => 'N', 'config_smilies' => 'N']);

        $row = $this->row($id);
        $this->assertSame('happy1', $row['icon']);
        $this->assertSame('Y', $row['smilies']);
    }

    #[Test]
    public function editPageRejectsUnknownIcon(): void
    {
        $id = $this->insertEntry(['icon' => 'happy1']);
        $_POST = $this->editPost($id, ['edit_icon' => '../../assets/x']);

        $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('no', $this->row($id)['icon']);
    }

    #[Test]
    public function editPageAsksBeforeDeleting(): void
    {
        $id = $this->insertEntry(['name' => 'Kredit-Express']);
        $_POST = ['action' => 'confirm_delete', 'edit_id' => (string) $id, 'csrf_token' => generateCsrfToken()];

        $html = $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('id="pbDeleteQuestion"', $html);
        $this->assertStringContainsString('id="pbDeleteConfirm"', $html);
        $this->assertStringContainsString('id="pbDeleteCancel"', $html);
        $this->assertStringContainsString('value="delete"', $html);
        $this->assertStringNotContainsString('id="pb_edit_name"', $html);
        $this->assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_entries WHERE id = {$id}")->fetchColumn());
    }

    #[Test]
    public function editPageDeletesAndReturnsToList(): void
    {
        $id = $this->insertEntry(['name' => 'Tim und Mara']);
        $_POST = ['action' => 'delete', 'edit_id' => (string) $id, 'csrf_token' => generateCsrfToken()];

        $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('?page=entries', $this->redirect);
        $this->assertSame('Der Eintrag von „Tim und Mara“ wurde gelöscht.', $this->flash()['text'] ?? '');
        $this->assertSame(0, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_entries WHERE id = {$id}")->fetchColumn());
    }

    #[Test]
    public function editPageDeleteReturnsToReleasePage(): void
    {
        $id = $this->insertEntry(['status' => 'U']);
        $_POST = ['action' => 'delete', 'edit_id' => (string) $id, 'return' => 'release', 'csrf_token' => generateCsrfToken()];

        $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('?page=release', $this->redirect);
    }

    #[Test]
    public function editPageWithInvalidTokenKeepsInput(): void
    {
        $id = $this->insertEntry(['text' => 'Alter Text']);
        $_POST = $this->editPost($id, ['edit_text' => 'Text aus dem zweiten Tab', 'csrf_token' => 'veraltet']);

        $html = $this->render('edit.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertSame(pb_admin_csrf_message(), $this->flash()['text'] ?? '');
        $this->assertStringContainsString('Text aus dem zweiten Tab', $html);
        $this->assertSame('Alter Text', $this->row($id)['text']);
    }

    // ========================================================================
    // Freischalten
    // ========================================================================

    #[Test]
    public function releasePageDeniesWithoutPermission(): void
    {
        $html = $this->render('release.inc.php', ['admin_session' => ['release' => 'N', 'entries' => 'Y']]);

        $this->assertStringContainsString('keine Berechtigung', $html);
    }

    #[Test]
    public function releasePageShowsEmptyMessage(): void
    {
        $this->insertEntry(['status' => 'R']);

        $html = $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('Keine Einträge warten auf Freischaltung.', $html);
        $this->assertStringNotContainsString('id="pbReleaseSelected"', $html);
    }

    #[Test]
    public function releasePageListsPendingEntriesWithCheckboxes(): void
    {
        $a = $this->insertEntry(['status' => 'U', 'name' => 'Ines aus Leipzig']);
        $b = $this->insertEntry(['status' => 'U', 'name' => 'Kredit-Express']);
        $this->insertEntry(['status' => 'R', 'name' => 'Schon öffentlich']);

        $html = $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame(2, substr_count($html, 'name="ids[]"'));
        $this->assertStringContainsString('id="pbReleaseCheck' . $a . '"', $html);
        $this->assertStringContainsString('id="pbReleaseCheck' . $b . '"', $html);
        $this->assertStringContainsString('id="pbReleaseSelected"', $html);
        $this->assertStringContainsString('id="pbDeleteSelected"', $html);
        $this->assertStringContainsString('id="pbEntryEdit' . $a . '"', $html);
        $this->assertStringNotContainsString('Schon öffentlich', $html);
        $this->assertStringNotContainsString('Alle freischalten', $html);
        $this->assertMatchesRegularExpression('~Es warten\s*<span[^>]*>2</span>\s*Einträge auf Freischaltung\.~', $html);
    }

    #[Test]
    public function releasePageWithoutEntriesRightHasNoEditButton(): void
    {
        $id = $this->insertEntry(['status' => 'U']);

        $html = $this->render('release.inc.php', ['admin_session' => ['release' => 'Y', 'entries' => 'N']]);

        $this->assertStringNotContainsString('id="pbEntryEdit' . $id . '"', $html);
        $this->assertStringContainsString('id="pbDeleteSelected"', $html);
    }

    #[Test]
    public function releaseWithoutSelectionShowsMessage(): void
    {
        $this->insertEntry(['status' => 'U']);
        $_POST = ['action' => 'release', 'csrf_token' => generateCsrfToken()];

        $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertSame('Bitte wählen Sie mindestens einen Eintrag aus.', $this->flash()['text'] ?? '');
        $this->assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_entries WHERE status = 'U'")->fetchColumn());
    }

    #[Test]
    public function releaseSelectedReleasesOnlySelection(): void
    {
        $keep = $this->insertEntry(['status' => 'U']);
        $release = $this->insertEntry(['status' => 'U']);
        $_POST = ['action' => 'release', 'ids' => [(string) $release], 'csrf_token' => generateCsrfToken()];

        $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('?page=release', $this->redirect);
        $this->assertSame('Ein Eintrag wurde freigeschaltet. Er steht jetzt im Gästebuch.', $this->flash()['text'] ?? '');
        $this->assertSame('U', $this->row($keep)['status']);
        $this->assertSame('R', $this->row($release)['status']);
    }

    #[Test]
    public function releaseSeveralShowsPlural(): void
    {
        $a = $this->insertEntry(['status' => 'U']);
        $b = $this->insertEntry(['status' => 'U']);
        $_POST = ['action' => 'release', 'ids' => [(string) $a, (string) $b, 'x', '-3'], 'csrf_token' => generateCsrfToken()];

        $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('2 Einträge wurden freigeschaltet. Sie stehen jetzt im Gästebuch.', $this->flash()['text'] ?? '');
    }

    #[Test]
    public function deleteSelectedAsksFirst(): void
    {
        $spam = $this->insertEntry(['status' => 'U', 'name' => 'Kredit-Express', 'text' => 'Schnelle Kredite ohne Schufa!']);
        $_POST = ['action' => 'delete_confirm', 'ids' => [(string) $spam], 'csrf_token' => generateCsrfToken()];

        $html = $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertStringContainsString('Diesen Eintrag wirklich löschen?', $html);
        $this->assertStringContainsString('erscheint nie im Gästebuch', $html);
        $this->assertStringContainsString('Kredit-Express', $html);
        $this->assertStringContainsString('Schnelle Kredite ohne Schufa!', $html);
        $this->assertStringContainsString('name="ids[]" value="' . $spam . '"', $html);
        $this->assertStringContainsString('id="pbDeleteConfirm"', $html);
        $this->assertStringContainsString('id="pbDeleteCancel"', $html);
        $this->assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_entries WHERE id = {$spam}")->fetchColumn());
    }

    #[Test]
    public function deleteRemovesOnlyPendingEntries(): void
    {
        $spam = $this->insertEntry(['status' => 'U']);
        $public = $this->insertEntry(['status' => 'R']);
        $_POST = ['action' => 'delete', 'ids' => [(string) $spam, (string) $public], 'csrf_token' => generateCsrfToken()];

        $this->render('release.inc.php', ['admin_session' => ['release' => 'Y', 'entries' => 'N']]);

        $this->assertSame('?page=release', $this->redirect);
        $this->assertSame('Ein Eintrag wurde gelöscht.', $this->flash()['text'] ?? '');
        $this->assertSame(0, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_entries WHERE id = {$spam}")->fetchColumn());
        $this->assertSame(1, (int) self::$pdo->query("SELECT COUNT(*) FROM pb_entries WHERE id = {$public}")->fetchColumn());
    }

    #[Test]
    public function releaseWithInvalidTokenChangesNothing(): void
    {
        $id = $this->insertEntry(['status' => 'U']);
        $_POST = ['action' => 'release', 'ids' => [(string) $id], 'csrf_token' => 'falsch'];

        $html = $this->render('release.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertSame(pb_admin_csrf_message(), $this->flash()['text'] ?? '');
        $this->assertSame('U', $this->row($id)['status']);
        $this->assertStringContainsString('id="pbReleaseCheck' . $id . '" class="form-check-input" type="checkbox" name="ids[]" value="' . $id . '" checked', $html);
    }

    // ========================================================================
    // Antwort
    // ========================================================================

    #[Test]
    public function answerPageDeniesWithoutPermission(): void
    {
        $html = $this->render('statement.inc.php', ['admin_session' => ['entries' => 'N']]);

        $this->assertStringContainsString('keine Berechtigung', $html);
    }

    #[Test]
    public function answerPageRejectsUnknownIds(): void
    {
        $this->assertStringContainsString('ID unbekannt', $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]));

        $_GET['id'] = '999';
        $this->assertStringContainsString('Diesen Eintrag gibt es nicht mehr.', $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]));
    }

    #[Test]
    public function answerPageShowsFormAndPreview(): void
    {
        $id = $this->insertEntry(['text' => 'Schöne Ferien!']);
        $_GET['id'] = (string) $id;

        $html = $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertStringContainsString('Antwort schreiben', $html);
        $this->assertStringContainsString('Schöne Ferien!', $html);
        $this->assertStringContainsString('id="pb_statement"', $html);
        $this->assertStringContainsString('id="pbAnswerSubmit"', $html);
        $this->assertStringContainsString('id="pbAnswerPreview"', $html);
        $this->assertStringNotContainsString('id="pbAnswerDelete"', $html);
        $this->assertStringContainsString('„Antwort von Anke:“', $html);
        $this->assertStringNotContainsString('Statement', $html);
    }

    #[Test]
    public function answerIsSavedTrimmedUnderCurrentAdmin(): void
    {
        $id = $this->insertEntry(['statement' => 'Alt', 'statement_by' => 'Anke']);
        $_POST = ['action' => 'save', 'id' => (string) $id, 'edit_statement' => "  Danke, Jannik hier.  \n", 'csrf_token' => generateCsrfToken()];

        $this->render('statement.inc.php', ['admin_session' => self::MODERATOR]);

        $this->assertSame('?page=statement&id=' . $id, $this->redirect);
        $this->assertSame('Die Antwort wurde gespeichert. Sie erscheint im Gästebuch unter dem Eintrag.', $this->flash()['text'] ?? '');
        $row = $this->row($id);
        $this->assertSame('Danke, Jannik hier.', $row['statement']);
        $this->assertSame('Jannik', $row['statement_by']);
    }

    #[Test]
    public function blankAnswerRemovesAnswerAndAuthor(): void
    {
        $id = $this->insertEntry(['statement' => 'Alt', 'statement_by' => 'Anke']);
        $_POST = ['action' => 'save', 'id' => (string) $id, 'edit_statement' => '   ', 'csrf_token' => generateCsrfToken()];

        $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('Die Antwort wurde entfernt.', $this->flash()['text'] ?? '');
        $row = $this->row($id);
        $this->assertSame('', $row['statement']);
        $this->assertSame('', $row['statement_by']);
    }

    #[Test]
    public function deleteButtonRemovesAnswer(): void
    {
        $id = $this->insertEntry(['statement' => 'Alt', 'statement_by' => 'Anke']);
        $_GET['id'] = (string) $id;
        $this->assertStringContainsString('id="pbAnswerDelete"', $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]));

        $_POST = ['action' => 'delete', 'id' => (string) $id, 'edit_statement' => 'Alt', 'csrf_token' => generateCsrfToken()];
        $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertSame('Die Antwort wurde entfernt.', $this->flash()['text'] ?? '');
        $this->assertSame('', $this->row($id)['statement']);
    }

    #[Test]
    public function emptyAnswerWithoutExistingAnswerSavesNothing(): void
    {
        $id = $this->insertEntry();
        $_POST = ['action' => 'save', 'id' => (string) $id, 'edit_statement' => '', 'csrf_token' => generateCsrfToken()];

        $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertSame('info', $this->flash()['type'] ?? '');
    }

    #[Test]
    public function answerPageWarnsAboutOtherAuthor(): void
    {
        $id = $this->insertEntry(['statement' => 'Von Anke', 'statement_by' => 'Anke']);
        $_GET['id'] = (string) $id;

        $html = $this->render('statement.inc.php', ['admin_session' => self::MODERATOR]);

        $this->assertStringContainsString('id="pbAnswerOtherAuthor"', $html);
        $this->assertStringContainsString('Die Antwort stammt von <b>Anke</b>.', $html);
        $this->assertStringContainsString('Antwort bearbeiten', $html);
    }

    #[Test]
    public function answerWithInvalidTokenKeepsText(): void
    {
        $id = $this->insertEntry();
        $_POST = ['action' => 'save', 'id' => (string) $id, 'edit_statement' => 'Bleibt stehen', 'csrf_token' => 'veraltet'];

        $html = $this->render('statement.inc.php', ['admin_session' => self::FULL_RIGHTS]);

        $this->assertNull($this->redirect);
        $this->assertSame(pb_admin_csrf_message(), $this->flash()['text'] ?? '');
        $this->assertStringContainsString('Bleibt stehen</textarea>', $html);
        $this->assertSame('', $this->row($id)['statement']);
    }

    // ========================================================================
    // Blätterleiste
    // ========================================================================

    #[Test]
    public function pagerShowsNothingForSinglePage(): void
    {
        $this->assertSame('', $this->renderPager(10, 0));
    }

    #[Test]
    public function pagerShowsNumbersAndGermanLabels(): void
    {
        $html = $this->renderPager(45, 15, 'pbEntriesPagerBottom');

        $this->assertStringContainsString('id="pbEntriesPagerBottom"', $html);
        $this->assertStringContainsString('Anfang', $html);
        $this->assertStringContainsString('Ende', $html);
        $this->assertStringNotContainsString('Beginn', $html);
        $this->assertStringContainsString('<li class="page-item active" aria-current="page"><span class="page-link">2</span>', $html);
        $this->assertStringContainsString('href="?page=entries">1</a>', $html);
        $this->assertStringContainsString('href="?page=entries&amp;tmp_start=30">3</a>', $html);
        $this->assertMatchesRegularExpression('~Vorherige Seite</a>~', $html);
    }

    #[Test]
    public function pagerDisablesNextOnLastPage(): void
    {
        $html = $this->renderPager(45, 30);

        $this->assertStringContainsString('<span class="page-link">Nächste Seite &rsaquo;</span>', $html);
        $this->assertStringContainsString('<span class="page-link">Ende &raquo;</span>', $html);
    }

    // ========================================================================
    // Helfer
    // ========================================================================

    protected function setUp(): void
    {
        $GLOBALS['pdo'] = self::$pdo;
        $_POST = [];
        $_GET = [];
        $this->redirect = null;
        unset($_SESSION['pb_flash']);
        self::$pdo->exec('DELETE FROM pb_entries');
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        unset($_SESSION['pb_flash']);
    }

    /**
     * Bindet eine Seite des AdminCenters ein und liefert die Ausgabe.
     * Eine Weiterleitung landet in $this->redirect.
     *
     * @param array<string, mixed> $vars Variablen aus index.php
     */
    private function render(string $file, array $vars = []): string
    {
        $pdo = self::$pdo;
        $pb_admin = 'pb_admins';
        $pb_entries = 'pb_entries';
        $pb_config = 'pb_config';
        $config_icons = 'Y';
        $config_text_format = 'Y';
        $config_smilies = 'Y';
        $config_date = 'd.m.Y';
        $config_time = 'H:i';
        $config_statements = 'Y';
        $admin_session = [];
        $loggedIn = true;
        $loginName = '';
        extract($vars);

        $this->redirect = null;
        unset($_SESSION['pb_flash']);
        ob_start();

        try {
            include POWERBOOK_ROOT . '/pb_inc/admincenter/' . $file;
        } catch (\PbAdminRedirect $redirect) {
            $this->redirect = $redirect->location;
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    private function renderPager(int $count, int $start, string $pagerId = ''): string
    {
        $count_pages = $count;
        $tmp_start = $start;
        $perPage = 15;

        ob_start();
        include POWERBOOK_ROOT . '/pb_inc/admincenter/pages.inc.php';

        return (string) ob_get_clean();
    }

    /**
     * Bindet entry.inc.php ein und liefert die gesetzten Variablen.
     *
     * @param array<string, mixed> $entryValues
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function entryVars(array $entryValues, array $config = []): array
    {
        $entry = array_merge([
            'id' => 1, 'name' => 'Gast', 'email' => '', 'text' => 'Text', 'date' => 1_700_000_000,
            'homepage' => '', 'ip' => '192.0.2.1', 'status' => 'R', 'icon' => 'no', 'smilies' => 'Y',
            'statement' => '', 'statement_by' => '',
        ], $entryValues);
        $config_icons = 'Y';
        $config_text_format = 'Y';
        $config_smilies = 'Y';
        $config_date = 'd.m.Y';
        $config_time = 'H:i';
        extract($config);

        include POWERBOOK_ROOT . '/pb_inc/admincenter/entry.inc.php';

        return get_defined_vars();
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function editPost(int $id, array $overrides = []): array
    {
        return array_merge([
            'action' => 'update',
            'edit_id' => (string) $id,
            'csrf_token' => generateCsrfToken(),
            'edit_name' => 'Gast',
            'edit_email' => '',
            'edit_text' => 'Text',
            'edit_homepage' => '',
            'edit_icon' => 'no',
            'edit_status' => 'R',
            'edit_smilies' => 'Y',
        ], $overrides);
    }

    /**
     * @return array{type: string, text: string}|null
     */
    private function flash(): ?array
    {
        $flash = $_SESSION['pb_flash'] ?? null;

        return is_array($flash) ? $flash : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertEntry(array $data = []): int
    {
        $row = array_merge([
            'name' => 'Gast', 'email' => '', 'text' => 'Ein Eintrag.', 'date' => time(), 'homepage' => '',
            'ip' => '192.0.2.1', 'status' => 'R', 'icon' => 'no', 'smilies' => 'Y', 'statement' => '', 'statement_by' => '',
        ], $data);
        $columns = array_keys($row);
        $stmt = self::$pdo->prepare('INSERT INTO pb_entries (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
        $stmt->execute(array_values($row));

        return (int) self::$pdo->lastInsertId();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id): array
    {
        $stmt = self::$pdo->prepare('SELECT * FROM pb_entries WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    }
}
