<?php
//TODO add custom logging

class EzPhpMysqlBackUp
{
    private static $instance;
    private $config;
    private mysqli $conn;
    private $log;

    public static int $DOT_LENGTH = 60;

    public static function getInstance($config = null)
    {
        if (self::$instance == null)
            self::$instance = new EzPhpMysqlBackUp($config);
        else if ($config != null)
            self::$instance->resetConfig($config);

        return self::$instance;
    }

    private function __construct($config = null)
    {
        $this->setConfig($config);
        $this->setConnection();
        $this->log = "";
    }

    public function __get($name)
    {
        if (isset($this->config[$name]))
            return $this->config[$name];
        else {
            $this->obfPrintError("You are trying to get $name which is either inaccessible or non existing member");
            die();
        }
    }

    public function __set($name, $value)
    {
        if (isset($this->config[$name]))
            $this->config[$name] = $value;
        else {
            $this->obfPrintError("You are trying to set $name which is either inaccessible or non existing member");
            die();
        }
    }

    private function setConfig($config)
    {
        //db_name is mandatory
        if (isset($config["db_name"]))
            $dbName = $config["db_name"];
        else if (isset($_ENV["db_name"]))
            $dbName = $_ENV["db_name"];
        else
            die("db_name not set!");

        $defaultConfig = [
            "db_host" => isset($_ENV["db_host"]) ?: "localhost",
            "db_username" => isset($_ENV["db_username"]) ?: "root",
            "db_passwd" => isset($_ENV["db_passwd"]) ?: "",
            "db_name" => $dbName,
            "db_charset" => isset($_ENV["db_charset"]) ?: "utf8",
            "ezpmb_backup_tables" => isset($_ENV["ezpmb_backup_tables"]) ?: "*",
            "ezpmb_ignore_tables" => isset($_ENV["ezpmb_ignore_tables"]) ?: "",
            "ezpmb_backup_dir" => isset($_ENV["ezpmb_backup_dir"]) ?: "ezpmb_backups",
            "ezpmb_backup_file_name" => isset($_ENV["ezpmb_backup_file_name"]) ?: $dbName . '-' . date("Ymd_His", time()) . '.sql',
            "ezpmb_backup_triggers" => isset($_ENV["ezpmb_backup_triggers"]) ? strtolower($_ENV["ezpmb_backup_triggers"]) == "true" : false,
            "ezpmb_gzip" => isset($_ENV["ezpmb_gzip"]) ? strtolower($_ENV["ezpmb_gzip"]) == "true" : true,
            "ezpmb_disable_foreign_key_checks" => isset($_ENV["ezpmb_disable_foreign_key_checks"]) ? strtolower($_ENV["ezpmb_disable_foreign_key_checks"]) : true,
            "ezpmb_batch_size" => isset($_ENV["ezpmb_batch_size"]) ? intval($_ENV["ezpmb_batch_size"]) : 1000,
            "ezpmb_download" => isset($_ENV["ezpmb_download"]) ? strtolower($_ENV["ezpmb_download"]) == "true" : false, //Immediately start downloading the result
            "ezpmb_log_dir" => isset($_ENV["ezpmb_log_dir"]) ?: "ezpmb_backups/logs",
            "ezpmb_log_all" => isset($_ENV["ezpmb_log_all"]) ? strtolower($_ENV["ezpmb_log_all"]) == "true" : true,
            "ezpmb_log_error" => isset($_ENV["ezpmb_log_error"]) ? strtolower($_ENV["ezpmb_log_error"]) == "true" : true,
        ];

        if ($config == null)
            $this->config = $defaultConfig;
        else
            $this->config = array_merge($defaultConfig, $config);
    }

    private function setConnection()
    {
        $this->conn = new mysqli(
            $this->db_host,
            $this->db_username,
            $this->db_passwd,
            $this->db_name
        );
        if ($this->conn->connect_error)
            die("Connection failed: " . $this->conn->connect_error);

        $this->conn->query("SET NAMES $this->db_charset");
    }

    public function resetConfig($config)
    {
        $this->setConfig($config);
        $this->conn->close();
        $this->setConnection();
    }

