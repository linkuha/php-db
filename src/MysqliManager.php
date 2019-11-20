<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 26.11.2018
 * Time: 2:11
 */

namespace SimpleLibs\Db;

use \mysqli;
use \mysqli_result;

/**
 * @package Db
 */
class MysqliManager
{
    const DEFAULT_HOST = 'localhost';
    const DEFAULT_PORT = 3306;

    const FETCH_AS_ARRAY = 'array';
    const FETCH_AS_OBJECT = 'object';

    private $fetchMode = self::FETCH_AS_ARRAY;
    private $debug = false;
    private $reconnectRetries = 5;

    private $dbHost = self::DEFAULT_HOST;
    private $dbPort = self::DEFAULT_PORT;
    private $dbUser = '';
    private $dbPass = '';
    private $dbName = "";
    private $unixSocket = null;

    //p - persistent connection. use one connection for same params, if possible, for every mysqli_init in one script
    private $persistent = false;

    /**
     * @var mysqli $client
     * @var mysqli_result $result
     */
    private $client = null;
    private $result = null;

    private $resultMode = MYSQLI_STORE_RESULT;  // store - buffered, use - non buffered
    private $inTransaction = false;

    protected $lastResult = null;
    public $lastQuery  = "";
    public $lastInsertId = 0;   //  The ID generated for an AUTO_INCREMENT column by the previous query (usually INSERT).
    public $lastAffectedRows = 0;
    public $lastElapsedTime = 0;
    public $lastNumRows = 0;

    protected static $instances = [];

    /**
     * @param string $user
     * @param string $pass
     * @param string $dbName
     * @param string|null $host
     * @param int|null $port
     * @param bool $persistent
     * @param string $unixSocket
     * @return MysqliManager
     */
    public static function getInstance($user = null, $pass = null, $dbName = null, $host = null, $port = null, $persistent = false, $unixSocket = null)
    {
        $hash = ($persistent ? "p:" : "") . "$host:$port:$dbName:$unixSocket";

        if (! array_key_exists($hash, self::$instances)) {
            self::$instances[$hash] = new self($user, $pass, $dbName, $host, $port, $persistent, $unixSocket);
        }
        return self::$instances[$hash];
    }


