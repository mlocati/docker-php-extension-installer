<?php

error_reporting(-1);

set_error_handler(
    static function ($errno, $errstr, $errfile, $errline) {
        $msg = "Error {$errno}: {$errstr}\n";
        if ($errfile) {
            $msg .= "File: {$errfile}\n";
            if ($errline) {
                $msg .= "Line: {$errline}\n";
            }
        }
        fwrite(STDERR, $msg);
        exit(1);
    },
    -1
);
