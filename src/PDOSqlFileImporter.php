<?php
/**
 * Created by PhpStorm.
 * User: linkuha (Pudich Aleksandr)
 * Date: 21.02.2019
 * Time: 15:26
 */

namespace SimpleLibs\Db;

class PDOSqlFileImporter
{
    public static function tryImport(\PDO $pdoInstance, $fileName)
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
            if (file_exists($progressFilename)) {
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

                    $prep = $pdoInstance->prepare($templine);

                    try {
                        if (! ($prep->execute())) {
                            $error = 'Error performing query \'' . $templine . '\': ' .
                                $pdoInstance->errorCode() . " - " .
                                var_export($pdoInstance->errorInfo(), true);
                            file_put_contents($errorFilename, $error . "\n");
                            @unlink($progressFilename);
                            return [
                                "status" => "fail",
                                "details" => $error,
                                "queries" => $queryCount
                            ];
                        }
                    } catch (\PDOException $err) {
                        $error = 'Error catch performing query \'' . $templine . '\': ' .
                            $pdoInstance->errorCode() . " - " .
                            var_export($pdoInstance->errorInfo(), true);
                        file_put_contents($errorFilename, $error . "\n");
                        @unlink($progressFilename);
                        return [
                            "status" => "fail",
                            "details" => $error,
                            "queries" => $queryCount
                        ];
                    }


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
                $details = ftell($fp) . '/' . filesize($fileName) . ' ' . (round(ftell($fp) / filesize($fileName), 2) * 100) . '%';
            }
            fclose($fp);
        }
        return [
            "status" => $statusMsg,
            "details" => $details,
            "queries" => $queryCount
        ];
    }
}