    /**
     * NOTE! Use getInstance(...) method above for creating connection (will use existed if it has)
     *
     * MysqliManager constructor.
     * @param $user
     * @param $pass
     * @param $dbName
     * @param null $host
     * @param null $port
     * @param null $persistent
     * @param string $unixSocket
     */
    public function __construct($user = null, $pass = null, $dbName = null, $host = null, $port = null, $persistent = null, $unixSocket = null)
    {
        $defaults = self::getPhpDefaults();
        if (empty($user)) {
            if (empty($defaults["user"]) && empty($defaults["pw"])) {
                throw new \InvalidArgumentException("Database connection parameters is missed.");
            }
            $user = $defaults["user"];
            $pass = $defaults["pw"];
        }
        $this->dbUser = $user;
        $this->dbPass = $pass;
        $this->dbName = $dbName;

        $this->dbHost = $host;
        if ( ! $this->dbHost && $unixSocket) {
            $this->unixSocket = $unixSocket;
        } else {
            $this->unixSocket = $defaults["socket"];
        }
        if ($port) {
            $this->dbPort = $port;
        }
        if ($persistent) {
            $this->persistent = $persistent;
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function enableDebug()
    {
        $this->debug = true;
    }

    /**
     * @return bool
     * @throws DbException
     */
    public function connect()
    {
        if ($this->client) {
            return true;
        }
        // open connection
        $this->client = mysqli_init();

        if ( ! $this->client) {
            throw new DbException("Connection error " . mysqli_connect_error(), 0);
        }
        mysqli_options($this->client, MYSQLI_INIT_COMMAND, "SET NAMES 'UTF8'");
        // SET NAMES x [COLLATE y] = (Eq.) = SET character_set_client = x; SET character_set_results = x; SET character_set_connection = x;

        $host = ($this->persistent ? "p:" : "") . $this->dbHost;

        // when host, port and unixsocket is null, will try connect via default unixsocket
        // when host and port is null, but unixsocket defined, lets try connect via unixsocket
        // when host defined as '127.0.0.1' and port is null, lets try connect via TCP/IP and default port
        // when host defined as 'localhost' and port is null, lets try connect via default unixsocket
        if ($this->debug) {
            $connected = mysqli_real_connect($this->client, $host, $this->dbUser, $this->dbPass, $this->dbName, $this->dbPort, $this->unixSocket);
        } else {
            $connected = @mysqli_real_connect($this->client, $host, $this->dbUser, $this->dbPass, $this->dbName, $this->dbPort, $this->unixSocket);
        }
        if (! $connected) {
            throw new DbException("Connection error. {$this->client->connect_error}", 0);
        }
        mysqli_set_charset($this->client, "utf8");

        return $connected;
    }


    /**
     * @param string $dbName
     * @throws DbException
     */
    public function selectDb($dbName = "")
    {
        // check use defaults
        if (empty($dbName)) {
            if ($this->dbName) {
                $dbName = $this->dbName;
            } else {
                throw new DbException("Db name is not resolved", 0);
            }
        }
        if ( ! $this->client) {
            $this->connect();
        }
        // select database
        $result = mysqli_select_db($this->client, $dbName);
        if ( ! $result) {
            throw new DbException("Can't select db", 0);
        }
    }

    public function setRowFetchMode($mode)
    {
        if (in_array($mode, [self::FETCH_AS_ARRAY, self::FETCH_AS_OBJECT])) {
            $this->fetchMode = $mode;
        } else {
            trigger_error('Row fetch mode is unknown.');
        }
    }

    public function setConnectTries($count)
    {
        if (is_integer($count)) {
            $this->reconnectRetries = $count;
        } else {
            trigger_error('Connection retries count must be integer.');
        }
    }

    public function getSelectedDb()
    {
        if (! $this->client) {
            $this->connect();
        }
        $row = "";
        if ($result = mysqli_query($this->client, "SELECT DATABASE()")) {
            $rows = mysqli_fetch_row($result);
            $row = $rows[0];
            mysqli_free_result($result);
        }
        return $row;
    }

    public function close()
    {
        if(! $this->client) {
            return true;
        }
        $closed = mysqli_close($this->client);
        if ($closed) {
            $this->client = null;
        }
        return $closed;
    }

    /**
     * @param string $sql
     * @param bool $useMode
     * @return \mysqli_result|array|bool|int
     * @throws DbException
     */
    public function query($sql, $useMode = false)
    {
        if (! is_string($sql)) {
            throw new \InvalidArgumentException("Sql must be a string, passed: " . var_export($sql, true));
        }
        $this->flush();

        // use_result - for big data, not save in memory, allow read (data_seek) without waiting when query finished
        // store_result - buffered result, for allow: num_rows, data_seek, (require free_result)
        $this->resultMode = $useMode ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT;

        $this->lastQuery = $sql;
        if ($this->debug) {
            $this->queryBenchmark($sql, $this->resultMode);
        } else {
            $this->result = @mysqli_query($this->client, $sql, $this->resultMode);
        }

        $errCode = mysqli_errno($this->client);
        if (empty($this->client) || 2006 == $errCode) {
            if ($this->heartbeatClient()) {
                if ($this->debug) {
                    $this->queryBenchmark($sql, $this->resultMode);
                } else {
                    $this->result = @mysqli_query($this->client, $sql, $this->resultMode);
                }
            }
        }
        // 1046 = No database selected

        if (! $this->result || 0 !== ($errCode = mysqli_errno($this->client))) {
            throw new DbException(
                "DB: [". $this->dbName .
                "] Query: [$sql] failed. File: [" . __FILE__ .
                "] MySQL error: [" . $errCode . " : " . mysqli_error($this->client) . "]",
                $this->result);
        }
        return $this->parseResults();
    }

    protected function queryBenchmark($sql, $mode)
    {
        $this->lastElapsedTime = microtime(true);
        $this->result = mysqli_query($this->client, $sql, $mode);
        $this->lastElapsedTime = microtime(true) - $this->lastElapsedTime;
    }

    public function parseResults()
    {
        if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $this->lastQuery)) {
            $returnVal = $this->result;
        } elseif (preg_match('/^\s*(insert|delete|update|replace)\s/i', $this->lastQuery)) {
            $this->lastAffectedRows = $this->getAffectedRows();
            if (preg_match( '/^\s*(insert|replace)\s/i', $this->lastQuery)) {
                $this->lastInsertId = $this->getInsertId();
            }
            // Return number of rows affected
            $returnVal = $this->lastAffectedRows;
        } else {
            if ($this->result instanceof mysqli_result) {
                $returnVal = $this->rowsToArray();
            } else {
                $returnVal = false;
            }
        }
        return $returnVal;
    }

