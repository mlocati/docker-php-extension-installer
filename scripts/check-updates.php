<?php

declare(strict_types=1);

/*
 * Check whether the external dependencies used by install-php-extensions have new versions, and whether they work.
 *
 * Commands:
 *
 *   detect --state-file=<path> --tests-file=<path>
 *     Detect the new versions of:
 *     - the PECL extensions (from the feed of the latest PECL releases)
 *     - the PECL extensions that we install as non-stable by default (beta, alpha, ...): is there a more stable version? (only notified)
 *     - the libraries and PHP extensions that we download manually (listed in data/dependencies.json)
 *     The tests to be performed are written to the tests file, one per line, in the format
 *     <comma-separated extensions> [<VARIABLE>=<value> ...]
 *     New versions that failed the tests are tested again until they pass.
 *     When the state file doesn't exist, the current versions of the PECL extensions are considered as already tested.
 *
 *   finalize --state-file=<path> --results-dir=<path> --distros=<comma-separated list>
 *     Read the test results (written by ci-test-extensions in <results-dir>/<distro>/<n>.txt, where <n> is the line number in the tests file, with a
 *     <results-dir>/<distro>/done file written when all the tests of the distro have been executed), update the state file,
 *     and build the message to be notified.
 *
 * Temporary problems (like websites that can't be reached) are reported as warnings.
 * Problems that require updating this script, data/dependencies.json or install-php-extensions are reported as errors:
 * they are notified, and finalize exits with a non-zero code.
 *
 * When running in GitHub Actions:
 * - detect sets the "has-tests" output ("yes" or "no")
 * - finalize sets the "message" output (the text to be notified, empty if there's nothing to be notified)
 */

set_error_handler(
    static function (int $errno, string $errstr, string $errfile = '', int $errline = 0): never {
        throw new RuntimeException("Error {$errno}: {$errstr}" . ($errfile === '' ? '' : " in {$errfile}" . ($errline === 0 ? '' : " at line {$errline}")));
    },
    -1,
);

require_once __DIR__ . '/dependencies.php';

const INSTALLER_PATH = __DIR__ . '/../install-php-extensions';

const SUPPORTED_EXTENSIONS_PATH = __DIR__ . '/../data/supported-extensions';

const PECL_FEED_URL = 'https://pecl.php.net/feeds/latest.rss';

const USER_AGENT = 'mlocati/docker-php-extension-installer update checker';

/**
 * The default maximum number of seconds for the connection and for the download.
 */
const DEFAULT_TIMEOUT = 30;

/**
 * The values of the latestVersion.source property of data/dependencies.json.
 */
enum LatestVersionSource: string
{
    /**
     * The highest stable version among the tags of a git repository.
     */
    case GitTags = 'gitTags';

    /**
     * The commit hash a git ref (branch) points to.
     */
    case GitRef = 'gitRef';

    /**
     * The highest version extracted from a web page with a regular expression:
     * the version is the "version" named group (or the first capturing group);
     * if the regular expression has a "suffix" named group, its value is appended to the version (<version>@<suffix>).
     */
    case WebPage = 'webPage';

    /**
     * The value returned by the static method of the CustomLatestVersion class whose name is the key of the item.
     */
    case Custom = 'custom';
}

/**
 * The stability levels of PECL releases, from the most stable to the least stable.
 */
enum PeclStability: string
{
    case Stable = 'stable';
    case Beta = 'beta';
    case Alpha = 'alpha';
    case Snapshot = 'snapshot';
    case Devel = 'devel';

    /**
     * Get the stability levels that are more stable than this one (the most stable first).
     *
     * @return PeclStability[]
     */
    public function getMoreStable(): array
    {
        $cases = self::cases();

        return array_slice($cases, 0, array_search($this, $cases, true));
    }
}

/**
 * A temporary problem (for example, a website that can't be reached).
 */
class ConnectionException extends RuntimeException
{
}

/**
 * A problem that requires updating this script, data/dependencies.json or install-php-extensions.
 */
class ActionRequiredException extends RuntimeException
{
}

/**
 * A version detected by the "detect" command.
 */
