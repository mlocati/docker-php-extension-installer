<?php

declare(strict_types=1);

/*
 * Update the automatically generated sections of README.md:
 * - the table of the supported PHP extensions (from data/supported-extensions)
 * - the table of the special requirements (from data/special-requirements)
 * - the table of the libraries whose version can be configured (from data/dependencies.json and install-php-extensions)
 */

require_once __DIR__ . '/dependencies.php';

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

const ROOT_DIR = __DIR__ . '/..';

const README_PATH = ROOT_DIR . '/README.md';

const SUPPORTED_EXTENSIONS_PATH = ROOT_DIR . '/data/supported-extensions';

const SPECIAL_REQUIREMENTS_PATH = ROOT_DIR . '/data/special-requirements';

const INSTALLER_PATH = ROOT_DIR . '/install-php-extensions';

/**
 * Read a data file.
 *
 * @throws RuntimeException
 *
 * @return string[][] every item contains the non-empty words of a line (lines without words are skipped), sorted by line
 */
function readDataFile(string $path): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Failed to read {$path}");
    }
    $lines = preg_split('/\R/', $contents, -1, PREG_SPLIT_NO_EMPTY);
    sort($lines, SORT_STRING);
    $result = [];
    foreach ($lines as $line) {
        $words = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($words !== []) {
            $result[] = $words;
        }
    }

    return $result;
}

/**
 * The width of the text inside the box at the beginning of the automatically generated sections.
 */
const GENERATED_SECTION_HEADER_WIDTH = 53;

/**
 * Get the header of an automatically generated section.
 *
 * @throws RuntimeException
 *
 * @return string[]
 */
function getGeneratedSectionHeader(string $dataFile): array
{
    $border = '<!-- ' . str_repeat('#', GENERATED_SECTION_HEADER_WIDTH + 6) . ' -->';
    $lines = [$border];
    $texts = [
        '',
        'DO NOT EDIT THIS TABLE: IT IS GENERATED AUTOMATICALLY',
        '',
        "EDIT THE {$dataFile} FILE INSTEAD",
        '',
    ];
    foreach ($texts as $text) {
        if (strlen($text) > GENERATED_SECTION_HEADER_WIDTH) {
            throw new RuntimeException("The text '{$text}' is longer than " . GENERATED_SECTION_HEADER_WIDTH . ' characters');
        }
        $lines[] = '<!-- #  ' . str_pad($text, GENERATED_SECTION_HEADER_WIDTH) . '  # -->';
    }
    $lines[] = $border;

    return $lines;
}

/**
 * Generate the markdown table with the supported PHP extensions.
 *
 * @param string[][] $supportedExtensions
 * @param string[][] $specialRequirements
 *
 * @return string[]
 */
function generateExtensionsTable(array $supportedExtensions, array $specialRequirements): array
{
    $extensionsWithSpecialRequirements = array_map(static fn (array $words): string => $words[0], $specialRequirements);
    $phpVersions = [];
    foreach ($supportedExtensions as $words) {
        $phpVersions = array_merge($phpVersions, array_slice($words, 1));
    }
    $phpVersions = array_values(array_unique($phpVersions));
    usort($phpVersions, static fn (string $a, string $b): int => version_compare($b, $a));
    $lines = [
        '| Extension |' . implode('', array_map(static fn (string $phpVersion): string => " PHP {$phpVersion} |", $phpVersions)),
        '|:---:|' . str_repeat(':---:|', count($phpVersions)),
    ];
    foreach ($supportedExtensions as $words) {
        $extension = $words[0];
        $line = "| {$extension}";
        if (in_array($extension, $extensionsWithSpecialRequirements, true)) {
            $line .= "[*](#special-requirements-for-{$extension})";
        }
        $line .= ' |';
        foreach ($phpVersions as $phpVersion) {
            $line .= in_array($phpVersion, $words, true) ? ' &check; |' : '  |';
        }
        $lines[] = $line;
    }
    $lines[] = '';
    $lines[] = '*Number of supported extensions: ' . count($supportedExtensions) . '*';

    return $lines;
}

/**
 * Get the description of a special requirement.
 */
function describeSpecialRequirement(string $requirement): string
{
    if ($requirement === 'zts') {
        return 'Requires images with PHP compiled with thread-safety enabled (`zts`)';
    }
    if ($requirement === '!arm') {
        return 'Not available in ARM architectures';
    }
    if (str_starts_with($requirement, '!')) {
        return 'Not available in `' . substr($requirement, 1) . '` docker images';
    }

    return $requirement;
}

