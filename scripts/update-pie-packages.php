<?php

declare(strict_types=1);

/*
 * Update the generated PIE blocks in install-php-extensions.
 *
 * Usage:
 *   php scripts/update-pie-packages.php
 *   php scripts/update-pie-packages.php --check
 *
 * The --check mode exits with a non-zero code if the generated blocks are out of date.
 */

const ROOT_DIR = __DIR__ . '/..';

const PIE_PACKAGES_PATH = ROOT_DIR . '/data/pie-packages';

const SUPPORTED_EXTENSIONS_PATH = ROOT_DIR . '/data/supported-extensions';

const INSTALLER_PATH = ROOT_DIR . '/install-php-extensions';

const START_PACKAGES_MARKER = "\t\t# START OF PIE PACKAGES (generated from data/pie-packages)";

const END_PACKAGES_MARKER = "\t\t# END OF PIE PACKAGES (generated from data/pie-packages)";

const START_MIN_PHP_MARKER = "\t\t# START OF PIE MINIMUM PHP VERSIONS (generated from data/pie-packages)";

const END_MIN_PHP_MARKER = "\t\t\t# END OF PIE MINIMUM PHP VERSIONS (generated from data/pie-packages)";

const START_UNSUPPORTED_MARKER = "\t\t# START OF PIE UNSUPPORTED DISTROS (generated from data/pie-packages)";

const END_UNSUPPORTED_MARKER = "\t\t\t# END OF PIE UNSUPPORTED DISTROS (generated from data/pie-packages)";

/**
 * @return array<string, array{minimumPhpVersion: string, package: string, distributions: string[]}> extension name => PIE package
 */
function readPiePackages(): array
{
    $contents = file_get_contents(PIE_PACKAGES_PATH);
    if ($contents === false) {
        throw new RuntimeException('Failed to read ' . PIE_PACKAGES_PATH);
    }
    $supportedExtensions = [];
    $supportedContents = file_get_contents(SUPPORTED_EXTENSIONS_PATH);
    if ($supportedContents === false) {
        throw new RuntimeException('Failed to read ' . SUPPORTED_EXTENSIONS_PATH);
    }
    foreach (preg_split('/\R/', $supportedContents, -1, PREG_SPLIT_NO_EMPTY) as $line) {
        $words = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        if ($words !== []) {
            $supportedExtensions[$words[0]] = true;
        }
    }
    $result = [];
    $packages = [];
    foreach (preg_split('/\R/', $contents, -1, PREG_SPLIT_NO_EMPTY) as $lineNumber => $line) {
        $words = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) < 3) {
            throw new RuntimeException('Line ' . ($lineNumber + 1) . ' of ' . PIE_PACKAGES_PATH . ' must contain at least three words: the extension name, the minimum PHP version, and the PIE package name');
        }
        $extension = $words[0];
        $minimumPhpVersion = $words[1];
        $package = $words[2];
        $distributions = array_slice($words, 3);
        if (!preg_match('/^[a-z0-9_]+$/', $extension)) {
            throw new RuntimeException('Invalid extension name in ' . PIE_PACKAGES_PATH . ': ' . $extension);
        }
        if (!isset($supportedExtensions[$extension])) {
            throw new RuntimeException('The extension ' . $extension . ' is not listed in ' . SUPPORTED_EXTENSIONS_PATH);
        }
        if (!preg_match('/^[0-9]+\.[0-9]+$/', $minimumPhpVersion) || !version_compare($minimumPhpVersion, '8.1', '>=')) {
            throw new RuntimeException('Invalid minimum PHP version in ' . PIE_PACKAGES_PATH . ': ' . $minimumPhpVersion);
        }
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/', $package)) {
            throw new RuntimeException('Invalid PIE package name in ' . PIE_PACKAGES_PATH . ': ' . $package);
        }
        foreach ($distributions as $distribution) {
            if (!preg_match('/^![a-z0-9.]+$/', $distribution)) {
                throw new RuntimeException('Invalid distribution exclusion in ' . PIE_PACKAGES_PATH . ': ' . $distribution);
            }
            getDistroVersion(substr($distribution, 1));
        }
        if (isset($result[$extension])) {
            throw new RuntimeException('Duplicated extension in ' . PIE_PACKAGES_PATH . ': ' . $extension);
        }
        if (isset($packages[$package])) {
            throw new RuntimeException('Duplicated PIE package in ' . PIE_PACKAGES_PATH . ': ' . $package);
        }
        $result[$extension] = [
            'minimumPhpVersion' => $minimumPhpVersion,
            'package' => $package,
            'distributions' => $distributions,
        ];
        $packages[$package] = true;
    }
    if ($result === []) {
        throw new RuntimeException(PIE_PACKAGES_PATH . ' is empty');
    }
    ksort($result, SORT_STRING);

    return $result;
}

/**
 * Get the value of DISTRO_VERSION for a distribution handle used in data/ci-distros.
 */