readonly class DetectedVersion
{
    /**
     * @param string                $id           the ID of the item in the state file
     * @param string                $version      the detected version
     * @param string                $description  the description to be notified
     * @param string[]              $extensions   the PHP extensions to be tested (empty array: the version won't be tested)
     * @param array<string, string> $env          the environment variables to be used when testing the extensions
     * @param bool                  $notifyAlways notify the version even if the tests pass
     */
    public function __construct(
        public string $id,
        public string $version,
        public string $description,
        public string $url = '',
        public array $extensions = [],
        public array $env = [],
        public bool $notifyAlways = false,
    ) {
    }
}

function isGitHubActions(): bool
{
    return getenv('GITHUB_ACTIONS') === 'true';
}

function logInfo(string $message): void
{
    fwrite(STDERR, $message . "\n");
}

function logWarning(string $message): void
{
    if (isGitHubActions()) {
        // See https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands#setting-a-warning-message
        fwrite(STDOUT, '::warning::' . strtr($message, ['%' => '%25', "\r" => '%0D', "\n" => '%0A']) . "\n");
    } else {
        fwrite(STDERR, "WARNING: {$message}\n");
    }
}

function logError(string $message): void
{
    if (isGitHubActions()) {
        // See https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-commands#setting-an-error-message
        fwrite(STDOUT, '::error::' . strtr($message, ['%' => '%25', "\r" => '%0D', "\n" => '%0A']) . "\n");
    } else {
        fwrite(STDERR, "ERROR: {$message}\n");
    }
}

/**
 * @param int $timeout the maximum number of seconds for the connection and for the download
 *
 * @throws ConnectionException
 *
 * @return null|string NULL if the resource does not exist (HTTP 404)
 */
function download(string $url, int $timeout = DEFAULT_TIMEOUT, bool $headersOnly = false): ?string
{
    static $curl = null;
    if ($curl === null) {
        $curl = curl_init();
    }
    curl_reset($curl);
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => !$headersOnly,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_NOBODY => $headersOnly,
        CURLOPT_HEADER => $headersOnly,
    ]);
    $response = curl_exec($curl);
    if ($response === false) {
        throw new ConnectionException("Failed to download {$url}: " . curl_error($curl));
    }
    $httpCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($httpCode === 404) {
        return null;
    }
    if ($httpCode < 200 || $httpCode >= ($headersOnly ? 400 : 300)) {
        throw new ConnectionException("Failed to download {$url}: HTTP status code {$httpCode}");
    }

    return $response;
}

/**
 * @throws ConnectionException
 * @throws ActionRequiredException if the resource does not exist (HTTP 404)
 */
function downloadExisting(string $url, int $timeout = DEFAULT_TIMEOUT, bool $headersOnly = false): string
{
    $response = download($url, $timeout, $headersOnly);
    if ($response === null) {
        throw new ActionRequiredException("Failed to download {$url}: not found");
    }

    return $response;
}

/**
 * Normalize a version, returning NULL if it's not a stable version.
 *
 * @example '1.2.3' => '1.2.3'
 * @example 'v1.2.3' => '1.2.3'
 * @example 'R2_5_9' => NULL
 * @example '1.2.3-rc1' => NULL
 */
function normalizeStableVersion(string $version): ?string
{
    return preg_match('/^v?(\d+(?:\.\d+)+)$/i', $version, $matches) ? $matches[1] : null;
}

/**
 * @param string[] $versions
 */
function getHighestVersion(array $versions): ?string
{
    $result = null;
    foreach ($versions as $version) {
        if ($result === null || version_compare($version, $result) > 0) {
            $result = $version;
        }
    }

    return $result;
}

/**
 * Retrieve the references of a remote git repository, using the "smart HTTP" protocol.
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 *
 * @return array<string, string> keys are the ref names, values are the commit hashes
 */
function getGitRefs(string $repositoryUrl, int $timeout = DEFAULT_TIMEOUT): array
{
    static $cache = [];
    if (isset($cache[$repositoryUrl])) {
        return $cache[$repositoryUrl];
    }
    $url = rtrim($repositoryUrl, '/');
    if (str_starts_with($url, 'https://github.com/') && !str_ends_with($url, '.git')) {
        $url .= '.git';
    }
    $url .= '/info/refs?service=git-upload-pack';
    $response = downloadExisting($url, $timeout);
    if (!preg_match_all('/([0-9a-f]{40}) ([^\s\x00]+)/', $response, $matches, PREG_SET_ORDER)) {
        throw new ActionRequiredException("Failed to parse the git refs of {$repositoryUrl}");
    }
    $refs = [];
    foreach ($matches as $match) {
        [, $hash, $ref] = $match;
        if (str_ends_with($ref, '^{}')) {
            // Peeled tags: use the hash of the commit instead of the hash of the annotated tag
            $refs[substr($ref, 0, -3)] = $hash;
        } elseif (!isset($refs[$ref])) {
            $refs[$ref] = $hash;
        }
    }

    return $cache[$repositoryUrl] = $refs;
}

