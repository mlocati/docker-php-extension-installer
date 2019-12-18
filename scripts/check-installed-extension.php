<?php

$rc = 0;
$numTestedExtensions = 0;
$nameMap = array(
    'opcache' => 'Zend OPcache',
);
for ($index = 1, $count = isset($argv) ? count($argv) : 0; $index < $count; $index++) {
    $numTestedExtensions++;
    $rcThis = 1;
    $extension = $argv[$index];
    if ($extension === '') {
        fprintf(STDERR, "Missing extension handle.\n");
    } else {
        $extensionLowerCase = strtolower($extension);
        if (isset($nameMap[$extensionLowerCase])) {
            $extension = $nameMap[$extensionLowerCase];
        }
        if (!extension_loaded($extension)) {
            fprintf(STDERR, sprintf("Extension not loaded: %s\n", $extension));
        } else {
            fprintf(STDOUT, sprintf("Extension correctly loaded: %s\n", $extension));
            $rcThis = 0;
        }
    }
    if ($rcThis !== 0) {
        $rc = $rcThis;
    }
}
if ($numTestedExtensions === 0) {
    fprintf(STDERR, "No extension handles specified.\n");
    $rc = 1;
}

exit($rc);
