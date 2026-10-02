<?php

/**
 * PowerBook - PHP Guestbook System
 * Formular „Neuen Eintrag schreiben“
 *
 * Wird von guestbook.inc.php eingebunden. Erwartet optional:
 * $pbFormValues (Rohwerte aus pb_normalize_entry()), $pbFormErrors
 * (Fehler je Feld), $pbFormNotice (allgemeine Meldung, maskiertes HTML)
 * sowie die $config_*-Variablen. Alle Werte werden hier genau einmal
 * maskiert.
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.inc.php';
require_once __DIR__ . '/validation.inc.php';

$pbValues = array_merge(
    ['name' => '', 'email' => '', 'url' => '', 'text' => '', 'icon' => 'no', 'smilies' => 'Y'],
    is_array($pbFormValues ?? null) ? $pbFormValues : []
);
$pbErrors = is_array($pbFormErrors ?? null) ? $pbFormErrors : [];
$pbNotice = (string) ($pbFormNotice ?? '');
$pbIconsOn = ($config_icons ?? 'N') === 'Y';
$pbSmileysOn = ($config_smilies ?? 'N') === 'Y';
$pbBbcodeOn = ($config_text_format ?? 'N') === 'Y';
$pbFirstError = array_key_first(array_intersect_key(['name' => 1, 'email' => 1, 'url' => 1, 'icon' => 1, 'text' => 1], $pbErrors));

// Attribute und Fehlertext eines Feldes.
$pbField = static function (string $field, string $help) use ($pbErrors, $pbFirstError): array {
    $id = 'pb_' . $field;
    if (!isset($pbErrors[$field])) {
        return ['class' => '', 'aria' => ' aria-describedby="' . $help . '"', 'feedback' => '', 'focus' => ''];
    }

    return [
        'class' => ' is-invalid',
        'aria' => ' aria-invalid="true" aria-describedby="' . $id . '_error ' . $help . '"',
        'feedback' => '<div id="' . $id . '_error" class="invalid-feedback">' . pb_h($pbErrors[$field]) . '</div>',
        'focus' => $pbFirstError === $field ? ' autofocus' : '',
    ];
};

$pbName = $pbField('name', 'pb_name_help');
$pbEmail = $pbField('email', 'pb_email_help');
$pbUrl = $pbField('url', 'pb_url_help');
$pbText = $pbField('text', 'pb_text_help pbTextCounter');

$pbHelpLabel = match (true) {
    $pbBbcodeOn && $pbSmileysOn => 'Hilfe zu Formatierung und Smileys',
    $pbBbcodeOn => 'Hilfe zur Formatierung',
    $pbSmileysOn => 'Hilfe zu Smileys',
    default => '',
};

?>
<section class="card shadow-sm mb-4" aria-labelledby="pbEntryFormTitle">
    <header class="card-header bg-primary text-white">
        <h2 id="pbEntryFormTitle" class="h5 mb-0">Neuen Eintrag schreiben</h2>
    </header>
    <div class="card-body">
        <?php if ($pbNotice !== '' || $pbErrors !== []) { ?>
        <div id="pbFormError" class="alert alert-danger" role="alert">
            <?= $pbNotice !== '' ? $pbNotice : 'Bitte prüfen Sie die markierten Felder.' ?>
        </div>
        <?php } ?>

        <form id="pbEntryForm" action="<?= pb_h((string) ($config_guestbook_name ?? 'pbook.php')) ?>" method="post" novalidate>
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="pb_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="pb_name" name="name" type="text" class="form-control<?= $pbName['class'] ?>" maxlength="<?= PB_MAX_NAME ?>" required autocomplete="name" value="<?= pb_h($pbValues['name']) ?>"<?= $pbName['aria'] . $pbName['focus'] ?>>
                    <?= $pbName['feedback'] ?>
                    <div id="pb_name_help" class="form-text">Pflichtfeld, höchstens <?= PB_MAX_NAME ?> Zeichen.</div>
                </div>

                <div class="col-md-6">
                    <label for="pb_email" class="form-label">E-Mail-Adresse</label>
                    <input id="pb_email" name="email2" type="email" class="form-control<?= $pbEmail['class'] ?>" maxlength="<?= PB_MAX_EMAIL ?>" autocomplete="email" value="<?= pb_h($pbValues['email']) ?>"<?= $pbEmail['aria'] . $pbEmail['focus'] ?>>
                    <?= $pbEmail['feedback'] ?>
                    <div id="pb_email_help" class="form-text">Optional. Wird nicht veröffentlicht.</div>
                </div>

                <div class="col-12">
                    <label for="pb_url" class="form-label">Homepage</label>
                    <input id="pb_url" name="url" type="url" inputmode="url" class="form-control<?= $pbUrl['class'] ?>" maxlength="<?= PB_MAX_URL ?>" autocomplete="url" placeholder="https://www.example.org" value="<?= pb_h($pbValues['url']) ?>"<?= $pbUrl['aria'] . $pbUrl['focus'] ?>>
                    <?= $pbUrl['feedback'] ?>
                    <div id="pb_url_help" class="form-text">Optional. Vollständige Adresse, zum Beispiel https://www.example.org.</div>
                </div>

                <?php if ($pbIconsOn) { ?>
                <fieldset id="pbIconGroup" class="col-12">
                    <legend class="form-label">Icon</legend>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div class="form-check">
                            <input id="pb_icon_no" type="radio" class="form-check-input" name="icon" value="no"<?= pb_icon_label($pbValues['icon']) !== null ? '' : ' checked' ?>>
                            <label for="pb_icon_no" class="form-check-label">Kein Icon</label>
                        </div>
                        <?php foreach (PB_ICONS as $pbIcon => $pbIconLabel) { ?>
                        <div class="form-check">
                            <input id="pb_icon_<?= $pbIcon ?>" type="radio" class="form-check-input" name="icon" value="<?= $pbIcon ?>"<?= $pbValues['icon'] === $pbIcon ? ' checked' : '' ?>>
                            <label for="pb_icon_<?= $pbIcon ?>" class="form-check-label"><img src="pb_inc/smilies/<?= $pbIcon ?>.gif" alt="<?= pb_h($pbIconLabel) ?>" title="<?= pb_h($pbIconLabel) ?>" width="15" height="15"></label>
                        </div>
                        <?php } ?>
                    </div>
                    <?php if (isset($pbErrors['icon'])) { ?>
                    <div class="invalid-feedback d-block"><?= pb_h($pbErrors['icon']) ?></div>
                    <?php } ?>
                </fieldset>
                <?php } ?>

                <div class="col-12">
                    <label for="pb_text" class="form-label">Text <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea id="pb_text" name="text" rows="8" class="form-control<?= $pbText['class'] ?>" required data-pb-max="<?= PB_MAX_TEXT ?>"<?= $pbText['aria'] . $pbText['focus'] ?>><?= pb_h($pbValues['text']) ?></textarea>
                    <?= $pbText['feedback'] ?>
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div id="pb_text_help" class="form-text">Pflichtfeld, höchstens <?= number_format(PB_MAX_TEXT, 0, ',', '.') ?> Zeichen.</div>
                        <div id="pbTextCounter" class="form-text pb-counter" aria-live="polite"><?= number_format(mb_strlen($pbValues['text']), 0, ',', '.') ?> von <?= number_format(PB_MAX_TEXT, 0, ',', '.') ?> Zeichen</div>
                    </div>

                    <?php if ($pbSmileysOn) { ?>
                    <div class="form-check mt-2">
                        <input id="pb_smilies" type="checkbox" class="form-check-input" name="smilies2" value="Y"<?= $pbValues['smilies'] === 'Y' ? ' checked' : '' ?>>
                        <label for="pb_smilies" class="form-check-label">Smileys als Bilder anzeigen</label>
                    </div>
                    <?php } ?>

                    <?php if ($pbHelpLabel !== '') { ?>
                    <button id="pbHelpToggle" type="button" class="btn btn-link btn-sm px-0 mt-1" data-bs-toggle="collapse" data-bs-target="#pbHelp" aria-expanded="false" aria-controls="pbHelp"><?= $pbHelpLabel ?></button>
                    <div id="pbHelp" class="collapse">
                        <div class="card card-body bg-body-tertiary mt-1 pb-help">
                            <?= pb_render_help($pbBbcodeOn, $pbSmileysOn) ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>

                <div class="col-12">
                    <p id="pbPrivacyNote" class="form-text mt-0">
                        Ihre E-Mail-Adresse wird nicht veröffentlicht. Zum Schutz vor Missbrauch speichern wir die IP-Adresse Ihres Eintrags.
                        <?php if (($config_release ?? 'R') === 'U') { ?>
                        Neue Einträge erscheinen, sobald sie freigeschaltet sind.
                        <?php } ?>
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button id="pbEntryPreviewBtn" type="submit" name="action" value="preview" class="btn btn-outline-primary">Vorschau</button>
                        <button id="pbEntrySubmit" type="submit" name="action" value="save" class="btn btn-primary">Eintragen</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
