<?php

$extension = isset($argv[1]) ? trim($argv[1]) : '';

if ($extension === '') {
    fprintf(STDERR, "Missing module handle.\n");
    exit(1);
}
$nameMap = array(
    'opcache' => 'Zend OPcache',
);
$extensionLowerCase = strtolower($extension);
if (isset($nameMap[$extensionLowerCase])) {
    $extension = $nameMap[$extensionLowerCase];
}

if (extension_loaded($extension)) {
    fprintf(STDOUT, sprintf("Extension correctly loaded: %s\n", $extension));
    exit(0);
}
fprintf(STDERR, sprintf("Extension not loaded: %s\n", $extension));
exit(1);
