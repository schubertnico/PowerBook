<?php
/**
 * PowerBook - PHP Guestbook System
 * Entry Form
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// This file is included from guestbook.inc.php

// Get form values (for re-display after error)
$formName = e($name ?? '');
$formEmail = e($email2 ?? '');
$formUrl = e($url ?? '');
$formIcq = e($icq2 ?? '');
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
<form action="<?= e($config_guestbook_name) ?>" method="post">
    <?= csrfField() ?>
    <table border="0">
        <tr>
            <td width="120">Name:</td>
            <td><input name="name" maxlength="100" size="30" value="<?= $formName ?>"></td>
        </tr>
        <tr>
            <td width="120">eMail:</td>
            <td><input name="email2" maxlength="250" size="30" value="<?= $formEmail ?>"></td>
        </tr>
        <tr>
            <td width="120">Homepage:</td>
            <td>
                <input maxlength="5" size="4" value="http://" readonly>
                <input name="url" maxlength="100" size="24" value="<?= $formUrl ?>">
            </td>
        </tr>

        <?php if (($config_icq ?? 'N') === 'Y') { ?>
        <tr>
            <td width="120">ICQ#:</td>
            <td><input name="icq2" maxlength="20" size="10" value="<?= $formIcq ?>"></td>
        </tr>
        <?php } ?>

        <?php if (($config_icons ?? 'N') === 'Y') { ?>
        <tr>
            <td width="120" valign="top">Icon:</td>
            <td>
                <input type="radio" name="icon" value="no" <?= $iconChecked['no'] ?>> Kein Icon<br>
                <input type="radio" name="icon" value="text" <?= $iconChecked['text'] ?>>
                    <img src="pb_inc/smilies/text.gif" alt="text">
                <input type="radio" name="icon" value="question" <?= $iconChecked['question'] ?>>
                    <img src="pb_inc/smilies/question.gif" alt="question">
                <input type="radio" name="icon" value="mark" <?= $iconChecked['mark'] ?>>
                    <img src="pb_inc/smilies/mark.gif" alt="mark">
                <input type="radio" name="icon" value="shock" <?= $iconChecked['shock'] ?>>
                    <img src="pb_inc/smilies/shock.gif" alt="shock">
                <input type="radio" name="icon" value="sad2" <?= $iconChecked['sad2'] ?>>
                    <img src="pb_inc/smilies/sad2.gif" alt="sad">
                <input type="radio" name="icon" value="happy1" <?= $iconChecked['happy1'] ?>>
                    <img src="pb_inc/smilies/happy1.gif" alt="happy">
                <input type="radio" name="icon" value="happy5" <?= $iconChecked['happy5'] ?>>
                    <img src="pb_inc/smilies/happy5.gif" alt="happy">
            </td>
        </tr>
        <?php } ?>

        <tr>
            <td width="120" valign="top">
                Text<?php if (($config_text_format ?? 'N') === 'Y') { ?>
                    &nbsp; <small>(<a href="javascript:TextHelp()">Hilfe</a>)</small>
                <?php } ?>:
            </td>
            <td>
                <textarea name="text" rows="10" cols="35"><?= $formText ?></textarea><br>

                <?php if (($config_smilies ?? 'N') === 'Y') { ?>
                <input type="checkbox" name="smilies2" <?= $smiliesChecked ?> value="Y">
                Smilies aktivieren &nbsp;
                <small>(<a href="javascript:SmiliesHelp()">Hilfe</a>)</small>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td height="30" valign="bottom">
                <input type="hidden" name="show_gb" value="no">
                <input type="hidden" name="preview" value="yes">
                <input type="submit" value="Abschicken">
                <input type="reset" value="Zurücksetzen">
            </td>
        </tr>
    </table>
</form>