/**
 * @param string $ignoreTags regular expression of the tags to be ignored (empty string: none)
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function getLatestGitTag(string $repositoryUrl, string $ignoreTags = '', int $timeout = DEFAULT_TIMEOUT): ?string
{
    $versions = [];
    foreach (array_keys(getGitRefs($repositoryUrl, $timeout)) as $ref) {
        if (!str_starts_with($ref, 'refs/tags/')) {
            continue;
        }
        $tag = substr($ref, strlen('refs/tags/'));
        if ($ignoreTags !== '' && preg_match($ignoreTags, $tag)) {
            continue;
        }
        $version = normalizeStableVersion($tag);
        if ($version !== null) {
            $versions[$version] = $tag;
        }
    }
    $highest = getHighestVersion(array_map('strval', array_keys($versions)));

    return $highest === null ? null : $versions[$highest];
}

/**
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function getGitRefHash(string $repositoryUrl, string $ref, int $timeout = DEFAULT_TIMEOUT): string
{
    $refs = getGitRefs($repositoryUrl, $timeout);
    if (!isset($refs[$ref])) {
        throw new ActionRequiredException("Unable to find the ref {$ref} in {$repositoryUrl}");
    }

    return $refs[$ref];
}

/**
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function getLatestVersionFromWebPage(string $url, string $regex, int $timeout = DEFAULT_TIMEOUT): ?string
{
    $html = downloadExisting($url, $timeout);
    if (!preg_match_all($regex, $html, $matches, PREG_SET_ORDER)) {
        return null;
    }
    $result = null;
    foreach ($matches as $match) {
        $version = $match['version'] ?? $match[1];
        if ($result === null || version_compare($version, getVersionWithoutSuffix($result)) > 0) {
            $result = ($match['suffix'] ?? '') === '' ? $version : "{$version}@{$match['suffix']}";
        }
    }

    return $result;
}

/**
 * Get the tag of the latest release of a GitHub repository.
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function getLatestGitHubRelease(string $repository, int $timeout = DEFAULT_TIMEOUT): ?string
{
    $headers = downloadExisting("https://github.com/{$repository}/releases/latest", $timeout, true);

    return preg_match('/^location:.*\/releases\/tag\/(\S+)/im', $headers, $matches) ? $matches[1] : null;
}

/**
 * The latest versions of the items of data/dependencies.json whose source is "custom": the method names are the keys of the items.
 */
final class CustomLatestVersion
{
    /**
     * @throws ConnectionException
     * @throws ActionRequiredException
     */
    public static function blackfire(int $timeout): ?string
    {
        $headers = downloadExisting('https://blackfire.io/api/v1/releases/probe/php/linux/amd64/84', $timeout, true);

        return preg_match('/^location:.*\/blackfire-php\/([^\/\s]+)\//im', $headers, $matches) ? $matches[1] : null;
    }

    /**
     * @throws ConnectionException
     * @throws ActionRequiredException
     */
    public static function ddtrace(int $timeout): ?string
    {
        return getLatestGitHubRelease('DataDog/dd-trace-php', $timeout);
    }