    /**
     * @return bool
     * @throws DbException
     */
    private function heartbeatClient()
    {
        // NOTE! mysqli_ping refreshes connection id (if not persistent),
        // unset mysqli_affected_rows
        if (! empty($this->client) && mysqli_ping($this->client)) {
            return true;
        }
        $errorReporting = false;
        // Disable warnings, as we don't want to see a multitude of "unable to connect" messages
        if ($this->debug) {
            $errorReporting = error_reporting();
            error_reporting($errorReporting & ~E_WARNING);
        }
        for ($tries = 1; $tries <= $this->reconnectRetries; $tries++) {
            // On the last try, re-enable warnings. We want to see a single instance of the
            // "unable to connect" message on the bail() screen, if it appears.
            if ($this->reconnectRetries === $tries && $this->debug) {
                error_reporting($errorReporting);
            }
            if ($this->connect()) {
                if ($errorReporting) {
                    error_reporting($errorReporting);
                }
                return true;
            }
            sleep(1);
        }
        return false;
    }

    public function rowToObject()
    {
        if ($this->result instanceof mysqli_result) {
            $row = mysqli_fetch_object($this->result);
        } else {
            $row = null;
        }
        return $row;
    }

    public function rowToArray()
    {
        $row = null;
        if ($this->result instanceof mysqli_result) {
            $row = mysqli_fetch_array($this->result, MYSQLI_ASSOC);
        }
        return $row;
    }

    private function fetchRow()
    {
        switch ($this->fetchMode) {
            case self::FETCH_AS_OBJECT:
                return $this->rowToObject();
                break;
            default:
            case self::FETCH_AS_ARRAY:
                return $this->rowToArray();
                break;
        }
    }

    public function rowsToArray()
    {
        $numRows = 0;
        $rows = [];
        if (! empty($res = $this->getLastResult())) {
            return $res;
        }
        // NOTE! in MYSQLI_USE_RESULT mode
        // after mysqli_fetch_object / mysqli_fetch_assoc - new fetch's (including mysqli_fetch_all) will not work!
        // because usually need to free_result and new query

        if ($this->resultMode === MYSQLI_USE_RESULT) {
            // num_rows and data_seek not allowed here
            while ($row = $this->fetchRow()) {        // works for both: USE_RESULT, STORE_RESULT , but not works if was fetched
                $rows[$numRows] = $row;
                $numRows++;
            }
        } else {
            while(($this->seek($numRows)) !== false) {  // works for: STORE_RESULT
                $rows[] = $this->fetchRow();
                $numRows++;
            }
        }
        // Log number of rows the query returned
        $this->lastNumRows = $numRows;
        return $this->lastResult = $rows;
    }

    public function seek($iRowNum)
    {
        return @mysqli_data_seek($this->result, $iRowNum);
    }

