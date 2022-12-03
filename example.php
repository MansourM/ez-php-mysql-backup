<?php
// Report all errors
error_reporting(E_ALL);
// Set script max execution time
set_time_limit(900); // 15 minutes
require_once "ez-php-mysql-backup.php";

$backupDatabase = EzPhpMysqlBackUp::getInstance([
    "db_name" => "morghiran-eggs",
    "ezpmb_gzip" => true,
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

*/


// Use $output variable for further processing, for example to send it by email
$output = $backupDatabase->getLog();



/*$fileName = $backupDatabase->getBackupFileName();
$filePath = $backupDatabase->getBackupFilePath();
if (!file_exists($filePath))
    die('file not found');
$f = fopen($filePath, "r");

header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=$fileName");
header("Content-Type: application/zip");
header("Content-Transfer-Encoding: binary");
header('Content-Length: ' . filesize($f));


fpassthru($f);
fclose($f);*/


