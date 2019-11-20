<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 26.11.2018
 * Time: 2:13
 */

namespace SimpleLibs\Db;

class DbException extends \Exception
{
    public function __construct($iMessage, $iCode)
    {
        parent::__construct($iMessage, $iCode);
    }
}
