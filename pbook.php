<?php
/**
 * PowerBook - PHP Guestbook System
 * Main Entry Point
 *
 * @license MIT
 * @copyright Original: 2002 Axel Habermaier, Updates: 2025 Nico Schubert
 *
 * @see https://github.com/schubertnico/PowerBook.git
 */

declare(strict_types=1);

// BUG-013: Entry-Konstante setzen, damit guestbook.inc.php Direktaufruf ablehnen kann.
if (!defined('PB_ENTRY')) {
    define('PB_ENTRY', true);
}

// Include config early for session_start() before any output
require_once __DIR__ . '/pb_inc/config.inc.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PowerBook Gästebuch</title>
    <style type="text/css">
        body {
            scrollbar-arrow-color: #001329;
            scrollbar-base-color: #3F5070;
        }
        td {
            font-size: 12px;
            font-family: Arial, sans-serif;
        }
        input, textarea, select {
            color: #FFFFFF;
            border-width: 1px;
            border-color: #6078A0;
            border-style: solid;
            background: #001329;
        }
        small {
            font-size: 11px;
        }
        hr {
            color: #001329;
        }
    </style>
</head>
<body bgcolor="#002040" topmargin="10" bottommargin="10" leftmargin="10" rightmargin="10" text="#ffffff" link="#B5C3D9" vlink="#B5C3D9" alink="#B5C3D9" marginwidth="0" marginheight="0">

<table width="760" border="0" cellpadding="5" cellspacing="0">
    <tr>
        <td width="100" valign="top" bgcolor="#001329">
            <a href="pb_inc/admincenter/">Admin</a>
        </td>
        <td width="*" align="center">
            <?php include __DIR__ . '/pb_inc/guestbook.inc.php'; ?>
        </td>
    </tr>
</table>

</body>
</html>
