<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 05.12.2018
 * Time: 1:14
 */

namespace SimpleLibs\Db;


/**
 * Interface HandlerSocketInterface for client
 * for highlighting code and autocomplete functions from external extension php-handlersocket
 */
interface HandlerSocketInterface
{
    /**
     * @param string $host
     * @param int $port
     * @param array $options
     */
    function __construct ($host, $port, $options = []);

    /**
     * @param int $id
     * @param string $db
     * @param string $table
     * @param string $index
     * @param string $fields
     * @return bool
     */
    public function openIndex ($id, $db, $table, $index, $fields );

    /**
     * @param int $id
     * @param string $op
     * @param array $fields
     * @param int $limit
     * @param int $skip
     * @param string $modop
     * @param array $values
     * @return mixed
     */
    public function executeSingle ($id, $op, $fields, $limit = null, $skip = null, $modop = null, $values = null);

    /**
     * @param array $requests
     * @return mixed
     */
    public function executeMulti ($requests);

    /**
     * @param int $id
     * @param string $op
     * @param array $fields
     * @param int $limit
     * @param int $skip
     * @return int
     */
    public function executeUpdate ($id, $op, $fields, $limit = null, $skip = null);

    /**
     * @param int $id
     * @param string $op
     * @param array $fields
     * @param int $limit
     * @param int $skip
     * @return int
     */
    public function executeDelete ($id, $op, $fields, $limit = null, $skip = null);

    /**
     * @param int $id
     * @param array $values
     * @return bool
     */
    public function executeInsert ($id, $values);

    /**
     * @return string
     */
    public function getError ();
}
