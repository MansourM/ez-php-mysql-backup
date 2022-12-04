<?php
// Optional - DevOnly - Report all errors
error_reporting(E_ALL);
// Optional - Set script max execution time
set_time_limit(900); // 15 minutes
// Import the lib
require_once "ez-php-mysql-backup.php";

// Initialize
$backupDatabase = EzPhpMysqlBackUp::getInstance([
    "db_name" => "morghiran-eggs",
    "ezpmb_gzip" => true,
    "ezpmb_download" => true,
    "ezpmb_timezone" => 'Asia/Tehran',
]);

// Option-1: Backup tables already defined above
$backupDatabase->backupTables();

// Option-2: Backup changed tables only
//$since = '1 day';
//$backupDatabase->backupTablesSince($since);

