<?php

/**
 * PowerBook - PHP Guestbook System
 * Database Configuration
 *
 * @license MIT
 * @copyright PowerScripts.org
 *
 * @see https://www.powerscripts.org
 */

declare(strict_types=1);

// MySQL Server Configuration
// For Docker: Use service name 'db' as host
$config_sql_server = 'db';                    // MySQL Server Address
$config_sql_user = 'powerbook';             // MySQL User
$config_sql_password = 'powerbook_secret';      // MySQL Password
$config_sql_database = 'powerbook';             // MySQL Database

// Table Names
$pb_config = 'pb_config';                      // Config Table Name
$pb_admin = 'pb_admins';                      // Admin Table Name
$pb_entries = 'pb_entries';                     // Entries Table Name