    //TODO: test with null and stuff
    //format = * OR table1, table2, ...
    private function getTables($backupTablesString, $ignoreTablesString)
    {
        $tables = [];
        if ($backupTablesString == '*') {
            $result = $this->conn->query('SHOW TABLES');
            while ($row = mysqli_fetch_row($result))
                $tables[] = $row[0];
        } else
            $tables = explode(',', str_replace(' ', '', $backupTablesString));
        return array_diff($tables, explode(',', str_replace(' ', '', $ignoreTablesString)));
    }

    public function backupTables()
    {
        $this->wrapInDiv();
        $this->obfPrint("=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-= .:Starting backup:. =-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=", false);
        $this->obfPrint("$this->ezpmb_backup_dir/$this->ezpmb_backup_file_name");
        $this->lineBreak();
        try {
            $tables = $this->getTables($this->ezpmb_backup_tables, $this->ezpmb_ignore_tables);
            $sql = 'CREATE DATABASE IF NOT EXISTS `' . $this->db_name . '`' . ";\n\n";
            $sql .= 'USE `' . $this->db_name . "`;\n\n";

            $sessionForeignKeyChecks = $this->getForeignKeyChecks();
            $sql .= $this->getSetForeignKeyChecksString($sessionForeignKeyChecks);

            foreach ($tables as $table) {
                $this->obfPrintWithDots("Backing up `$table` table");

                /** CREATE TABLE */
                $sql .= 'DROP TABLE IF EXISTS `' . $table . '`;';
                $row = mysqli_fetch_row($this->conn->query('SHOW CREATE TABLE `' . $table . '`'));
                $sql .= "\n\n" . $row[1] . ";\n\n";

                /** INSERT INTO */
                $row = mysqli_fetch_row($this->conn->query('SELECT COUNT(*) FROM `' . $table . '`'));
                $numRows = $row[0];

                // Split table in batches in order to not exhaust system memory 
                $numBatches = intval($numRows / $this->ezpmb_batch_size) + 1; // Number of while-loop calls to perform

                for ($b = 1; $b <= $numBatches; $b++) {

                    $query = 'SELECT * FROM `' . $table . '` LIMIT ' . ($b * $this->ezpmb_batch_size - $this->ezpmb_batch_size) . ',' . $this->ezpmb_batch_size;
                    $result = $this->conn->query($query);
                    $realBatchSize = mysqli_num_rows($result); // Last batch size can be different from $this->batchSize
                    $numFields = mysqli_num_fields($result);

                    //TODO i dont think this check is required
                    // unless we are trying to account for changes while backup process is running
                    // in that case we need to be way more careful and do a lot more checks and stuff
                    // we could just lock each table before backing up if consistency is uper important
                    // but there should be way better ways to handle it
                    if ($realBatchSize !== 0) {
                        $sql .= 'INSERT INTO `' . $table . '` VALUES ';

                        for ($i = 0; $i < $numFields; $i++) {
                            $rowCount = 1;
                            while ($row = mysqli_fetch_row($result)) {
                                $sql .= '(';
                                for ($j = 0; $j < $numFields; $j++) {
                                    $sql .= $this->formatValue($row[$j]);

                                    if ($j < ($numFields - 1))
                                        $sql .= ',';
                                }

                                //TODO Think: what if DB changes while backup is in progress
                                if ($rowCount == $realBatchSize) {
                                    $rowCount = 0;
                                    $sql .= ");\n"; //close the insert statement
                                } else
                                    $sql .= "),\n"; //close the row

                                $rowCount++;
                            }
                        }

                        $this->saveSqlFile($sql);
                        $sql = '';
                    }
                }

                $this->backUpTriggers($table);

                $sql .= "\n\n";

                $this->obfPrint('OK', false);
            }

            /** Resets foreign key checks */
            $sql .= $this->getSetForeignKeyChecksString($sessionForeignKeyChecks, true);

            //TODO check return?
            $this->saveSqlFile($sql);

            if ($this->ezpmb_gzip)
                $this->gzipBackupFile();
            else {
                $this->lineBreak();
                $this->obfPrintWithDots("Backup");
                $this->obfPrint("OK", false);
            }
        } catch (Exception $e) {
            $this->lineBreak();
            $this->obfPrintError("Backup ERROR:");
            $this->obfPrintError($e->getMessage(), false);
            return false;
        }
        return true;
    }

