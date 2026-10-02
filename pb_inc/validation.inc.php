<?php

/**
 * PowerBook - PHP Guestbook System
 * Prüffunktionen
 *
 * pb_normalize_entry() und pb_validate_entry() prüfen einen Gästebucheintrag
 * – in der Vorschau und beim Speichern mit denselben Regeln. Die übrigen
 * Funktionen nutzt das AdminCenter.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.inc.php';

/** Höchstlängen eines Eintrags (Zeichen, Zeilenumbruch = 1 Zeichen). */
const PB_MAX_NAME = 100;
const PB_MAX_EMAIL = 250;
const PB_MAX_URL = 200;
const PB_MAX_TEXT = 5000;

/**
 * Formularwerte eines Eintrags vereinheitlichen (Rohwerte, nicht maskiert).
 *
 * Name einzeilig, Text mit \n als Zeilenumbruch (ein Zeichen), Steuerzeichen
 * entfernt, Icon „no“ für „Kein Icon“, Smileys „Y“ oder „N“.
 *
 * @param array<array-key, mixed> $input z. B. $_POST
 *
 * @return array{name: string, email: string, url: string, text: string, icon: string, smilies: string}
 */
function pb_normalize_entry(array $input): array
{
    $string = static fn (string $key): string => is_scalar($input[$key] ?? null) ? (string) $input[$key] : '';
    $clean = static function (string $value): string {
        if (!mb_check_encoding($value, 'UTF-8')) {
            // Wie mb_convert_encoding($value, 'UTF-8', 'UTF-8'), liefert aber sicher einen String.
            $value = mb_scrub($value, 'UTF-8');
        }

        return $value;
    };

    $name = $clean($string('name'));
    $name = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name) ?? '';
    $name = trim(preg_replace('/\s{2,}/u', ' ', $name) ?? '');

    $email = $clean($string('email2'));
    $email = preg_replace('/[\x00-\x20\x7F]+/', '', $email) ?? '';

    $url = $clean($string('url'));
    $url = trim(preg_replace('/[\x00-\x1F\x7F]+/', '', $url) ?? '');

    $text = $clean($string('text'));
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';
    $text = trim($text);

    $icon = $string('icon');
    $icon = $icon === '' ? 'no' : $icon;

    return [
        'name' => $name,
        'email' => $email,
        'url' => $url,
        'text' => $text,
        'icon' => $icon,
        'smilies' => $string('smilies2') === 'Y' ? 'Y' : 'N',
    ];
}

/**
 * Prüft einen Eintrag (Werte aus pb_normalize_entry()).
 *
 * @param array{name: string, email: string, url: string, text: string, icon: string, smilies?: string} $data
 * @param bool $iconsEnabled Icon-Auswahl ist eingeschaltet
 *
 * @return array<string, string> Fehlermeldungen je Feld (name, email, url, text, icon)
 */
function pb_validate_entry(array $data, bool $iconsEnabled = true): array
{
    $errors = [
        'name' => pb_validate_entry_name($data['name']),
        'email' => pb_validate_entry_email($data['email']),
        'url' => pb_validate_entry_url($data['url']),
        'text' => pb_validate_entry_text($data['text']),
        'icon' => $iconsEnabled ? pb_validate_entry_icon($data['icon']) : null,
    ];

    return array_filter($errors, static fn (?string $error): bool => $error !== null);
}

/**
 * Prüft den Namen (Pflichtfeld). Gibt eine Fehlermeldung oder null zurück.
 */
function pb_validate_entry_name(string $name): ?string
{
    $nameLength = mb_strlen($name);
    if ($nameLength === 0) {
        return 'Bitte geben Sie Ihren Namen ein.';
    }
    if ($nameLength > PB_MAX_NAME) {
        return sprintf('Der Name darf höchstens %d Zeichen lang sein (jetzt %d).', PB_MAX_NAME, $nameLength);
    }

    return null;
}

/**
 * Prüft die E-Mail-Adresse (optional). Gibt eine Fehlermeldung oder null zurück.
 */
function pb_validate_entry_email(string $email): ?string
{
    if ($email === '') {
        return null;
    }
    if (mb_strlen($email) > PB_MAX_EMAIL) {
        return sprintf('Die E-Mail-Adresse darf höchstens %d Zeichen lang sein.', PB_MAX_EMAIL);
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return 'Bitte prüfen Sie die E-Mail-Adresse (Beispiel: name@example.org) oder lassen Sie das Feld leer.';
    }

    return null;
}

