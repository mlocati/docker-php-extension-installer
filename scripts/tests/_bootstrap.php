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

/**
 * @example ['alpine', '3.18']
 * @example ['debian', 13]
 */
function inspectOS()
{
    $osRelease = file_get_contents('/etc/os-release');
    if (!preg_match('/^ID=(.+)$/m', $osRelease, $matches)) {
        fwrite(STDERR, "Unable to determine OS from /etc/os-release\n");
        exit(1);
    }
    $id = trim($matches[1], '"');
    if (!preg_match('/^VERSION_ID=(.+)$/m', $osRelease, $matches)) {
        fwrite(STDERR, "Unable to determine OS version from /etc/os-release\n");
        exit(1);
    }
    $versionID = trim($matches[1], '"');
    switch ($id) {
        case 'alpine':
            return [$id, $versionID];
        case 'debian':
            return [$id, (int) $versionID];
        default:
            fwrite(STDERR, "Unsupported OS: {$id}\n");
            exit(1);
    }
}
