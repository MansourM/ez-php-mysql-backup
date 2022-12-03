<?php
// Optional - Report all errors
error_reporting(E_ALL);
// Optional - Set script max execution time
set_time_limit(900); // 15 minutes
// Import the lib
require_once "ez-php-mysql-backup.php";

// Initialize
$backupDatabase = EzPhpMysqlBackUp::getInstance([
    "db_name" => "your_db_name",
    "ezpmb_gzip" => false,
    "ezpmb_timezone" => 'Asia/Tehran',
]);

// Option-1: Backup tables already defined above
$backupDatabase->backupTables();

// Option-2: Backup changed tables only
$since = '1 day';
$changed = $backupDatabase->backupTablesSince($since);

// Download the result
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


