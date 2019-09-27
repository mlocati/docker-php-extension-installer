<?php

$extension = isset($argv[1]) ? trim($argv[1]) : '';
$rc = 1;

if ($extension === '') {
    fprintf(STDERR, "Missing module handle.\n");
} else {
    $nameMap = array(
        'opcache' => 'Zend OPcache',
    );
    $extensionLowerCase = strtolower($extension);
    if (isset($nameMap[$extensionLowerCase])) {
        $extension = $nameMap[$extensionLowerCase];
    }
    if (!extension_loaded($extension)) {
        fprintf(STDERR, sprintf("Extension not loaded: %s\n", $extension));
    } else {
        fprintf(STDOUT, sprintf("Extension correctly loaded: %s\n", $extension));
        $rc = 0;
    }
}

exit($rc);
