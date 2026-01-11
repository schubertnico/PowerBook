<?php
/**
 * PowerBook - PHP Guestbook System
 * Admin Email Notifications (Legacy Support)
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 *
 * Note: This file is kept for backward compatibility.
 * The email functionality has been moved into the respective admin files.
 */

declare(strict_types=1);

// This file is now largely deprecated as email sending has been integrated
// directly into the admin management files with proper header injection protection.
// The functions below are kept for any legacy code that might still include this file.

// Variables that might be set from calling code
$email = $email ?? '';
$add_email = $add_email ?? '';
$add_name = $add_name ?? '';
$edit_email = $edit_email ?? '';
$edit_name = $edit_name ?? '';
$admin_name = $admin_name ?? '';
$config_admin_url = $config_admin_url ?? '';

// Sanitize email addresses for header injection prevention
$safe_add_email = sanitizeEmailHeader($add_email);
$safe_edit_email = sanitizeEmailHeader($edit_email);

$headers = "From: PowerBook <noreply@powerbook.local>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if ($email === 'admin_added' && !empty($safe_add_email)) {
    $tempPassword = $time ?? bin2hex(random_bytes(8));
    $add_config_display = ($add_config ?? 'N') === 'Y' ? 'Ja' : 'Nein';
    $add_admins_display = ($add_admins ?? 'N') === 'Y' ? 'Ja' : 'Nein';
    $add_entries_display = ($add_entries ?? 'N') === 'Y' ? 'Ja' : 'Nein';
    $add_release_display = ($add_release ?? 'N') === 'Y' ? 'Ja' : 'Nein';

    $body = "Hallo!\n\n";
    $body .= e($admin_name) . " hat Sie zur Admin-Datenbank von PowerBook hinzugefügt.\n\n";
    $body .= "Ihr Name: " . e($add_name) . "\n";
    $body .= "Ihre E-Mail: " . e($add_email) . "\n";
    $body .= "Ihr Passwort: {$tempPassword}\n";
    $body .= "Konfiguration: {$add_config_display}\n";
    $body .= "Admin-Verwaltung: {$add_admins_display}\n";
    $body .= "Eintrag-Verwaltung: {$add_entries_display}\n";
    $body .= "Einträge Freischalten: {$add_release_display}\n\n";
    $body .= "Geben Sie diese Informationen niemals weiter!\n";
    $body .= "Ändern Sie bitte auch Ihr Passwort im AdminCenter";
    if (!empty($config_admin_url)) {
        $body .= " (" . e($config_admin_url) . ")";
    }
    $body .= ".\n\n";
    $body .= "--------------------------------------------------------\n";
    $body .= "PowerBook - PHP Guestbook System\n";
    $body .= "https://github.com/schubertnico/PowerBook.git\n\n";
    $body .= "DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!";

    sendEmail($safe_add_email, "PowerBook: AdminCenter", $body, $headers, 'Admin Added');

} elseif ($email === 'admin_edited' && !empty($safe_edit_email)) {
    $edit_config_display = ($edit_config ?? 'N') === 'Y' ? 'Ja' : 'Nein';
    $edit_admins_display = ($edit_admins ?? 'N') === 'Y' ? 'Ja' : 'Nein';
    $edit_entries_display = ($edit_entries ?? 'N') === 'Y' ? 'Ja' : 'Nein';
    $edit_release_display = ($edit_release ?? 'N') === 'Y' ? 'Ja' : 'Nein';

    $body = "Hallo!\n\n";
    $body .= e($admin_name) . " hat Ihr Profil im AdminCenter von PowerBook bearbeitet.\n\n";
    $body .= "Ihr (neuer) Name: " . e($edit_name) . "\n";
    $body .= "Ihre (neue) E-Mail: " . e($edit_email) . "\n";
    if (!empty($edit_password1)) {
        $body .= "Ihr (neues) Passwort: {$edit_password1}\n";
    }
    $body .= "Konfiguration: {$edit_config_display}\n";
    $body .= "Admin-Verwaltung: {$edit_admins_display}\n";
    $body .= "Eintrag-Verwaltung: {$edit_entries_display}\n";
    $body .= "Einträge Freischalten: {$edit_release_display}\n\n";
    $body .= "Geben Sie diese Informationen niemals weiter!\n";
    if (!empty($config_admin_url)) {
        $body .= "Die URL zum AdminCenter ist: " . e($config_admin_url) . "\n";
    }
    $body .= "\n--------------------------------------------------------\n";
    $body .= "PowerBook - PHP Guestbook System\n";
    $body .= "https://github.com/schubertnico/PowerBook.git\n\n";
    $body .= "DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!";

    sendEmail($safe_edit_email, "PowerBook: AdminCenter", $body, $headers, 'Admin Edited');

} elseif ($email === 'admin_deleted' && !empty($safe_edit_email)) {
    $body = "Hallo!\n\n";
    $body .= e($admin_name) . " hat Sie aus der Admin-Datenbank von PowerBook gelöscht.\n";
    $body .= "Sie sind nicht mehr berechtigt, mit PowerBook zu arbeiten.\n\n";
    $body .= "--------------------------------------------------------\n";
    $body .= "PowerBook - PHP Guestbook System\n";
    $body .= "https://github.com/schubertnico/PowerBook.git\n\n";
    $body .= "DIESE E-MAIL WURDE AUTOMATISCH GENERIERT!";

    sendEmail($safe_edit_email, "PowerBook: AdminCenter", $body, $headers, 'Admin Deleted');
}