/**
 * Prüft die Homepage (optional). Gibt eine Fehlermeldung oder null zurück.
 */
function pb_validate_entry_url(string $url): ?string
{
    if ($url === '') {
        return null;
    }
    $url = pb_normalize_url($url);
    if ($url === '') {
        return 'Bitte geben Sie eine vollständige Internetadresse ein (Beispiel: https://www.example.org) oder lassen Sie das Feld leer.';
    }
    if (mb_strlen($url) > PB_MAX_URL) {
        return sprintf('Die Homepage-Adresse darf höchstens %d Zeichen lang sein.', PB_MAX_URL);
    }

    return null;
}

/**
 * Prüft den Text (Pflichtfeld). Gibt eine Fehlermeldung oder null zurück.
 */
function pb_validate_entry_text(string $text): ?string
{
    $textLength = mb_strlen($text);
    if ($textLength === 0) {
        return 'Bitte schreiben Sie einen Text.';
    }
    if ($textLength > PB_MAX_TEXT) {
        return sprintf('Der Text darf höchstens %s Zeichen lang sein (jetzt %s).', number_format(PB_MAX_TEXT, 0, ',', '.'), number_format($textLength, 0, ',', '.'));
    }

    return null;
}

/**
 * Prüft das Icon („no“ = kein Icon). Gibt eine Fehlermeldung oder null zurück.
 */
function pb_validate_entry_icon(string $icon): ?string
{
    if ($icon !== 'no' && pb_icon_label($icon) === null) {
        return 'Bitte wählen Sie eines der angebotenen Icons.';
    }

    return null;
}

/**
 * Validate admin login data
 *
 * @return array<string, string> Errors keyed by field name
 */
function validateAdminLogin(string $name, string $password): array
{
    $errors = [];

    if (empty(trim($name))) {
        $errors['name'] = 'Name ist erforderlich';
    }

    if (empty($password)) {
        $errors['password'] = 'Passwort ist erforderlich';
    }

    return $errors;
}

/**
 * Validate email address using filter_var
 *
 * @return array<string, string> Errors keyed by field name
 */
function validateEmail(string $email, bool $required = false): array
{
    $errors = [];

    $email = trim($email);

    if ($required && empty($email)) {
        $errors['email'] = 'E-Mail-Adresse ist erforderlich';

        return $errors;
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ungültige E-Mail-Adresse';
    }

    return $errors;
}

/**
 * Validate admin data for creation/editing
 *
 * @return array<string, string> Errors keyed by field name
 */
function validateAdminData(
    string $name,
    string $email,
    ?string $password1 = null,
    ?string $password2 = null,
    bool $passwordRequired = false
): array {
    $errors = [];

    // Name validation
    if (empty(trim($name))) {
        $errors['name'] = 'Name ist erforderlich';
    }

    // Email validation
    if (empty(trim($email))) {
        $errors['email'] = 'E-Mail-Adresse ist erforderlich';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Ungültige E-Mail-Adresse';
    }

    // Password validation
    if ($passwordRequired && empty($password1)) {
        $errors['password'] = 'Passwort ist erforderlich';
    } elseif (!empty($password1) && $password1 !== $password2) {
        $errors['password'] = 'Die Passwörter stimmen nicht überein';
    }

    return $errors;
}

/**
 * Validate password strength
 *
 * @param string $password The password to validate
 * @param int $minLength Minimum password length (default: 8)
 * @param bool $required Whether password is required
 *
 * @return array<string, string> Errors keyed by field name
 */
function validatePassword(string $password, int $minLength = 8, bool $required = false): array
{
    $errors = [];

    if ($required && empty($password)) {
        $errors['password'] = 'Passwort ist erforderlich';

        return $errors;
    }

    if (!empty($password)) {
        if (strlen($password) < $minLength) {
            $errors['password'] = "Passwort muss mindestens {$minLength} Zeichen lang sein";
        }
    }

    return $errors;
}

/**
 * Validate password confirmation match
 *
 * @return array<string, string> Errors keyed by field name
 */
function validatePasswordConfirmation(string $password1, string $password2): array
{
    $errors = [];

    if (!empty($password1) && $password1 !== $password2) {
        $errors['password_confirm'] = 'Die Passwörter stimmen nicht überein';
    }

    return $errors;
}