function getDistroVersion(string $distribution): string
{
    $debianVersions = [
        'jessie' => '8',
        'stretch' => '9',
        'buster' => '10',
        'bullseye' => '11',
        'bookworm' => '12',
        'trixie' => '13',
    ];
    if (isset($debianVersions[$distribution])) {
        return 'debian@' . $debianVersions[$distribution];
    }
    if (preg_match('/^alpine([0-9]+\.[0-9]+)$/', $distribution, $matches)) {
        return 'alpine@' . $matches[1];
    }

    throw new RuntimeException('Unknown distribution in ' . PIE_PACKAGES_PATH . ': ' . $distribution);
}

/**
 * @param array<string, array{minimumPhpVersion: string, package: string, distributions: string[]}> $packages
 */
function generatePackagesBlock(array $packages): string
{
    $lines = [];
    foreach ($packages as $extension => $row) {
        $lines[] = "\t\t{$extension})";
        $lines[] = "\t\t\tprintf '%s' '{$row['package']}'";
        $lines[] = "\t\t\t;;";
    }

    return implode("\n", $lines);
}

/**
 * @param array<string, array{minimumPhpVersion: string, package: string, distributions: string[]}> $packages
 */
function generateMinimumPhpBlock(array $packages): string
{
    $lines = [];
    foreach ($packages as $extension => $row) {
        if (version_compare($row['minimumPhpVersion'], '8.1', '<=')) {
            continue;
        }
        [$major, $minor] = array_map('intval', explode('.', $row['minimumPhpVersion']));
        $lines[] = "\t\t{$extension})";
        $lines[] = "\t\t\tif test \$PHP_MAJMIN_VERSION -lt " . ($major * 100 + $minor) . '; then';
        $lines[] = "\t\t\t\treturn 1";
        $lines[] = "\t\t\tfi";
        $lines[] = "\t\t\t;;";
    }

    return implode("\n", $lines);
}

/**
 * @param array<string, array{minimumPhpVersion: string, package: string, distributions: string[]}> $packages
 */
function generateUnsupportedDistrosBlock(array $packages): string
{
    $lines = [];
    foreach ($packages as $extension => $row) {
        if ($row['distributions'] === []) {
            continue;
        }
        $versions = [];
        foreach ($row['distributions'] as $distribution) {
            $versions[] = getDistroVersion(substr($distribution, 1));
        }
        sort($versions, SORT_NATURAL);
        $lines[] = "\t\t{$extension})";
        $lines[] = "\t\t\tcase \"\$DISTRO_VERSION\" in";
        $lines[] = "\t\t\t\t" . implode(' | ', $versions) . ')';
        $lines[] = "\t\t\t\t\treturn 1";
        $lines[] = "\t\t\t\t\t;;";
        $lines[] = "\t\t\tesac";
        $lines[] = "\t\t\t;;";
    }

    return implode("\n", $lines);
}

function replaceBlock(string $installer, string $startMarker, string $endMarker, string $block, string $name): string
{
    $start = strpos($installer, $startMarker);
    if ($start === false) {
        throw new RuntimeException("Unable to find the {$name} start marker in " . INSTALLER_PATH);
    }
    $end = strpos($installer, $endMarker, $start);
    if ($end === false) {
        throw new RuntimeException("Unable to find the {$name} end marker in " . INSTALLER_PATH);
    }
    $prefix = substr($installer, 0, $start);

    return $prefix . $startMarker . "\n" . $block . "\n" . substr($installer, $end);
}

function main(): int
{
    try {
        $packages = readPiePackages();
        $installer = file_get_contents(INSTALLER_PATH);
        if ($installer === false) {
            throw new RuntimeException('Failed to read ' . INSTALLER_PATH);
        }
        $updated = replaceBlock($installer, START_PACKAGES_MARKER, END_PACKAGES_MARKER, generatePackagesBlock($packages), 'PIE packages');
        $updated = replaceBlock($updated, START_MIN_PHP_MARKER, END_MIN_PHP_MARKER, generateMinimumPhpBlock($packages), 'PIE minimum PHP versions');
        $updated = replaceBlock($updated, START_UNSUPPORTED_MARKER, END_UNSUPPORTED_MARKER, generateUnsupportedDistrosBlock($packages), 'PIE unsupported distributions');
        if (in_array('--check', $_SERVER['argv'] ?? [], true)) {
            if ($updated !== $installer) {
                fwrite(STDERR, "The generated PIE blocks in install-php-extensions are out of date.\nRun: php scripts/update-pie-packages.php\n");

                return 1;
            }

            return 0;
        }
        if (file_put_contents(INSTALLER_PATH, $updated) === false) {
            throw new RuntimeException('Failed to write ' . INSTALLER_PATH);
        }
    } catch (RuntimeException $x) {
        fwrite(STDERR, $x->getMessage() . "\n");

        return 1;
    }

    return 0;
}

exit(main());
