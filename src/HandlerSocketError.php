<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 18.03.2019
 * Time: 11:30
 */

namespace SimpleLibs\Db;

class HandlerSocketError
{
    public static function throwNormalError($errorCode)
    {
        switch ($errorCode) {
            case 'cmd':
            case 'syntax':
            case 'notimpl':
                $errorMsg = "Problems with parsing command";
                break;
            case 'authtype':
            case 'unauth':
                $errorMsg = "You need to authenticate before execute commands";
                break;
            case 'open_table':
                $errorMsg = "Something goes wrong or wrong DB/table name";
                break;
            case 'tblnum':
            case 'stmtnum':
                $errorMsg = "You try to use un initialized index number";
                break;
            case 'invalueslen':
                $errorMsg = "Wrong IN values list size";
                break;
            case 'filtertype':
                $errorMsg = "Wrong filter TYPE";
                break;
            case 'filterfld':
                $errorMsg = "You filter column size < filter offset";
                break;
            case 'lock_tables':
            case 'modop':
                $errorMsg = "You try to open locked table";
                break;
            case 'idxnum':
                $errorMsg = "Key index > opened columns count";
                break;
            case 'kpnum':
            case 'klen':
                $errorMsg = "Key length > key values or key length <= 0";
                break;
            case 'op':
                $errorMsg = "Unknown comparison operator, you can use only '>', '<', '>=', '<='.";
                break;
            case 'readonly':
                $errorMsg = "You try to execute modify command on read only socket";
                break;
            case 'fld':
                $errorMsg = "Something goes wrong on parse column or filter column";
                break;
            //case 'filterblob': // unknown error TODO
            // response_buf_remove: protocol out of sync
            // read: eof
            default:
                // Errors with wrong data
                if (is_numeric($errorCode)) {
                    $errorMsg = "If you try to insert data with wrong values, wrong keys (internal MySQL errors)";
                } else {
                    $errorMsg = "Unknown error";
                }
                break;
        }
        return "[$errorCode] $errorMsg";
    }
}
