<?php
/**
 * PowerBook - PHP Guestbook System
 * Entry Form
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// This file is included from guestbook.inc.php

// Get form values (for re-display after error)
$formName = e($name ?? '');
$formEmail = e($email2 ?? '');
$formUrl = e($url ?? '');
$formText = e($text ?? '');

// Icon checked states
$icon ??= '';
$iconChecked = [
    'no' => ($icon === 'no' || $icon === '') ? 'checked' : '',
    'text' => ($icon === 'text') ? 'checked' : '',
    'question' => ($icon === 'question') ? 'checked' : '',
    'mark' => ($icon === 'mark') ? 'checked' : '',
    'shock' => ($icon === 'shock') ? 'checked' : '',
    'sad2' => ($icon === 'sad2') ? 'checked' : '',
    'happy1' => ($icon === 'happy1') ? 'checked' : '',
    'happy5' => ($icon === 'happy5') ? 'checked' : '',
];

// Smilies checkbox
$smiliesChecked = (($smilies2 ?? '') === 'Y' || ($show_gb ?? '') !== 'no') ? 'checked' : '';

?>
<section class="card shadow-sm mb-4">
    <header class="card-header bg-primary text-white">
        <h2 class="h5 mb-0">Neuen Eintrag schreiben</h2>
    </header>
    <div class="card-body">
        <form action="<?= e($config_guestbook_name) ?>" method="post" novalidate>
            <?= csrfField() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="pb_name" class="form-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="pb_name" name="name" type="text" class="form-control" maxlength="100" required value="<?= $formName ?>" aria-describedby="pb_name_help">
                    <div id="pb_name_help" class="form-text">Pflichtfeld. Max. 100 Zeichen.</div>
                </div>

                <div class="col-md-6">
                    <label for="pb_email" class="form-label">E-Mail-Adresse</label>
                    <input id="pb_email" name="email2" type="email" class="form-control" maxlength="250" value="<?= $formEmail ?>" aria-describedby="pb_email_help">
                    <div id="pb_email_help" class="form-text">Optional. Wird nur für Antworten genutzt.</div>
                </div>

                <div class="col-md-12">
                    <label for="pb_url" class="form-label">Homepage</label>
                    <div class="input-group">
                        <span class="input-group-text">http://</span>
                        <input id="pb_url" name="url" type="text" class="form-control" maxlength="100" value="<?= $formUrl ?>" aria-describedby="pb_url_help">
                    </div>
                    <div id="pb_url_help" class="form-text">Ohne <code>http://</code> eingeben.</div>
                </div>

                <?php if (($config_icons ?? 'N') === 'Y') { ?>
                <fieldset class="col-12">
                    <legend class="form-label">Icon</legend>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <div class="form-check">
                            <input id="pb_icon_no" type="radio" class="form-check-input" name="icon" value="no" <?= $iconChecked['no'] ?>>
                            <label for="pb_icon_no" class="form-check-label">Kein Icon</label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_text" type="radio" class="form-check-input" name="icon" value="text" <?= $iconChecked['text'] ?>>
                            <label for="pb_icon_text" class="form-check-label"><img src="pb_inc/smilies/text.gif" alt="text"></label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_question" type="radio" class="form-check-input" name="icon" value="question" <?= $iconChecked['question'] ?>>
                            <label for="pb_icon_question" class="form-check-label"><img src="pb_inc/smilies/question.gif" alt="question"></label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_mark" type="radio" class="form-check-input" name="icon" value="mark" <?= $iconChecked['mark'] ?>>
                            <label for="pb_icon_mark" class="form-check-label"><img src="pb_inc/smilies/mark.gif" alt="mark"></label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_shock" type="radio" class="form-check-input" name="icon" value="shock" <?= $iconChecked['shock'] ?>>
                            <label for="pb_icon_shock" class="form-check-label"><img src="pb_inc/smilies/shock.gif" alt="shock"></label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_sad2" type="radio" class="form-check-input" name="icon" value="sad2" <?= $iconChecked['sad2'] ?>>
                            <label for="pb_icon_sad2" class="form-check-label"><img src="pb_inc/smilies/sad2.gif" alt="sad"></label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_happy1" type="radio" class="form-check-input" name="icon" value="happy1" <?= $iconChecked['happy1'] ?>>
                            <label for="pb_icon_happy1" class="form-check-label"><img src="pb_inc/smilies/happy1.gif" alt="happy"></label>
                        </div>
                        <div class="form-check">
                            <input id="pb_icon_happy5" type="radio" class="form-check-input" name="icon" value="happy5" <?= $iconChecked['happy5'] ?>>
                            <label for="pb_icon_happy5" class="form-check-label"><img src="pb_inc/smilies/happy5.gif" alt="happy"></label>
                        </div>
                    </div>
                </fieldset>
                <?php } ?>

                <div class="col-12">
                    <label for="pb_text" class="form-label">
                        Text <span class="text-danger" aria-hidden="true">*</span>
                        <?php if (($config_text_format ?? 'N') === 'Y') { ?>
                        &nbsp;<small>(<a href="javascript:TextHelp()">Formatierungs-Hilfe</a>)</small>
                        <?php } ?>
                    </label>
                    <textarea id="pb_text" name="text" rows="8" class="form-control" maxlength="5000" required aria-describedby="pb_text_help"><?= $formText ?></textarea>
                    <div id="pb_text_help" class="form-text">Pflichtfeld. Max. 5000 Zeichen.</div>

                    <?php if (($config_smilies ?? 'N') === 'Y') { ?>
                    <div class="form-check mt-2">
                        <input id="pb_smilies" type="checkbox" class="form-check-input" name="smilies2" value="Y" <?= $smiliesChecked ?>>
                        <label for="pb_smilies" class="form-check-label">
                            Smilies aktivieren &nbsp;
                            <small>(<a href="javascript:SmiliesHelp()">Hilfe</a>)</small>
                        </label>
                    </div>
                    <?php } ?>
                </div>

                <div class="col-12">
                    <input type="hidden" name="show_gb" value="no">
                    <input type="hidden" name="preview" value="yes">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Vorschau / Abschicken</button>
                        <button type="reset" class="btn btn-outline-secondary">Zurücksetzen</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
