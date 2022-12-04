<?php
// Optional - DevOnly - Report all errors
//error_reporting(E_ALL);
// Optional - Set script max execution time
set_time_limit(900); // 15 minutes

require_once('ez-php-mysql-backup.php');
$ezpmb = EzPhpMysqlBackUp::getInstance([
    "db_name" => "morghiran-eggs",
    "db_username" => "root",
    "db_pass" => "",
    "ezpmb_gzip" => true,
    "ezpmb_timezone" => 'Asia/Tehran',
]);

// Option-1: Backup tables already defined above
$wasSuccessful = $ezpmb->backupTables();

//Option-2: Backup changed tables only
//$since = '1 day';
//$wasSuccessful = $ezpmb->backupTablesSince($since);

if ($wasSuccessful)
    $ezpmb->log("Scheduled backup successful.");
else
    $ezpmb->loge("Scheduled backup failed!");