    //TODO: doest not work properly!
    private function backUpTriggers($table)
    {
        if (!$this->ezpmb_backup_triggers)
            return;
        // Check if there are some TRIGGERS associated to the table
        $triggerSql = "";
        $query = "SHOW TRIGGERS LIKE '" . $table . "%'";
        $result = $this->conn->query($query);
        if ($result) {
            $triggers = array();
            while ($trigger = mysqli_fetch_row($result)) {
                $triggers[] = $trigger[0];
            }

            // Iterate through triggers of the table
            foreach ($triggers as $trigger) {
                $query = 'SHOW CREATE TRIGGER `' . $trigger . '`';
                $result = mysqli_fetch_array($this->conn->query($query));
                $triggerSql .= "\nDROP TRIGGER IF EXISTS `" . $trigger . "`;\n";
                $triggerSql .= "DELIMITER $$\n" . $result[2] . "$$\n\nDELIMITER ;\n";
            }

            $triggerSql .= "\n";

            $this->saveSqlFile($triggerSql);
        }
    }

    private function formatValue($val)
    {
        if (isset($val)) {
            $val = addslashes($val);
            $val = str_replace("\n", "\\n", $val);
            $val = str_replace("\r", "\\r", $val);
            $val = str_replace("\f", "\\f", $val);
            $val = str_replace("\t", "\\t", $val);
            $val = str_replace("\v", "\\v", $val);
            $val = str_replace("\a", "\\a", $val);
            $val = str_replace("\b", "\\b", $val);
            if ($val == 'true' or $val == 'false' or preg_match('/^-?[1-9][0-9]*$/', $val) or $val == 'NULL' or $val == 'null') {
                return $val;
            } else {
                return '"' . $val . '"';
            }
        } else {
            return 'NULL';
        }
    }

    //checks if foreign_key_checks==true (only for current session)
    private function getForeignKeyChecks()
    {
        return mysqli_fetch_row($this->conn->query("SHOW Variables WHERE Variable_name='foreign_key_checks'"))[1] == "ON";
    }

    private function getSetForeignKeyChecksString($sessionValue, $reset = false)
    {
        if ($sessionValue == $this->ezpmb_disable_foreign_key_checks)
            return "\n";
        if ($reset)
            return "SET foreign_key_checks = " . ($sessionValue ? 1 : 0) . ";\n\n";
        return "SET foreign_key_checks = " . ($this->ezpmb_disable_foreign_key_checks ? 1 : 0) . ";\n\n";
    }

    /**
     * Save SQL to file
     * @param string $sql
     */
    //TODO why protected?
    protected function saveSqlFile(&$sql)
    {
        return $this->saveFile($this->ezpmb_backup_dir, $this->ezpmb_backup_file_name, $sql);
    }

    private function saveLogFile(&$text, $isError)
    {
        if ($this->ezpmb_log_all)
            $this->saveFile($this->ezpmb_log_dir, "all.log", $text);
        if ($isError && $this->ezpmb_log_error)
            $this->saveFile($this->ezpmb_log_dir, "error.log", $text);
    }

