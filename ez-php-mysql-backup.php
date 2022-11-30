<?php

class EzPhpMysqlBackUp
{
    private static $instance;
    private $config;
    private $conn;
    private $output;

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
        $this->output = "";
    }

    public function __get($name)
    {
        if (isset($this->config[$name]))
            return $this->config[$name];
        else
            die("You are trying to get $name which is either inaccessible or non existing member");
    }

    public function __set($name, $value)
    {
        if (isset($this->config[$name]))
            $this->config[$name] = $value;
        else
            die("You are trying to set $name which is either inaccessible or non existing member");
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
            "ezpmb_gzip" => isset($_ENV["ezpmb_gzip"]) ? strtolower($_ENV["ezpmb_gzip"]) == "true" : true,
            "ezpmb_disable_foreign_key_checks" => isset($_ENV["ezpmb_disable_foreign_key_checks"]) ? strtolower($_ENV["ezpmb_disable_foreign_key_checks"]) : true,
            "ezpmb_batch_size" => isset($_ENV["ezpmb_batch_size"]) ? intval($_ENV["ezpmb_batch_size"]) : 1000,
            "ezpmb_download" => isset($_ENV["ezpmb_download"]) ? strtolower($_ENV["ezpmb_download"]) == "true" : false, //Immediately start downloading the result
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
        try {
            $tables = $this->getTables($this->ezpmb_backup_tables, $this->ezpmb_ignore_tables);

            $sql = 'CREATE DATABASE IF NOT EXISTS `' . $this->db_name . '`' . ";\n\n";
            $sql .= 'USE `' . $this->db_name . "`;\n\n";

            if ($this->ezpmb_disable_foreign_key_checks === true)
                $sql .= "SET foreign_key_checks = 0;\n\n";


            foreach ($tables as $table) {
                $this->obfPrint("Backing up `$table` table..." . str_repeat('.', 50 - strlen($table)), 0, 0);

                /**
                 * CREATE TABLE
                 */
                $sql .= 'DROP TABLE IF EXISTS `' . $table . '`;';
                $row = mysqli_fetch_row($this->conn->query('SHOW CREATE TABLE `' . $table . '`'));
                $sql .= "\n\n" . $row[1] . ";\n\n";

                /**
                 * INSERT INTO
                 */

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
                    // unless we are trying to count for changes while backup process is running
                    // in that case we need to be way more careful and and a lot more checks and stuff
                    if ($realBatchSize !== 0) {
                        $sql .= 'INSERT INTO `' . $table . '` VALUES ';

                        for ($i = 0; $i < $numFields; $i++) {
                            $rowCount = 1;
                            while ($row = mysqli_fetch_row($result)) {
                                $sql .= '(';
                                for ($j = 0; $j < $numFields; $j++) {
                                    if (isset($row[$j])) {
                                        $row[$j] = addslashes($row[$j]);
                                        $row[$j] = str_replace("\n", "\\n", $row[$j]);
                                        $row[$j] = str_replace("\r", "\\r", $row[$j]);
                                        $row[$j] = str_replace("\f", "\\f", $row[$j]);
                                        $row[$j] = str_replace("\t", "\\t", $row[$j]);
                                        $row[$j] = str_replace("\v", "\\v", $row[$j]);
                                        $row[$j] = str_replace("\a", "\\a", $row[$j]);
                                        $row[$j] = str_replace("\b", "\\b", $row[$j]);
                                        if ($row[$j] == 'true' or $row[$j] == 'false' or preg_match('/^-?[1-9][0-9]*$/', $row[$j]) or $row[$j] == 'NULL' or $row[$j] == 'null') {
                                            $sql .= $row[$j];
                                        } else {
                                            $sql .= '"' . $row[$j] . '"';
                                        }
                                    } else {
                                        $sql .= 'NULL';
                                    }

                                    if ($j < ($numFields - 1))
                                        $sql .= ',';
                                }

                                if ($rowCount == $realBatchSize) {
                                    $rowCount = 0;
                                    $sql .= ");\n"; //close the insert statement
                                } else
                                    $sql .= "),\n"; //close the row

                                $rowCount++;
                            }
                        }

                        $this->saveFile($sql);
                        $sql = '';
                    }
                }

                /**
                 * CREATE TRIGGER
                 */

                // Check if there are some TRIGGERS associated to the table
                /*$query = "SHOW TRIGGERS LIKE '" . $table . "%'";
                $result = $this->conn->query($query);
                if ($result) {
                    $triggers = array();
                    while ($trigger = mysqli_fetch_row ($result)) {
                        $triggers[] = $trigger[0];
                    }
                    
                    // Iterate through triggers of the table
                    foreach ( $triggers as $trigger ) {
                        $query= 'SHOW CREATE TRIGGER `' . $trigger . '`';
                        $result = mysqli_fetch_array ($this->conn->query($query));
                        $sql.= "\nDROP TRIGGER IF EXISTS `" . $trigger . "`;\n";
                        $sql.= "DELIMITER $$\n" . $result[2] . "$$\n\nDELIMITER ;\n";
                    }

                    $sql.= "\n";

                    $this->saveFile($sql);
                    $sql = '';
                }*/

                $sql .= "\n\n";

                $this->obfPrint('OK');
            }

            /**
             * Re-enable foreign key checks
             */
            //TODO: is it possible that this is wrong? maybe db had turned it off before by default?
            if ($this->ezpmb_disable_foreign_key_checks === true)
                $sql .= "SET foreign_key_checks = 1;\n";

            $this->saveFile($sql);

            if ($this->ezpmb_gzip)
                $this->gzipBackupFile();
            else
                $this->obfPrint("Backup file succesfully saved to $this->ezpmb_backup_dir/$this->ezpmb_backup_file_name", 1, 1);
        } catch (Exception $e) {
            print_r($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Save SQL to file
     * @param string $sql
     */
    protected function saveFile(&$sql)
    {
        if (!$sql) return false;

        try {
            if (!file_exists($this->ezpmb_backup_dir))
                mkdir($this->ezpmb_backup_dir, 0777, true);

            file_put_contents($this->ezpmb_backup_dir . '/' . $this->ezpmb_backup_file_name, $sql, FILE_APPEND | LOCK_EX);

        } catch (Exception $e) {
            print_r($e->getMessage());
            return false;
        }
        return true;
    }

    /*
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

        $this->obfPrint("Gzipping backup file to $dest ... ", 1, 0);

        $mode = 'wb' . $level;
        if ($fpOut = gzopen($dest, $mode)) {
            if ($fpIn = fopen($source, 'rb')) {
                while (!feof($fpIn))
                    gzwrite($fpOut, fread($fpIn, 1024 * 256));
                fclose($fpIn);
            } else
                return false;

            gzclose($fpOut);
            if (!unlink($source))
                return false;
        } else
            return false;

        $this->obfPrint('OK');
        return $dest;
    }

    /**
     * Prints message forcing output buffer flush
     *
     */
    public function obfPrint($msg = '', $lineBreaksBefore = 0, $lineBreaksAfter = 1)
    {
        if ($this->ezpmb_download)
            return;

        if ($msg != 'OK' and $msg != 'KO')
            $msg = date("Y-m-d H:i:s") . ' - ' . $msg;

        $output = '';

        if (php_sapi_name() != "cli")
            $lineBreak = "<br />";
        else
            $lineBreak = "\n";

        for ($i = 0; $i < $lineBreaksBefore; $i++)
            $output .= $lineBreak;

        $output .= $msg;

        for ($i = 0; $i < $lineBreaksAfter; $i++)
            $output .= $lineBreak;

        // Save output for later use
        $this->output .= str_replace('<br />', '\n', $output);

        echo $output;

        if (php_sapi_name() != "cli")
            if (ob_get_level() > 0)
                ob_flush();

        $this->output .= " ";

        flush();
    }

    /**
     * Returns full execution output
     *
     */
    public function getOutput()
    {
        return $this->output;
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

    /**
     * Returns array of changed tables since duration
     *
     */
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