    public function flush()
    {
        $this->lastQuery = "";
        $this->lastNumRows = 0;
        $this->lastInsertId = 0;
        $this->lastAffectedRows = 0;
        $this->lastResult = null;
        $this->resultMode = MYSQLI_STORE_RESULT;

        if ($this->result instanceof mysqli_result) {
            mysqli_free_result($this->result);

            // Sanity check before using the handle
            if (empty($this->client) || ! ($this->client instanceof mysqli)) {
                return;
            }
            // Clear out any results from a multi-query
            while (mysqli_more_results($this->client)) {
                mysqli_next_result($this->client);
            }
        }
        $this->result = null;
    }

    /**
     * @return bool
     * @throws DbException
     */
    public function begin()
    {
        // if need other isolation level for session
//        $this->query("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");

//        $this->query("SET AUTOCOMMIT=0");
        mysqli_autocommit($this->client, false);

        $this->inTransaction = true;
        if (function_exists('mysqli_begin_transaction')) {
            return mysqli_begin_transaction($this->client); // This method since PHP 5.5
        } else {
            $this->query("START TRANSACTION"); // release the locks and begin a new transaction in less consistent faster isolation level
            return true;
        }
    }

    /**
     * @return bool
     */
    public function commit()
    {
//        $this->query("COMMIT");
//        $this->query("SET AUTOCOMMIT=1");
        $res = mysqli_commit($this->client);
        mysqli_autocommit($this->client, true);
        $this->inTransaction = false;
        return $res;
    }

    /**
     * @return bool
     */
    public function rollback()
    {
//        $this->query("ROLLBACK");
//        $this->query("SET AUTOCOMMIT=1");
        $res = mysqli_rollback($this->client);
        mysqli_autocommit($this->client, true);
        $this->inTransaction = false;
        return $res;
    }

    /**
     * SQL injections preventing
     * @param $string
     * @return string
     */
    public function escape($string)
    {
        if ($this->client && $this->client instanceof mysqli) {
            return mysqli_real_escape_string($this->client, $string);
        } else {
            return addcslashes($string, "");
        }
    }

    /**
     * SQL injections preventing
     * symbols % and _ in LIKE clauses as injection
     * @param $string
     * @return string
     */
    public function escapeForLike($string)
    {
        if ($this->client && $this->client instanceof mysqli) {
            $string = mysqli_real_escape_string($this->client, $string);
            return addcslashes($string, "%_");
        } else {
            return addcslashes($string, "%_");
        }
    }

    /**
     * Escapes the special symbols with trailing backslash
     * @param  string $string
     * @return string
     */
    public static function escapeStr($string)
    {
        static $sqlEscape = [
            "\x00" => '\0',
            "\n" => '\n',
            "\r" => '\r',
            '\\' => '\\\\',
            '\'' => '\\\'',
            '"' => '\\"'
        ];
        return strtr($string, $sqlEscape);
    }

    /**
     * Escapes the special symbols with a trailing backslash
     * @param  string $string
     * @return string
     */
    public static function escapeForLikeStr($string)
    {
        static $sqlEscape = [
            "\x00" => '\0',
            "\n" => '\n',
            "\r" => '\r',
            '\\' => '\\\\',
            '\'' => '\\\'',
            '"' => '\\"',
            '%' => '\%',
            '_' => '\_'
        ];
        return strtr($string, $sqlEscape);
    }

    public function escapeArray(array $arr)
    {
        if ( ! is_array($arr)) {
            return $this->escape($arr);
        }
        $result = [];
        foreach($arr as $key => $element) {
            if (is_array($element)) {
                $result[$key] = $this->escapeArray($element);
            } else {
                $result[$key] = $this->escape($element);
            }
        }
        return $result;
    }

    public function getDsn()
    {
        return "mysqli:host={$this->dbHost};port={$this->dbPort};dbname={$this->dbName}";
    }


    public function getClient()
    {
        return $this->client;
    }

    public function getRowsNum()
    {
        return mysqli_num_rows($this->result);
    }