/**
 * Generate the markdown table with the special requirements of the PHP extensions.
 *
 * @param string[][] $specialRequirements
 *
 * @return string[]
 */
function generateSpecialRequirementsTable(array $specialRequirements): array
{
    $lines = [];
    foreach ($specialRequirements as $words) {
        $extension = array_shift($words);
        if ($words === []) {
            continue;
        }
        if ($lines === []) {
            $lines[] = '| Extension | Requirements |';
            $lines[] = '|---|---|';
        }
        $descriptions = array_map('describeSpecialRequirement', $words);
        $requirements = count($descriptions) === 1 ? $descriptions[0] : '&bull; ' . implode('<br />&bull; ', $descriptions);
        $lines[] = "| <a name=\"special-requirements-for-{$extension}\"></a>{$extension} | {$requirements} |";
    }

    return $lines;
}

/**
 * Generate the markdown table with the libraries whose version can be configured.
 *
 * @param array<string, array<string, mixed>> $libraries the libraries listed in data/dependencies.json
 *
 * @throws RuntimeException
 *
 * @return string[]
 */
function generateLibrariesTable(array $libraries, string $installer): array
{
    $lines = [
        '| Library | Environment variable | Default version | Used by | Notes |',
        '|---|---|---|---|---|',
    ];
    foreach ($libraries as $key => $library) {
        $variable = getDependencyVariable('libraries', (string) $key);
        $defaultVersion = getInstallerVariableDefault($installer, $variable);
        if ($defaultVersion === null) {
            throw new RuntimeException("Unable to find the {$variable} variable in install-php-extensions");
        }
        $name = isset($library['url']) ? "[{$library['name']}]({$library['url']})" : $library['name'];
        $usedBy = implode(', ', $library['usedBy']);
        $notes = $library['notes'] ?? '';
        $lines[] = "| {$name} | `{$variable}` | `{$defaultVersion}` | {$usedBy} | {$notes} |";
    }

    return $lines;
}

/**
 * Replace the contents between the "<!-- START OF <section> -->" and "<!-- END OF <section> -->" lines.
 *
 * @param string[] $lines
 * @param string[] $newContents
 *
 * @throws RuntimeException
 *
 * @return string[]
 */
function replaceSection(array $lines, string $section, array $newContents): array
{
    $startPlaceholder = "<!-- START OF {$section} -->";
    $endPlaceholder = "<!-- END OF {$section} -->";
    $start = array_search($startPlaceholder, $lines, true);
    if ($start === false) {
        throw new RuntimeException("Unable to find the line {$startPlaceholder} in README.md");
    }
    $end = array_search($endPlaceholder, $lines, true);
    if ($end === false || $end < $start) {
        throw new RuntimeException("Unable to find the line {$endPlaceholder} after the line {$startPlaceholder} in README.md");
    }
    array_splice($lines, $start + 1, $end - $start - 1, $newContents);

    return $lines;
}

function main(): int
{
    try {
        $supportedExtensions = readDataFile(SUPPORTED_EXTENSIONS_PATH);
        $specialRequirements = readDataFile(SPECIAL_REQUIREMENTS_PATH);
        $libraries = readDependencies()['libraries'];
        $installer = file_get_contents(INSTALLER_PATH);
        if ($installer === false) {
            throw new RuntimeException('Failed to read install-php-extensions');
        }
        $readme = file_get_contents(README_PATH);
        if ($readme === false) {
            throw new RuntimeException('Failed to read README.md');
        }
        $lines = explode("\n", rtrim($readme, "\n"));
        $lines = replaceSection(
            $lines,
            'EXTENSIONS TABLE',
            array_merge(getGeneratedSectionHeader('data/supported-extensions'), generateExtensionsTable($supportedExtensions, $specialRequirements)),
        );
        $lines = replaceSection(
            $lines,
            'SPECIAL REQUIREMENTS',
            array_merge(getGeneratedSectionHeader('data/special-requirements'), generateSpecialRequirementsTable($specialRequirements)),
        );
        $lines = replaceSection(
            $lines,
            'LIBRARIES',
            array_merge(getGeneratedSectionHeader('data/dependencies.json'), generateLibrariesTable($libraries, $installer)),
        );
        if (file_put_contents(README_PATH, implode("\n", $lines) . "\n") === false) {
            throw new RuntimeException('Failed to write README.md');
        }
    } catch (RuntimeException $x) {
        fwrite(STDERR, $x->getMessage() . "\n");

        return 1;
    }

    return 0;
}

exit(main());
