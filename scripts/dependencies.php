<?php

declare(strict_types=1);

/*
 * Functions to read the libraries and PHP extensions that install-php-extensions downloads manually
 * (listed in data/dependencies.json).
 */

const DEPENDENCIES_PATH = __DIR__ . '/../data/dependencies.json';

/**
 * Read data/dependencies.json.
 *
 * @throws RuntimeException
 *
 * @return array{libraries: array<string, array<string, mixed>>, extensions: array<string, array<string, mixed>>}
 */
function readDependencies(): array
{
    $json = file_get_contents(DEPENDENCIES_PATH);
    if ($json === false) {
        throw new RuntimeException('Failed to read data/dependencies.json');
    }

    try {
        $dependencies = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $x) {
        throw new RuntimeException("Failed to parse data/dependencies.json: {$x->getMessage()}");
    }

    // The structure is checked by scripts/check-json-schemas.php
    return [
        'libraries' => $dependencies['libraries'],
        'extensions' => $dependencies['extensions'],
    ];
}

/**
 * Get the name of the install-php-extensions variable that contains the version of a dependency whose version is pinned.
 *
 * @param string $section 'libraries' or 'extensions'
 * @param string $key     the key of the dependency in data/dependencies.json
 */
function getDependencyVariable(string $section, string $key): string
{
    return match ($section) {
        'libraries' => "IPE_LIBVERSION_{$key}",
        'extensions' => 'IPE_EXTVERSION_' . strtoupper($key),
    };
}

/**
 * Get the names of the IPE_LIBVERSION_... and IPE_EXTVERSION_... variables defined in install-php-extensions.
 *
 * @return string[]
 */
function getInstallerDependencyVariables(string $installer): array
{
    preg_match_all('/^(IPE_(?:LIBVERSION|EXTVERSION)_\w+)=/m', $installer, $matches);

    return $matches[1];
}

/**
 * Get the default value of a variable defined in install-php-extensions as VARIABLE="${VARIABLE:-default value}".
 */
function getInstallerVariableDefault(string $installer, string $variable): ?string
{
    $quotedVariable = preg_quote($variable, '/');

    return preg_match('/^' . $quotedVariable . '="\$\{' . $quotedVariable . ':-(\S+)\}"$/m', $installer, $matches) ? $matches[1] : null;
}