    /**
     * ionCube doesn't publish the version of the loaders: let's use the date of the archive.
     *
     * @throws ConnectionException
     * @throws ActionRequiredException
     */
    public static function ioncube_loader(int $timeout): ?string
    {
        $headers = downloadExisting('https://downloads.ioncube.com/loader_downloads/ioncube_loaders_lin_x86-64.tar.gz', $timeout, true);
        if (!preg_match('/^last-modified:\s*(.+?)\s*$/im', $headers, $matches)) {
            return null;
        }
        $timestamp = strtotime($matches[1]);

        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @throws ConnectionException
     * @throws ActionRequiredException
     */
    public static function pdo_snowflake(int $timeout): ?string
    {
        return getLatestGitHubRelease('snowflakedb/pdo_snowflake', $timeout);
    }

    /**
     * @throws ConnectionException
     * @throws ActionRequiredException
     */
    public static function tideways(int $timeout): ?string
    {
        $data = json_decode(downloadExisting('https://app.tideways.io/api/current-versions', $timeout), true);
        $version = is_array($data) ? ($data['php']['version'] ?? null) : null;

        return is_string($version) && $version !== '' ? $version : null;
    }
}

/**
 * Remove the suffix (if any) from a version.
 *
 * @example '1.2.3' => '1.2.3'
 * @example '1.2.3@abc/def' => '1.2.3'
 */
function getVersionWithoutSuffix(string $version): string
{
    return explode('@', $version, 2)[0];
}

/**
 * Get the latest version of an item of data/dependencies.json.
 *
 * @param string               $key           the key of the item
 * @param array<string, mixed> $latestVersion the latestVersion property of the item
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function getLatestDependencyVersion(string $key, string $name, array $latestVersion): string
{
    $source = LatestVersionSource::from($latestVersion['source']);
    if ($source === LatestVersionSource::Custom && !method_exists(CustomLatestVersion::class, $key)) {
        throw new ActionRequiredException("The CustomLatestVersion::{$key}() method required by {$name} doesn't exist");
    }
    $timeout = $latestVersion['timeout'] ?? DEFAULT_TIMEOUT;
    $latest = match ($source) {
        LatestVersionSource::GitTags => getLatestGitTag($latestVersion['repository'], $latestVersion['ignoreTags'] ?? '', $timeout),
        LatestVersionSource::GitRef => getGitRefHash($latestVersion['repository'], $latestVersion['ref'], $timeout),
        LatestVersionSource::WebPage => getLatestVersionFromWebPage($latestVersion['url'], $latestVersion['regex'], $timeout),
        LatestVersionSource::Custom => CustomLatestVersion::$key($timeout),
    };
    if ($latest === null) {
        throw new ActionRequiredException("Unable to detect the latest version of {$name}");
    }

    return $latest;
}

/**
 * @throws ActionRequiredException
 *
 * @return PeclStability[] keys are the PHP module names, values are the stability flags we use by default
 */
function getUnstablePeclModules(string $installer): array
{
    $installRemoteModuleStart = strpos($installer, "\ninstallRemoteModule() {\n");
    if ($installRemoteModuleStart === false) {
        throw new ActionRequiredException('Unable to find the installRemoteModule function in install-php-extensions');
    }
    $installRemoteModuleEnd = strpos($installer, "\n}\n", $installRemoteModuleStart);
    $code = substr($installer, $installRemoteModuleStart, $installRemoteModuleEnd - $installRemoteModuleStart);
    $unstableFlags = array_map(
        static fn (PeclStability $stability): string => $stability->value,
        array_filter(PeclStability::cases(), static fn (PeclStability $stability): bool => $stability !== PeclStability::Stable),
    );
    $versionRegex = '/\binstallRemoteModule_version=(' . implode('|', $unstableFlags) . ')\b/';
    // Split the code by the case labels of the "case $installRemoteModule_module in" block
    $chunks = preg_split('/^\t\t([a-z0-9_]+(?: \| [a-z0-9_]+)*)\)$/m', $code, -1, PREG_SPLIT_DELIM_CAPTURE);
    $result = [];
    for ($index = 1, $count = count($chunks); $index < $count; $index += 2) {
        if (preg_match($versionRegex, $chunks[$index + 1], $matches)) {
            foreach (explode(' | ', $chunks[$index]) as $module) {
                $result[$module] = PeclStability::from($matches[1]);
            }
        }
    }
    ksort($result);

    return $result;
}

function getPeclPackageName(string $module): string
{
    return match ($module) {
        'ddtrace' => 'datadog_trace',
        'http' => 'pecl_http',
        'sodium' => 'libsodium',
        default => $module,
    };
}

function getModuleFromPeclPackageName(string $package): string
{
    return match ($package) {
        'datadog_trace' => 'ddtrace',
        'pecl_http' => 'http',
        'libsodium' => 'sodium',
        default => $package,
    };
}

/**
 * Get the latest version of a PECL package having exactly the specified stability.
 *
 * @throws ConnectionException
 */
function getLatestPeclVersion(string $package, PeclStability $stability): ?string
{
    $version = download("https://pecl.php.net/rest/r/{$package}/{$stability->value}.txt");
    $version = $version === null ? '' : trim($version);

    return $version === '' ? null : $version;
}

/**
 * Check if there's a version more stable than the one we use by default (and not older than it).
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function checkPeclModuleStability(string $module, PeclStability $stability): ?DetectedVersion
{
    $package = getPeclPackageName($module);
    $currentVersion = getLatestPeclVersion($package, $stability);
    if ($currentVersion === null) {
        throw new ActionRequiredException("PECL extension {$module}: unable to find its latest {$stability->value} version");
    }
    foreach ($stability->getMoreStable() as $betterStability) {
        $betterVersion = getLatestPeclVersion($package, $betterStability);
        if ($betterVersion === null) {
            continue;
        }
        if (version_compare($betterVersion, $currentVersion) < 0) {
            logInfo("- {$module}: latest {$betterStability->value} version is {$betterVersion}, but we use {$stability->value} ({$currentVersion})");

            continue;
        }
        logInfo("- {$module}: {$betterStability->value} version {$betterVersion} is available (we use {$stability->value} {$currentVersion})!");

        return new DetectedVersion(
            "stability:{$module}",
            "{$betterStability->value} {$betterVersion}",
            "PECL extension {$module}: {$betterStability->value} version {$betterVersion} is available (we install the {$stability->value} version by default, currently {$currentVersion})",
            "https://pecl.php.net/package/{$package}",
        );
    }
    logInfo("- {$module}: nothing more stable than {$stability->value} ({$currentVersion})");

    return null;
}

/**
 * Get the PHP extensions supported by install-php-extensions.
 *
 * @return string[]
 */
function getSupportedExtensions(): array
{
    $result = [];
    foreach (preg_split('/\R/', (string) file_get_contents(SUPPORTED_EXTENSIONS_PATH), -1, PREG_SPLIT_NO_EMPTY) as $line) {
        $words = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($words !== []) {
            $result[] = $words[0];
        }
    }

    return $result;
}

/**
 * Get the latest versions of the supported PECL extensions listed in the feed of the latest PECL releases.
 *
 * @param string[] $supportedExtensions
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 *
 * @return array<string, string> keys are the PHP extension names, values are the versions
 */
function getRecentPeclReleases(array $supportedExtensions): array
{
    $xml = downloadExisting(PECL_FEED_URL);
    if (!preg_match_all('/<item\b.*?<title>\s*(\S+)\s+(\S+)\s*<\/title>/s', $xml, $matches, PREG_SET_ORDER)) {
        throw new ActionRequiredException('Failed to parse the feed of the latest PECL releases');
    }
    $result = [];
    foreach ($matches as [, $package, $version]) {
        $module = getModuleFromPeclPackageName(strtolower(html_entity_decode($package)));
        // The feed lists the most recent releases first
        if (!isset($result[$module]) && in_array($module, $supportedExtensions, true)) {
            $result[$module] = html_entity_decode($version);
        }
    }
    ksort($result);

    return $result;
}

/**
 * Check that data/dependencies.json is consistent with the IPE_LIBVERSION_... and IPE_EXTLATESTVERSION_... variables defined in install-php-extensions.
 *
 * @param array{libraries: array<string, array<string, mixed>>, extensions: array<string, array<string, mixed>>} $dependencies
 *
 * @return string[] the problems found
 */
function checkDependenciesConsistency(string $installer, array $dependencies): array
{
    $errors = [];
    $definedVariables = getInstallerDependencyVariables($installer);
    $handledVariables = [];
    foreach ($dependencies as $section => $items) {
        foreach ($items as $key => $item) {
            if ($item['pinnedVersion']) {
                $handledVariables[] = getDependencyVariable($section, (string) $key);
            }
        }
    }
    foreach (array_diff($definedVariables, $handledVariables) as $variable) {
        $errors[] = "The {$variable} variable is defined in install-php-extensions, but there's no corresponding item in data/dependencies.json";
    }
    foreach (array_diff($handledVariables, $definedVariables) as $variable) {
        $errors[] = "data/dependencies.json refers to the {$variable} variable, but it's not defined in install-php-extensions";
    }

    return $errors;
}

/**
 * Check if there's a newer version of a library or PHP extension whose version is defined in install-php-extensions.
 *
 * @param string               $key        the key of the item in data/dependencies.json
 * @param array<string, mixed> $dependency an item of data/dependencies.json
 * @param string[]             $usedBy
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function checkPinnedDependency(string $key, array $dependency, string $variable, array $usedBy, string $installer): ?DetectedVersion
{
    $name = $dependency['name'];
    $current = getInstallerVariableDefault($installer, $variable);
    if ($current === null) {
        throw new ActionRequiredException("Unable to find the {$variable} variable in install-php-extensions (it should contain the current version of {$name})");
    }
    $latest = getLatestDependencyVersion($key, $name, $dependency['latestVersion']);
    $source = LatestVersionSource::from($dependency['latestVersion']['source']);
    $isNewer = match ($source) {
        LatestVersionSource::GitTags => version_compare(normalizeStableVersion($latest) ?? '0', normalizeStableVersion($current) ?? $current) > 0,
        LatestVersionSource::GitRef, LatestVersionSource::Custom => $latest !== $current,
        LatestVersionSource::WebPage => version_compare(getVersionWithoutSuffix($latest), getVersionWithoutSuffix($current)) > 0,
    };
    if (!$isNewer) {
        logInfo("- {$name}: {$current} is up to date");

        return null;
    }
    logInfo("- {$name}: {$current} => {$latest}");
    $description = "{$name} (used by " . implode(', ', $usedBy) . "): we use {$current}, {$latest} is available";
    if (isset($dependency['skipTest'])) {
        return new DetectedVersion("lib:{$name}", $latest, "{$description} (not tested: {$dependency['skipTest']})", $dependency['url'] ?? '');
    }
    $value = $source === LatestVersionSource::GitTags ? (normalizeStableVersion($latest) ?? $latest) : $latest;

    return new DetectedVersion("lib:{$name}", $latest, $description, $dependency['url'] ?? '', $usedBy, [$variable => $value], true);
}

/**
 * @throws RuntimeException
 *
 * @return array{items: array<string, array<string, mixed>>, tests: array<int, string>, errors: string[]}
 */
function readState(string $stateFile): array
{
    $state = json_decode((string) file_get_contents($stateFile), true, 512, JSON_THROW_ON_ERROR);

    return [
        'items' => is_array($state['items'] ?? null) ? $state['items'] : [],
        'tests' => is_array($state['tests'] ?? null) ? $state['tests'] : [],
        'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
    ];
}

/**
 * @param array{items: array<string, array<string, mixed>>, tests: array<int, string>, errors: string[]} $state
 */
function writeState(string $stateFile, array $state): void
{
    ksort($state['items']);
    $dir = dirname($stateFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function setGitHubOutput(string $name, string $value): void
{
    $outputFile = (string) getenv('GITHUB_OUTPUT');
    if ($outputFile === '') {
        return;
    }
    $delimiter = 'EOF_' . bin2hex(random_bytes(8));
    file_put_contents($outputFile, "{$name}<<{$delimiter}\n{$value}\n{$delimiter}\n", FILE_APPEND);
}

/**
 * @param array<string, string> $options
 *
 * @return int the exit code
 */
function detect(array $options): int
{
    $stateFile = $options['state-file'] ?? '';
    $testsFile = $options['tests-file'] ?? '';
    if ($stateFile === '' || $testsFile === '') {
        fwrite(STDERR, "Missing --state-file or --tests-file\n");

        return 1;
    }
    $firstRun = !is_file($stateFile);
    $state = $firstRun ? ['items' => [], 'tests' => [], 'errors' => []] : readState($stateFile);
    $installer = (string) file_get_contents(INSTALLER_PATH);
    /** @var DetectedVersion[] $detected */
    $detected = [];
    // IDs of the items that we could not check (so they must be kept in the state)
    $failedIDs = [];
    $errors = [];
    $handleException = static function (Throwable $x, string $id) use (&$failedIDs, &$errors): void {
        $failedIDs[] = $id;
        if ($x instanceof ConnectionException) {
            logWarning($x->getMessage());
        } else {
            $errors[] = $x->getMessage();
            logError($x->getMessage());
        }
    };

    logInfo('Checking the recent PECL releases');

    try {
        foreach (getRecentPeclReleases(getSupportedExtensions()) as $module => $version) {
            logInfo("- {$module}: {$version}");
            $detected[] = new DetectedVersion("pecl:{$module}", $version, "PECL extension {$module} {$version}", "https://pecl.php.net/package/{$module}", [$module]);
        }
    } catch (RuntimeException $x) {
        $handleException($x, 'pecl-feed');
    }

    logInfo('Checking PECL extensions installed as non-stable by default');

    try {
        $unstablePeclModules = getUnstablePeclModules($installer);
    } catch (RuntimeException $x) {
        $handleException($x, 'pecl-unstable');
        $unstablePeclModules = [];
    }
    foreach ($unstablePeclModules as $module => $stability) {
        try {
            $item = checkPeclModuleStability($module, $stability);
            if ($item !== null) {
                $detected[] = $item;
            }
        } catch (RuntimeException $x) {
            $handleException($x, "stability:{$module}");
        }
    }

    logInfo('Checking manually installed libraries and PHP extensions');

    try {
        $dependencies = readDependencies();
        foreach (checkDependenciesConsistency($installer, $dependencies) as $error) {
            $errors[] = $error;
            logError($error);
        }
    } catch (RuntimeException $x) {
        $handleException($x, 'dependencies');
        $dependencies = ['libraries' => [], 'extensions' => []];
    }
    foreach ($dependencies as $section => $items) {
        foreach ($items as $key => $dependency) {
            $key = (string) $key;
            $name = $dependency['name'];
            if (isset($dependency['skipCheck'])) {
                logInfo("- {$name}: skipped");

                continue;
            }
            $usedBy = $section === 'libraries' ? $dependency['usedBy'] : [$key];
            $id = ($dependency['pinnedVersion'] ? 'lib:' : 'unpinned:') . $name;

            try {
                if ($dependency['pinnedVersion']) {
                    $item = checkPinnedDependency($key, $dependency, getDependencyVariable($section, $key), $usedBy, $installer);
                } else {
                    $version = getLatestDependencyVersion($key, $name, $dependency['latestVersion']);
                    logInfo("- {$name}: {$version}");
                    $item = new DetectedVersion($id, $version, "{$name} {$version} (used by " . implode(', ', $usedBy) . ')', $dependency['url'] ?? '', $usedBy);
                }
                if ($item !== null) {
                    $detected[] = $item;
                }
            } catch (RuntimeException $x) {
                $handleException($x, $id);
            }
        }
    }

    $items = [];
    foreach ($detected as $item) {
        $old = $state['items'][$item->id] ?? null;
        $verified = $old['verified'] ?? null;
        if ($firstRun && str_starts_with($item->id, 'pecl:')) {
            // Let's not test all the PECL extensions listed in the feed
            $verified = $item->version;
        }
        $items[$item->id] = [
            'seen' => $item->version,
            'verified' => $verified,
            'notified' => $old['notified'] ?? null,
            'description' => $item->description,
            'url' => $item->url,
            'extensions' => $item->extensions,
            'env' => $item->env,
            'notifyAlways' => $item->notifyAlways,
        ];
    }
    foreach ($state['items'] as $id => $old) {
        // Keep the PECL releases no longer listed in the feed, and the items that we could not check
        if (!isset($items[$id]) && (str_starts_with($id, 'pecl:') || in_array($id, $failedIDs, true))) {
            $items[$id] = $old;
        }
    }

    $tests = [];
    $lines = [];
    foreach ($items as $id => $item) {
        if ($item['extensions'] === [] || $item['seen'] === $item['verified']) {
            continue;
        }
        $tests[] = $id;
        $line = implode(',', $item['extensions']);
        foreach ($item['env'] as $variable => $value) {
            $line .= " {$variable}={$value}";
        }
        $lines[] = $line;
    }
    writeState($stateFile, ['items' => $items, 'tests' => $tests, 'errors' => $errors]);
    file_put_contents($testsFile, $lines === [] ? '' : implode("\n", $lines) . "\n");
    logInfo('');
    logInfo(sprintf('Tests to be performed: %d', count($tests)));
    foreach ($lines as $line) {
        logInfo("- {$line}");
    }
    setGitHubOutput('has-tests', $tests === [] ? 'no' : 'yes');

    return 0;
}

/**
 * Read the results of the tests.
 *
 * @param string[] $distros
 *
 * @return array{failures: array<int, string[]>, incomplete: string[]} failures: keys are the test indexes, values are the failed distro/PHP combinations; incomplete: the distros whose tests didn't complete
 */
function readTestResults(string $resultsDir, array $distros): array
{
    $failures = [];
    $incomplete = [];
    foreach ($distros as $distro) {
        $dir = "{$resultsDir}/{$distro}";
        if (!is_file("{$dir}/done")) {
            $incomplete[] = $distro;

            continue;
        }
        foreach (glob("{$dir}/*.txt") ?: [] as $file) {
            // The files are named after the line number in the tests file (starting from 1)
            $index = (int) basename($file, '.txt') - 1;
            foreach (preg_split('/\R/', (string) file_get_contents($file), -1, PREG_SPLIT_NO_EMPTY) as $line) {
                [$result, $phpVersion] = explode(' ', $line, 3) + ['', ''];
                if ($result !== 'ok') {
                    $failures[$index][] = "{$distro} PHP {$phpVersion}";
                }
            }
        }
    }

    return ['failures' => $failures, 'incomplete' => $incomplete];
}

/**
 * @param array<string, string> $options
 *
 * @return int the exit code
 */
function finalize(array $options): int
{
    $stateFile = $options['state-file'] ?? '';
    $resultsDir = $options['results-dir'] ?? '';
    $distros = array_values(array_filter(explode(',', $options['distros'] ?? '')));
    if ($stateFile === '' || $resultsDir === '' || $distros === []) {
        fwrite(STDERR, "Missing --state-file, --results-dir or --distros\n");

        return 1;
    }
    $state = readState($stateFile);
    $errors = $state['errors'];
    $results = $state['tests'] === [] ? ['failures' => [], 'incomplete' => []] : readTestResults($resultsDir, $distros);
    $available = [];
    $failed = [];
    foreach ($state['items'] as $id => &$item) {
        $index = array_search($id, $state['tests'], true);
        if ($index !== false) {
            if (isset($results['failures'][$index])) {
                $failed[] = "- {$item['description']}: failed on " . implode(', ', $results['failures'][$index]) . ($item['url'] === '' ? '' : "\n  {$item['url']}");

                continue;
            }
            if ($results['incomplete'] !== []) {
                continue;
            }
            $item['verified'] = $item['seen'];
            if (!$item['notifyAlways']) {
                continue;
            }
        } elseif ($item['extensions'] !== []) {
            continue;
        }
        if ($item['notified'] !== $item['seen']) {
            $item['notified'] = $item['seen'];
            $available[] = "- {$item['description']}" . ($index === false ? '' : ' (tests passed)') . ($item['url'] === '' ? '' : "\n  {$item['url']}");
        }
    }
    unset($item);
    writeState($stateFile, ['items' => $state['items'], 'tests' => [], 'errors' => []]);

    $sections = [];
    if ($failed !== []) {
        $sections[] = "Some new versions of the dependencies of docker-php-extension-installer don't work:\n" . implode("\n", $failed);
    }
    if ($results['incomplete'] !== []) {
        $sections[] = 'The tests did not complete on ' . implode(', ', $results['incomplete']) . ': they will be performed again.';
    }
    if ($available !== []) {
        $sections[] = "New versions of dependencies used by docker-php-extension-installer are available:\n" . implode("\n", $available);
    }
    if ($errors !== []) {
        $sections[] = "The update checker or install-php-extensions need to be updated:\n" . implode("\n", array_map(static fn (string $error): string => "- {$error}", $errors));
    }
    $message = implode("\n\n", $sections);
    if ($message !== '') {
        echo $message, "\n";
    }
    setGitHubOutput('message', $message);

    return $errors === [] && $failed === [] && $results['incomplete'] === [] ? 0 : 1;
}

/**
 * @param string[] $argv
 */
function main(array $argv): int
{
    $command = $argv[1] ?? '';
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (!preg_match('/^--([a-z-]+)=(.*)$/', $arg, $matches)) {
            fwrite(STDERR, "Unrecognized argument: {$arg}\n");

            return 1;
        }
        $options[$matches[1]] = $matches[2];
    }

    return match ($command) {
        'detect' => detect($options),
        'finalize' => finalize($options),
        default => (static function () use ($command): int {
            fwrite(STDERR, $command === '' ? "Missing command (detect or finalize)\n" : "Unrecognized command: {$command}\n");

            return 1;
        })(),
    };
}

exit(main($argv));
