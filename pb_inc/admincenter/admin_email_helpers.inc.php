<?php

/**
 * PowerBook - PHP Guestbook System
 * Admin Email Helper Functions (BUG-006 Fix)
 *
 * Dient als Helper-Datei zur Auslagerung der E-Mail-Funktionen,
 * damit die Aufrufer (admins.inc.php) per require_once includen
 * koennen, ohne dass mehrfaches include zu Redeclaration-Fatals fuehrt.
 * Funktionen sind auf Root-Scope deklariert und dadurch vor der ersten
 * Verwendung in admins.inc.php verfuegbar.
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 */

declare(strict_types=1);

// Helper function to format permission as Ja/Nein
function formatPermission(string $value): string
{
    return $value === 'Y' ? 'Ja' : 'Nein';
}

/**
 * Helper function to format admin permissions block.
 *
 * @param array<string, string> $data
 */
function formatAdminPermissions(array $data): string
{
    return 'Konfiguration: ' . formatPermission($data['config']) . "\n"
         . 'Admin-Verwaltung: ' . formatPermission($data['admins']) . "\n"
         . 'Eintrag-Verwaltung: ' . formatPermission($data['entries']) . "\n"
         . 'Einträge Freischalten: ' . formatPermission($data['release']) . "\n";
}

// Helper function to get email footer
function getEmailFooter(): string
{
    return "\n--------------------------------------------------------\n"
         . "PowerBook - PHP Guestbook System\n"
         . "https://github.com/schubertnico/PowerBook.git\n\n"
         . 'DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!';
}

/**
 * Helper function to send admin notification emails.
 *
 * IMP-008: Rueckgabewert geaendert von void auf bool, damit der Aufrufer
 * (admins.inc.php) auf einen fehlgeschlagenen Mail-Versand reagieren kann
 * (z. B. um das Initial-Passwort einmalig im UI anzuzeigen).
 *
 * @param array<string, string> $data
 *
 * @return bool true bei erfolgreichem Versand, false bei Fehler oder ungueltigen Daten
 */
function sendAdminEmail(string $type, array $data): bool
{
    $to = sanitizeEmailHeader($data['to'] ?? '');
    if (empty($to)) {
        return false;
    }

    $subject = 'PowerBook: AdminCenter';
    $headers = "From: PowerBook <noreply@powerbook.local>\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $body = match ($type) {
        'added' => buildAddedEmailBody($data),
        'edited' => buildEditedEmailBody($data),
        'deleted' => buildDeletedEmailBody($data),
        default => ''
    };

    if (empty($body)) {
        return false;
    }

    $body .= getEmailFooter();

    return sendEmail($to, $subject, $body, $headers, "Admin {$type}");
}

/**
 * Build email body for added admin.
 *
 * @param array<string, string> $data
 */
function buildAddedEmailBody(array $data): string
{
    $body = "Hallo!\n\n";
    $body .= "{$data['by']} hat Sie zur Admin-Datenbank von PowerBook hinzugefügt.\n\n";
    $body .= "Ihr Name: {$data['name']}\n";
    $body .= "Ihre E-Mail: {$data['email']}\n";
    $body .= "Ihr Passwort: {$data['password']}\n";
    $body .= formatAdminPermissions($data) . "\n";
    $body .= "Geben Sie diese Informationen niemals weiter!\n";
    $body .= 'Ändern Sie bitte auch Ihr Passwort im AdminCenter';
    if (!empty($data['admin_url'])) {
        $body .= " ({$data['admin_url']})";
    }
    $body .= ".\n";

    return $body;
}

/**
 * Build email body for edited admin.
 *
 * @param array<string, string> $data
 */
function buildEditedEmailBody(array $data): string
{
    $body = "Hallo!\n\n";
    $body .= "{$data['by']} hat Ihr Profil im AdminCenter von PowerBook bearbeitet.\n\n";
    $body .= "Ihr (neuer) Name: {$data['name']}\n";
    $body .= "Ihre (neue) E-Mail: {$data['email']}\n";
    if (!empty($data['password'])) {
        $body .= "Ihr (neues) Passwort: {$data['password']}\n";
    }
    $body .= formatAdminPermissions($data) . "\n";
    $body .= "Geben Sie diese Informationen niemals weiter!\n";
    if (!empty($data['admin_url'])) {
        $body .= "Die URL zum AdminCenter ist: {$data['admin_url']}\n";
    }

    return $body;
}

/**
 * Build email body for deleted admin.
 *
 * @param array<string, string> $data
 */
function buildDeletedEmailBody(array $data): string
{
    return "Hallo!\n\n"
         . "{$data['by']} hat Sie aus der Admin-Datenbank von PowerBook gelöscht.\n"
         . "Sie sind nicht mehr berechtigt, mit PowerBook zu arbeiten.\n";
}
