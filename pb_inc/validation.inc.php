<?php
/**
 * PowerBook - PHP Guestbook System
 * Validation Functions
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

/**
 * Validate guestbook entry data
 *
 * @return array<string, string> Errors keyed by field name
 */
function validateGuestbookEntry(string $name, string $text, string $email): array
{
    $errors = [];

    if (strlen(trim($name)) === 0) {
        $errors['name'] = 'Bitte einen Name eingeben!';
    }

    if (strlen(trim($text)) === 0) {
        $errors['text'] = 'Bitte einen Text eingeben!';
    }

    if (strlen($email) >= 1 && (!str_contains($email, '@') || !str_contains($email, '.'))) {
        $errors['email'] = 'Ungueltige eMail-Adresse!';
    }

    return $errors;
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
        $errors['email'] = 'Ungueltige E-Mail-Adresse';
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
        $errors['email'] = 'Ungueltige E-Mail-Adresse';
    }

    // Password validation
    if ($passwordRequired && empty($password1)) {
        $errors['password'] = 'Passwort ist erforderlich';
    } elseif (!empty($password1) && $password1 !== $password2) {
        $errors['password'] = 'Passwoerter stimmen nicht ueberein';
    }

    return $errors;
}

/**
 * Validate URL format
 *
 * @return array<string, string> Errors keyed by field name
 */
function validateUrl(string $url): array
{
    $errors = [];

    $url = trim($url);

    if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
        $errors['url'] = 'Ungueltige URL';
    }

    return $errors;
}
