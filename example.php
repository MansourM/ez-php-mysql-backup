<?php
echo "Running example.php ... \n\n";
// Report all errors
error_reporting(E_ALL);
// Set script max execution time
set_time_limit(900); // 15 minutes
require_once "ez-php-mysql-backup.php";

if (php_sapi_name() != "cli")
    echo '<div style="font-family: monospace;">';

$backupDatabase = EzPhpMysqlBackUp::getInstance([
    "db_name" => "morghiran-eggs",
]);

// Option-1: Backup tables already defined above
$result = $backupDatabase->backupTables() ? 'OK' : 'KO';

// Option-2: Backup changed tables only - uncomment block below
/*
$since = '1 day';
$changed = $backupDatabase->getChangedTables($since);
if(!$changed){
  $backupDatabase->obfPrint('No tables modified since last ' . $since . '! Quitting..', 1);
  die();
}
$result = $backupDatabase->backupTables($changed) ? 'OK' : 'KO';
*/

$backupDatabase->obfPrint('Backup result: ' . $result, 1);

// Use $output variable for further processing, for example to send it by email
$output = $backupDatabase->getOutput();

if (php_sapi_name() != "cli")
    echo '</div>';