    /**
     * Column info type one of: name, table, db, def, max_length, length, type
     * @param string $type
     * @param int $index
     * @return null|array|string
     */
    public function getColumnsInfo($type = 'name', $index = -1)
    {
        if (! $this->result) {
            return null;
        }
        $colInfo = [];
//        $numFields = mysqli_num_fields($this->result);
//        for ( $i = 0; $i < $numFields; $i++ ) {
//            $colInfo[$i] = mysqli_fetch_field($this->result);
//        }
        // or:
        $colInfo = mysqli_fetch_fields($this->result);  //var_dump($colInfo);
        if ($index == -1) {
            $i = 0;
            $new = [];
            foreach ((array) $colInfo as $col) {
                $new[$i] = $col->{$type};
                $i++;
            }
            return $new;
        } else {
            return $colInfo[$index]->{$type};
        }
    }

    public function getLastResult()
    {
        return $this->lastResult;
    }

    /**
     * NOTE! value different from 0 will return only if primary key sets up to auto_increment
     * (not works with primary virtual index, if this column not crated manually)
     * @return int|string
     */
    public function getInsertId()
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return null;
        }
        return mysqli_insert_id($this->client);
    }

    public function getAffectedRows()
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return null;
        }
        return mysqli_affected_rows($this->client);
    }

    public function getConnectionId()
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return null;
        }
        return mysqli_thread_id($this->client);
    }

    /**
     * Get host and type of connection (TCP/IP or Unix socket)
     * @return string
     */
    public function getHostInfo()
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return null;
        }
        return mysqli_get_host_info($this->client);
    }

    public function getCharacterSet()
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return null;
        }
        return mysqli_character_set_name($this->client);
    }

    public static function getPhpDefaults()
    {
        /** @src http://php.net/manual/ru/mysqli.configuration.php */
        return [
            "host" => ini_get("mysqli.default_host"),
            "port" => ini_get("mysqli.default_port"),
            "user" => ini_get("mysqli.default_user"),
            "pw" => ini_get("mysqli.default_pw"),
            "socket" => ini_get("mysqli.default_socket"),
            "allow_persistent" => ini_get("mysqli.allow_persistent"),   // since PHP 5.3
            "max_persistent" => ini_get("mysqli.max_persistent"),   // since PHP 5.3, -1 no limit
            "max_process_links" => ini_get("mysqli.max_links"),
            "reconnect" => ini_get("mysqli.max_persistent"),
            "rollback_on_cached_plink" => ini_get("mysqli.rollback_on_cached_plink"),    // since PHP 5.6
        ];
    }

    public function getDbVersion()
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return null;
        }
        $serverInfo = mysqli_get_server_info($this->client);
        return preg_replace( '/[^0-9.].*/', '', $serverInfo);
    }

    private function checkSupport($capability)
    {
        $version = $this->getDbVersion();
        switch (strtolower($capability))
        {
            case 'collation':
            case 'group_concat':
            case 'subqueries':
                return version_compare($version, '4.1', '>=');
            case 'set_charset':
                return version_compare($version, '5.0.7', '>=');
            case 'utf8mb4':
                if (version_compare($version, '5.5.3', '<')) {
                    return false;
                }
                $clientVersion = mysqli_get_client_info();
                /*
                 * libmysql has supported utf8mb4 since 5.5.3, same as the MySQL server.
                 * mysqlnd has supported utf8mb4 since 5.0.9.
                 */
                if (false !== strpos($clientVersion, 'mysqlnd')) {
                    $clientVersion = preg_replace('/^\D+([\d.]+).*/', '$1', $clientVersion);
                    return version_compare($clientVersion, '5.0.9', '>=');
                } else {
                    return version_compare($clientVersion, '5.5.3', '>=');
                }
            case 'utf8mb4_520':
                return version_compare($version, '5.6', '>=');
            case 'transact_read_only':
                // Flag MYSQLI_TRANS_START_READ_ONLY allowed since MySQL 5.6.5 version
                // read-only for write operations or locking reads such as select ... for update
                // avoid overhead for setting up transaction ID
                return version_compare($version, '5.6.5', '>=');
        }
        return false;
    }

    /**
     * Determines the best charset and collation to use given a charset and collation.
     *
     * For example, when able, utf8mb4 should be used instead of utf8.
     *
     * @param string $charset The character set to check.
     * @param string $collate The collation to check.
     * @return array The most appropriate character set and collation to use.
     */
    public function getBestCharset($charset, $collate)
    {
        if (empty($this->client) || ! ($this->client instanceof mysqli)) {
            return compact('charset', 'collate');
        }
        if ('utf8' === $charset && $this->checkSupport('utf8mb4')) {
            $charset = 'utf8mb4';
        }
        if ('utf8mb4' === $charset && ! $this->checkSupport('utf8mb4')) {
            $charset = 'utf8';
            $collate = str_replace('utf8mb4_', 'utf8_', $collate);
        }
        if ('utf8mb4' === $charset) {
            // _general_ is outdated, so we can upgrade it to _unicode_, instead.
            if ( ! $collate || 'utf8_general_ci' === $collate) {
                $collate = 'utf8mb4_unicode_ci';
            } else {
                $collate = str_replace('utf8_', 'utf8mb4_', $collate);
            }
        }
        // _unicode_520_ is a better collation, we should use that when it's available.
        if ($this->checkSupport('utf8mb4_520') && 'utf8mb4_unicode_ci' === $collate) {
            $collate = 'utf8mb4_unicode_520_ci';
        }
        return compact('charset', 'collate');
    }

    /**
     * @param string $format
     * @param string $date
     * @return bool|int|string
     */
    public static function dateToFormat($format, $date)
    {
        if (empty($date)) {
            return false;
        }
        if ('G' == $format) {
            return strtotime($date . ' +0000');
        }
        $i = strtotime($date);
        if ('U' == $format) {
            return $i;
        }
        return date($format, $i);
    }

    /**
     * @param string $dateField
     * @return null|string|string[]
     */
    public static function dateToRfc3339($dateField)
    {
        $formatted = self::dateToFormat('c', $dateField);
        // Strip timezone information
        return preg_replace( '/(?:Z|[+-]\d{2}(?::\d{2})?)$/', '', $formatted);
    }

    /**
     * @param $fileName
     * @return array
     * @throws DbException
     * @throws \Exception
     */
    public function tryImportSqlFile($fileName)
    {
        $progressFilename = $fileName . '_filepointer'; // tmp file for progress
        $errorFilename = $fileName . '_error'; // tmp file for errors

        $queryCount = 0;
        $statusMsg = "";
        $details = "";

        //if file cannot be found throw error
        if (! file_exists($fileName)) {
            $statusMsg = "fail";
            $details = "Error: File not found.";
        } else {
            // Read in entire file
            $fp = fopen($fileName, 'r');

            // go to previous file position
            $filePosition = 0;
            if(file_exists($progressFilename)){
                $filePosition = file_get_contents($progressFilename);
                fseek($fp, $filePosition);
            }

            // Temporary variable, used to store current query
            $templine = '';
            // Loop through each line
            while (($line = fgets($fp, 1024000)) !== false) {
                // Skip it if it's a comment
                if (substr($line, 0, 2) == '--' || trim($line) == '') {
                    continue;
                }
                // Add this line to the current segment
                $templine .= $line;
                // If it has a semicolon at the end, it's the end of the query
                if (substr(trim($line), -1, 1) == ';') {

                    $this->query($templine);

                    // Reset temp variable to empty
                    $templine = '';
                    file_put_contents($progressFilename, ftell($fp)); // save the current file position
                    $queryCount++;
                }
            }

            if (feof($fp)) {
                $statusMsg = "success";
                @unlink($progressFilename);
            } else {
                $statusMsg = "partly";
                $details = ftell($fp).'/'.filesize($fileName).' '.(round(ftell($fp)/filesize($fileName), 2)*100).'%';
            }
            fclose($fp);
            $this->close();
        }
        return [
            "status" => $statusMsg,
            "details" => $details,
            "queries" => $queryCount
        ];
    }
}
