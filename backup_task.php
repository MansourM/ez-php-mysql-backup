<?php

require_once('lib/ez-php-mysql-backup.php');
$ezpmb = EzPhpMysqlBackUp::getInstance([
    "db_name" => "morghiran-eggs",
    "db_username" => "root",
    "db_pass" => "",
    "ezpmb_gzip" => true,
    "ezpmb_download" => false,
    "ezpmb_timezone" => 'Asia/Tehran',
]);

if (!$ezpmb->backupTables())
    $ezpmb->loge("Scheduled backup failed!");
else
    $ezpmb->log("Scheduled backup successful.");