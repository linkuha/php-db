<?php

namespace SimpleLibs\Db;

use \Exception;
use \HandlerSocket;

/**
 * Class HandlerSocketMySql
 *
 * InnoDB as NoSQL
 */
class HandlerSocketMySql
{
    const DEFAULT_READ_PORT = 9998;
    const DEFAULT_WRITE_PORT = 9999;

    private $wPort;
    private $rPort;

    /**
     * @var null|HandlerSocketInterface
     */
    private $_handlerSocket = null;
    private $_indexes = [];
    private $_lastOpenIndex = 0;

    private static $Inst = 0;

    private function __construct($host, $dbName, $wPort, $rPort)
    {
        $this->host = $host;
        $this->dbName = $dbName;
        $this->wPort = $wPort;
        $this->rPort = $rPort;
    }

    /**
     * @param $host
     * @param $dbName
     * @param int $wPort
     * @param int $rPort
     * @return HandlerSocketMySql
     */
    public static function getInstance($host, $dbName, $wPort = self::DEFAULT_WRITE_PORT, $rPort = self::DEFAULT_READ_PORT)
    {
        if ( ! self::$Inst) {
            self::$Inst = new HandlerSocketMySql($host, $dbName, $wPort, $rPort);
        }
        return self::$Inst;
    }

    public function selectStorage($host, $db)
    {
        $this->host = $host;
        $this->dbName = $db;
    }

    /**
     * @throws DbException
     */
    public function connectWriter()
    {
        try {
            $this->_handlerSocket = new HandlerSocket($this->host, $this->wPort);
        } catch (Exception $e) {
            throw new DbException("Connect error: " . $e->getMessage() . PHP_EOL, 0);
        }
    }

    /**
     * @param string $table
     * @param string $rows (in mysql context this is columns)
     * @param array $values
     * @param array $ignoreErrors
     * @return bool
     * @throws DbException
     */
    public function insert($table, $rows, $values, $ignoreErrors = [])
    {
        // filter fields (don't allow spaces between its in query)
        $rows = $this->escapeWhitespaces($rows);

        if ($this->_handlerSocket == null) {
            $this->connectWriter();
        }

        $index = @$this->_indexes[$table . $rows];
        if (empty($index)) {
            $this->_lastOpenIndex++;
            $this->_indexes[$table . $rows] = $this->_lastOpenIndex;
            $index = $this->_lastOpenIndex;
            if ( ! ($this->_handlerSocket->openIndex($index, $this->dbName, $table, '', $rows))) {
                $errorMsg = HandlerSocketError::throwNormalError($this->_handlerSocket->getError());
                throw new DbException("HandlerSocket " . $table . " failed to connect. Message: " . $errorMsg, 0);
            }
        }

        if ($this->_handlerSocket->executeInsert($index, $values) === false) {
            if ( ! in_array($this->_handlerSocket->getError(), $ignoreErrors)) {
                $errorMsg = HandlerSocketError::throwNormalError($this->_handlerSocket->getError());
                throw new DbException("HandlerSocket " . $table . " failed. Message: " . $errorMsg, 0);
            }
        }
        return true;
    }

    /**
     * @param string $str
     * @return string
     */
    public function escapeWhitespaces($str)
    {
        return preg_replace("~\s+~", "", $str);
    }
}
