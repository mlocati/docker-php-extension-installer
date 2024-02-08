<?php

$rc = 0;
$numTestedExtensions = 0;
$nameMap = [
    'opcache' => 'Zend OPcache',
    'apcu_bc' => 'apc',
    'ioncube_loader' => 'ionCube Loader',
    'saxon' => 'saxonc',
];
if (PHP_VERSION_ID < 70000) {
    $nameMap['sodium'] = 'libsodium';
} else {
    $nameMap['libsodium'] = 'sodium';
}
$testsDir = __DIR__ . '/tests';
function runTest($testFile)
{
    $rc = -1;
    passthru(escapeshellarg($testFile) . ' ' . PHP_VERSION_ID, $rc);

    return $rc === 0;
}

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
            $testFile = "{$testsDir}/{$extensionLowerCase}";
            if (is_file($testFile)) {
                try {
                    if (runTest($testFile) === true) {
                        fprintf(STDOUT, sprintf("Extension tested successfully: %s\n", $extension));
                        $rcThis = 0;
                    } else {
                        fprintf(STDERR, sprintf("Extension test failed: %s\n", $extension));
                    }
                } catch (Exception $x) {
                    fprintf(STDERR, sprintf("Extension test failed: %s (%s)\n", $extension, $x->getMessage()));
                } catch (Throwable $x) {
                    fprintf(STDERR, sprintf("Extension test failed: %s (%s)\n", $extension, $x->getMessage()));
                }
            } else {
                fprintf(STDOUT, sprintf("Extension correctly loaded: %s\n", $extension));
                $rcThis = 0;
            }
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