    //TODO why & ?
    private function saveFile($dir, $fileName, $text)
    {
        if (!$text) return false;

        try {
            if (!file_exists($dir))
                mkdir($dir, 0777, true);

            //TODO: optimize this for big file sizes? can crash stop executing because of RAM, Time, etc
            file_put_contents("$dir/$fileName", $text, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            $this->obfPrintError('Saving File catch!');
            $this->obfPrintError($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Gzip backup file
     *
     * @param integer $level GZIP compression level (default: 9)
     * @return string New filename (with .gz appended) if success, or false if operation fails
     */
    protected function gzipBackupFile($level = 9)
    {
        if (!$this->ezpmb_gzip) return true;

        $source = $this->ezpmb_backup_dir . '/' . $this->ezpmb_backup_file_name;
        $dest = $source . '.gz';

        $this->lineBreak();
        $this->obfPrint("Gzipping backup file:");
        $this->obfPrintWithDots($dest);

        $mode = 'wb' . $level;
        if ($fpOut = gzopen($dest, $mode)) {
            if ($fpIn = fopen($source, 'rb')) {
                while (!feof($fpIn))
                    gzwrite($fpOut, fread($fpIn, 1024 * 256));
                fclose($fpIn);
            } else
                return false;

            gzclose($fpOut);
            if (!unlink($source)) {
                $this->obfPrintError('Gzipping Failed 1');
                return false;
            }
        } else {
            $this->obfPrintError('Gzipping Failed 2');
            return false;
        }

        $this->obfPrint('OK', false);
        return $dest;
    }

    public function log($msg = '', $attachCurrentTimeStamp = true, $lineBreaks = 1)
    {
        $this->obfPrint($msg, $attachCurrentTimeStamp, $lineBreaks);
    }

    /** log error */
    public function loge($msg = '', $attachCurrentTimeStamp = true, $lineBreaks = 1)
    {
        $this->obfPrintError($msg, $attachCurrentTimeStamp, $lineBreaks);
    }

    private function obfPrint($msg = '', $attachCurrentTimeStamp = true, $lineBreaks = 1)
    {
        $this->ezPrint(false, $msg, $attachCurrentTimeStamp, $lineBreaks,);
    }

    private function obfPrintError($msg = '', $attachCurrentTimeStamp = true, $lineBreaks = 1)
    {
        $this->ezPrint(true, "Error in: $this->ezpmb_backup_dir/$this->ezpmb_backup_file_name", $attachCurrentTimeStamp, $lineBreaks);
        $this->ezPrint(true, $msg, $attachCurrentTimeStamp, $lineBreaks);
    }

    /** Prints message forcing output buffer flush */
    private function ezPrint($isError, $msg = '', $attachCurrentTimeStamp = true, $lineBreaks = 1)
    {
        //TODO: handle download better
        if ($this->ezpmb_download)
            return;

        $Output = '';
        if ($attachCurrentTimeStamp)
            $Output = date("Y-m-d H:i:s") . ' - ';

        $Output .= $msg . $this->lineBreak($lineBreaks, false);
        $this->saveLogFile($Output, $isError);
        $this->log .= $Output;

        echo $Output;
        if (php_sapi_name() != "cli")
            if (ob_get_level() > 0)
                ob_flush();
        flush();
    }

    /** Prints message forcing output buffer flush */
    private function obfPrintWithDots($msg = '', $lineBreaks = 0, $attachCurrentTimeStamp = true)
    {
        $this->obfPrint($msg . str_repeat('.', self::$DOT_LENGTH - strlen($msg)), $attachCurrentTimeStamp, $lineBreaks);
    }

    public function lineBreak($count = 1, $echo = true)
    {
        if ($this->ezpmb_download)
            return;

        $Output = str_repeat("\n", $count);

        if ($echo)
            echo $Output;
        return $Output;
    }

    public function wrapInDiv($start = true, $echo = true)
    {
        if ($this->ezpmb_download || php_sapi_name() == "cli")
            return;

        if ($start)
            $Output = '<div style="font-family: monospace;white-space: pre-wrap;">';
        else
            $Output = '</div>';

        if ($echo)
            echo $Output;
        return $Output;
    }

    //TODO think ...

    /** Returns full execution output */
    public function getLog()
    {
        return $this->log;
    }

    public function getBackupFilePath()
    {
        return $this->getBackupDir() . '/' . $this->getBackupFileName();
    }

    public function getBackupFileName()
    {
        if ($this->ezpmb_gzip) {
            return $this->ezpmb_backup_file_name . '.gz';
        } else
            return $this->ezpmb_backup_file_name;
    }

    public function getBackupDir()
    {
        return $this->ezpmb_backup_dir;
    }

    /** Returns array of changed tables since duration */
    public function getChangedTables($since = '1 day')
    {
        $query = "SELECT TABLE_NAME,update_time FROM information_schema.tables WHERE table_schema='$this->db_name'";

        $result = $this->conn->query($query);
        while ($row = mysqli_fetch_assoc($result))
            $resultset[] = $row;

        if (empty($resultset)) return false;

        $tables = [];
        for ($i = 0; $i < count($resultset); $i++) {
            if (in_array($resultset[$i]['TABLE_NAME'], IGNORE_TABLES)) // ignore this table
                continue;
            if (strtotime('-' . $since) < strtotime($resultset[$i]['update_time']))
                $tables[] = $resultset[$i]['TABLE_NAME'];
        }
        return ($tables) ?: false;
    }
}