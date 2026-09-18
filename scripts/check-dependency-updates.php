<?php

/*
 * Check whether the external dependencies used by install-php-extensions have newer versions:
 * - PECL extensions that we install as non-stable by default (beta, alpha, ...): is there a more stable version?
 * - libraries that we download manually: is there a newer version?
 *
 * The versions currently in use are extracted from the install-php-extensions script,
 * so that we don't need to update this script when we upgrade a dependency.
 *
 * Options:
 *   --state-file=<path> JSON file containing the updates already notified (updates listed there won't be reported again)
 *
 * Temporary problems (like websites that can't be reached) are reported as warnings.
 * Problems that require updating this script or install-php-extensions (like versions that can't be detected)
 * are reported as errors: the script checks all the dependencies anyway, and at the end it exits with a non-zero code.
 *
 * When running in GitHub Actions:
 * - the "message" output contains the text to be notified (empty if there's nothing new)
 * - the "errors" output contains the problems that require updating this script or install-php-extensions (empty if none)
 */

const INSTALLER_PATH = __DIR__ . '/../install-php-extensions';

const USER_AGENT = 'mlocati/docker-php-extension-installer dependency checker';

/**
 * The default maximum number of seconds for the connection and for the download.
 */
const DEFAULT_TIMEOUT = 30;

enum LatestVersionSource
{
    /**
     * The highest stable version among the tags of a git repository.
     */
    case GitTags;

    /**
     * The commit hash a git ref (branch) points to.
     */
    case GitRef;

    /**
     * The highest version extracted from a web page (first capturing group of a regular expression).
     */
    case WebPage;
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
 * The libraries/sources that we download manually.
 *
 * Array keys are the names of the libraries/sources; array values have these keys:
 * - usedBy: the PHP extensions that use the library/source
 * - variable: the name of the variable that contains the currently used version in install-php-extensions (without the IPE_LATESTVERSION_ prefix)
 * - latest: how to retrieve the latest version:
 *   - [LatestVersionSource::GitTags, <repository URL>]
 *   - [LatestVersionSource::GitRef, <repository URL>, <ref>]
 *   - [LatestVersionSource::WebPage, <URL>, <regular expression>]
 * - ignoreTags: regular expression of the git tags to be ignored (optional, only for LatestVersionSource::GitTags)
 * - url: the URL where maintainers can check the dependency (optional)
 * - timeout: the maximum number of seconds for the connection and for the download (optional, default: DEFAULT_TIMEOUT)
 */
const LIBRARIES = [
    'Firebird' => [
        'usedBy' => ['interbase', 'pdo_firebird', 'swoole'],
        'variable' => 'FIREBIRD',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/FirebirdSQL/firebird'],
        'url' => 'https://github.com/FirebirdSQL/firebird/releases',
    ],
    'HAT-trie' => [
        'usedBy' => ['php_trie'],
        'variable' => 'HATTRIE',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/Tessil/hat-trie'],
        'url' => 'https://github.com/Tessil/hat-trie/releases',
    ],
    'ion-c' => [
        'usedBy' => ['ion'],
        'variable' => 'IONC',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/amzn/ion-c'],
        // v1.4.0 is an old tag (February 2021) with a wrong version number
        'ignoreTags' => '/^v1\.4\.0$/',
        'url' => 'https://github.com/amzn/ion-c/releases',
    ],
    'IP2Location C library' => [
        'usedBy' => ['ip2location'],
        'variable' => 'LIBIP2LOCATION',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/chrislim2888/IP2Location-C-Library'],
        'url' => 'https://github.com/chrislim2888/IP2Location-C-Library/tags',
    ],
    'libaom' => [
        'usedBy' => ['gd'],
        'variable' => 'LIBAOM',
        'latest' => [LatestVersionSource::GitTags, 'https://aomedia.googlesource.com/aom'],
        'url' => 'https://aomedia.googlesource.com/aom/+refs',
    ],
    'libavif' => [
        'usedBy' => ['gd'],
        'variable' => 'LIBAVIF',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/AOMediaCodec/libavif'],
        'url' => 'https://github.com/AOMediaCodec/libavif/releases',
    ],
    'libcmark' => [
        'usedBy' => ['cmark'],
        'variable' => 'LIBCMARK',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/commonmark/cmark'],
        'url' => 'https://github.com/commonmark/cmark/releases',
    ],
    'libdav1d' => [
        'usedBy' => ['gd'],
        'variable' => 'LIBDAV1D',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/videolan/dav1d'],
        'url' => 'https://code.videolan.org/videolan/dav1d/-/tags',
    ],
    'libgearman' => [
        'usedBy' => ['gearman'],
        'variable' => 'LIBGEARMAN',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/gearman/gearmand'],
        'url' => 'https://github.com/gearman/gearmand/releases',
    ],
    'libidnkit' => [
        'usedBy' => ['http'],
        'variable' => 'LIBIDNKIT',
        'latest' => [LatestVersionSource::WebPage, 'https://jprs.co.jp/idn/', '/\bidnkit-(\d+(?:\.\d+)+)/'],
        'url' => 'https://jprs.co.jp/idn/',
    ],
    'libmpdec' => [
        'usedBy' => ['decimal'],
        'variable' => 'LIBMPDEC',
        'latest' => [LatestVersionSource::WebPage, 'https://www.bytereef.org/mpdecimal/download.html', '/\bmpdecimal-(\d+(?:\.\d+)+)\.tar\.gz\b/'],
        'url' => 'https://www.bytereef.org/mpdecimal/changelog.html',
    ],
    'libtomcrypt' => [
        'usedBy' => ['pdo_firebird', 'swoole'],
        'variable' => 'LIBTOMCRYPT',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/libtom/libtomcrypt'],
        'url' => 'https://github.com/libtom/libtomcrypt/releases',
    ],
    'libtommath' => [
        'usedBy' => ['pdo_firebird', 'swoole'],
        'variable' => 'LIBTOMMATH',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/libtom/libtommath'],
        'url' => 'https://github.com/libtom/libtommath/releases',
    ],
    'libxcrypt' => [
        'usedBy' => ['xpass'],
        'variable' => 'LIBXCRYPT',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/besser82/libxcrypt'],
        'url' => 'https://github.com/besser82/libxcrypt/releases',
    ],
    'LibXDiff' => [
        'usedBy' => ['xdiff'],
        'variable' => 'LIBXDIFF',
        'latest' => [LatestVersionSource::WebPage, 'http://www.xmailserver.org/xdiff-lib.html', '/\blibxdiff-(\d+(?:\.\d+)+)\.tar\.gz\b/'],
        'url' => 'http://www.xmailserver.org/xdiff-lib.html',
        // This website can be quite slow
        'timeout' => 60,
    ],
    'Microsoft ODBC Driver 18 for SQL Server (Alpine)' => [
        'usedBy' => ['pdo_sqlsrv', 'sqlsrv'],
        'variable' => 'MSODBC18',
        'latest' => [LatestVersionSource::WebPage, 'https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server', '/\bmsodbcsql18_(\d+(?:\.\d+)+-\d+)_/'],
        'url' => 'https://learn.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server#alpine18',
    ],
    'PHP-CPP' => [
        'usedBy' => ['tdlib'],
        'variable' => 'PHPCPP',
        'latest' => [LatestVersionSource::GitRef, 'https://github.com/CopernicaMarketingSoftware/PHP-CPP', 'HEAD'],
        'url' => 'https://github.com/CopernicaMarketingSoftware/PHP-CPP/commits',
    ],
    'php-ext-lz4' => [
        'usedBy' => ['lz4'],
        'variable' => 'LZ4',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/kjdev/php-ext-lz4'],
        'url' => 'https://github.com/kjdev/php-ext-lz4/tags',
    ],
    'php-ext-snappy' => [
        'usedBy' => ['snappy'],
        'variable' => 'SNAPPY',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/kjdev/php-ext-snappy'],
        'url' => 'https://github.com/kjdev/php-ext-snappy/tags',
    ],
    'php-geos' => [
        'usedBy' => ['geos'],
        'variable' => 'GEOS',
        'latest' => [LatestVersionSource::GitRef, 'https://github.com/libgeos/php-geos', 'HEAD'],
        'url' => 'https://github.com/libgeos/php-geos/commits',
    ],
    'php-spx' => [
        'usedBy' => ['spx'],
        'variable' => 'SPX',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/NoiseByNorthwest/php-spx'],
        'url' => 'https://github.com/NoiseByNorthwest/php-spx/tags',
    ],
    'snuffleupagus' => [
        'usedBy' => ['snuffleupagus'],
        'variable' => 'SNUFFLEUPAGUS',
        'latest' => [LatestVersionSource::GitTags, 'https://github.com/jvoisin/snuffleupagus'],
        'url' => 'https://github.com/jvoisin/snuffleupagus/releases',
    ],
    'v8js (php8 branch)' => [
        'usedBy' => ['v8js'],
        'variable' => 'V8JS',
        'latest' => [LatestVersionSource::GitRef, 'https://github.com/phpv8/v8js', 'refs/heads/php8'],
        'url' => 'https://github.com/phpv8/v8js/commits/php8',
    ],
];

/**
 * The names of the libraries/sources listed in LIBRARIES that we don't check for updates.
 */
const SKIPPED_LIBRARIES = [
    // We compile libaom only on old distros (Alpine < 3.15 and Debian < 12) whose build tools won't ever be upgraded,
    // so we use the latest version compatible with them: libaom 3.12.1+ requires cmake 3.16 (Debian Buster has cmake 3.13)
    'libaom',
    // We compile libavif only on old distros (Alpine < 3.15 and Debian < 12) whose build tools won't ever be upgraded,
    // so we use the latest version compatible with them: libavif 1.4.0+ requires cmake 3.22 (Debian Buster has cmake 3.13)
    'libavif',
    // We compile libdav1d only on old distros (Alpine < 3.15 and Debian < 12) whose build tools won't ever be upgraded,
    // so we use the latest version compatible with them: libdav1d 1.5.4+ requires meson 0.54 (Debian Buster has meson 0.49)
    'libdav1d',
    // The installation of tdlib is no longer supported (even if its code is still in install-php-extensions).
    // If it gets enabled again, the IPE_LATESTVERSION_PHPCPP variable must be defined in install-php-extensions.
    'PHP-CPP',
];

/**
 * A temporary problem (for example, a website that can't be reached).
 */
class ConnectionException extends RuntimeException
{
}

/**
 * A problem that requires updating this script or install-php-extensions.
 */
class ActionRequiredException extends RuntimeException
{
}

class Update
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $newVersion,
        public readonly string $url,
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
function download(string $url, int $timeout = DEFAULT_TIMEOUT): ?string
{
    static $curl = null;
    if ($curl === null) {
        $curl = curl_init();
    }
    curl_reset($curl);
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => USER_AGENT,
    ]);
    $response = curl_exec($curl);
    if ($response === false) {
        throw new ConnectionException("Failed to download {$url}: " . curl_error($curl));
    }
    $httpCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($httpCode === 404) {
        return null;
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        throw new ConnectionException("Failed to download {$url}: HTTP status code {$httpCode}");
    }

    return $response;
}

/**
 * @throws ConnectionException
 * @throws ActionRequiredException if the resource does not exist (HTTP 404)
 */
function downloadExisting(string $url, int $timeout = DEFAULT_TIMEOUT): string
{
    $response = download($url, $timeout);
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
    $highest = getHighestVersion(array_keys($versions));

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
    if (!preg_match_all($regex, $html, $matches)) {
        return null;
    }

    return getHighestVersion(array_unique($matches[1]));
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
function checkPeclModule(string $module, PeclStability $stability): ?Update
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

        return new Update(
            "pecl:{$module}",
            $module,
            "PECL extension {$module}: {$betterStability->value} version {$betterVersion} is available (we install the {$stability->value} version by default, currently {$currentVersion})",
            "{$betterStability->value} {$betterVersion}",
            "https://pecl.php.net/package/{$package}",
        );
    }
    logInfo("- {$module}: nothing more stable than {$stability->value} ({$currentVersion})");

    return null;
}

/**
 * Check that LIBRARIES and SKIPPED_LIBRARIES are consistent with the IPE_LATESTVERSION_... variables defined in install-php-extensions.
 *
 * @return string[] the problems found
 */
function checkLibrariesConsistency(string $installer): array
{
    $errors = [];
    preg_match_all('/^IPE_LATESTVERSION_(\w+)=/m', $installer, $matches);
    $definedVariables = $matches[1];
    $handledVariables = array_column(LIBRARIES, 'variable');
    foreach (array_diff($definedVariables, $handledVariables) as $variable) {
        $errors[] = "The IPE_LATESTVERSION_{$variable} variable is defined in install-php-extensions, but there's no corresponding library in the LIBRARIES constant of the dependency checker";
    }
    foreach (array_diff(SKIPPED_LIBRARIES, array_keys(LIBRARIES)) as $name) {
        $errors[] = "The library {$name} listed in the SKIPPED_LIBRARIES constant of the dependency checker is not defined in the LIBRARIES constant";
    }

    return $errors;
}

/**
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function checkLibrary(string $name, array $library, string $installer): ?Update
{
    $variable = "IPE_LATESTVERSION_{$library['variable']}";
    if (!preg_match('/^' . preg_quote($variable, '/') . '=(\S+)$/m', $installer, $matches)) {
        throw new ActionRequiredException("Unable to find the {$variable} variable in install-php-extensions (it should contain the current version of {$name})");
    }
    $current = $matches[1];
    $source = $library['latest'][0];
    $args = array_slice($library['latest'], 1);
    $timeout = $library['timeout'] ?? DEFAULT_TIMEOUT;
    $latest = match ($source) {
        LatestVersionSource::GitTags => getLatestGitTag(...$args, ignoreTags: $library['ignoreTags'] ?? '', timeout: $timeout),
        LatestVersionSource::GitRef => getGitRefHash(...$args, timeout: $timeout),
        LatestVersionSource::WebPage => getLatestVersionFromWebPage(...$args, timeout: $timeout),
    };
    if ($latest === null) {
        throw new ActionRequiredException("Unable to detect the latest version of {$name}");
    }
    $isNewer = match ($source) {
        LatestVersionSource::GitTags => version_compare(normalizeStableVersion($latest) ?? '0', normalizeStableVersion($current) ?? $current) > 0,
        LatestVersionSource::GitRef => $latest !== $current,
        LatestVersionSource::WebPage => version_compare($latest, $current) > 0,
    };
    if (!$isNewer) {
        logInfo("- {$name}: {$current} is up to date");

        return null;
    }
    logInfo("- {$name}: {$current} => {$latest}");

    return new Update(
        "lib:{$name}",
        $name,
        "{$name} (used by " . implode(', ', $library['usedBy']) . "): we use {$current}, {$latest} is available",
        $latest,
        $library['url'] ?? '',
    );
}

/**
 * @return array<string, string> keys are the update IDs, values are the notified versions
 */
function readState(string $stateFile): array
{
    if ($stateFile === '' || !is_file($stateFile)) {
        return [];
    }
    $state = json_decode((string) file_get_contents($stateFile), true);

    return is_array($state) ? $state : [];
}

/**
 * @param array<string, string> $state keys are the update IDs, values are the notified versions
 */
function writeState(string $stateFile, array $state): void
{
    if ($stateFile === '') {
        return;
    }
    ksort($state);
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

function main(array $argv): int
{
    $stateFile = '';
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--state-file=')) {
            $stateFile = substr($arg, strlen('--state-file='));
        } else {
            fwrite(STDERR, "Unrecognized argument: {$arg}\n");

            return 1;
        }
    }
    $installer = file_get_contents(INSTALLER_PATH);
    if ($installer === false) {
        fwrite(STDERR, "Failed to read install-php-extensions\n");

        return 1;
    }
    $updates = [];
    // IDs of the updates that we could not check
    $failedIDs = [];
    // Problems that require updating this script or install-php-extensions
    $errors = [];

    logInfo('Checking PECL extensions installed as non-stable by default');

    try {
        $unstablePeclModules = getUnstablePeclModules($installer);
    } catch (ActionRequiredException $x) {
        $errors[] = $x->getMessage();
        logError($x->getMessage());
        $unstablePeclModules = [];
    }
    foreach ($unstablePeclModules as $module => $stability) {
        try {
            $update = checkPeclModule($module, $stability);
        } catch (ConnectionException $x) {
            $failedIDs[] = "pecl:{$module}";
            logWarning($x->getMessage());

            continue;
        } catch (ActionRequiredException $x) {
            $failedIDs[] = "pecl:{$module}";
            $errors[] = $x->getMessage();
            logError($x->getMessage());

            continue;
        }
        if ($update !== null) {
            $updates[] = $update;
        }
    }

    logInfo('Checking manually installed libraries');
    foreach (checkLibrariesConsistency($installer) as $error) {
        $errors[] = $error;
        logError($error);
    }
    foreach (LIBRARIES as $name => $library) {
        if (in_array($name, SKIPPED_LIBRARIES, true)) {
            logInfo("- {$name}: skipped");

            continue;
        }

        try {
            $update = checkLibrary($name, $library, $installer);
        } catch (ConnectionException $x) {
            $failedIDs[] = "lib:{$name}";
            logWarning($x->getMessage());

            continue;
        } catch (ActionRequiredException $x) {
            $failedIDs[] = "lib:{$name}";
            $errors[] = $x->getMessage();
            logError($x->getMessage());

            continue;
        }
        if ($update !== null) {
            $updates[] = $update;
        }
    }

    $alreadyNotified = readState($stateFile);
    $newUpdates = array_values(array_filter(
        $updates,
        static fn (Update $update): bool => ($alreadyNotified[$update->id] ?? null) !== $update->newVersion,
    ));
    // Let's keep in the state file only the updates that are still pending (and the ones we could not check because of connection errors)
    $newState = array_intersect_key($alreadyNotified, array_flip($failedIDs));
    foreach ($updates as $update) {
        $newState[$update->id] = $update->newVersion;
    }
    writeState($stateFile, $newState);

    logInfo('');
    logInfo(sprintf('Updates found: %d (new: %d)', count($updates), count($newUpdates)));
    $message = '';
    if ($newUpdates !== []) {
        $lines = ['New versions of dependencies used by docker-php-extension-installer are available:'];
        foreach ($newUpdates as $update) {
            $lines[] = '';
            $lines[] = "- {$update->description}";
            if ($update->url !== '') {
                $lines[] = "  {$update->url}";
            }
        }
        $message = implode("\n", $lines);
        echo $message, "\n";
    }
    setGitHubOutput('message', $message);
    setGitHubOutput('errors', implode("\n", array_map(static fn (string $error): string => "- {$error}", $errors)));
    if ($errors !== []) {
        logInfo(sprintf('Problems that require updating the dependency checker or install-php-extensions: %d', count($errors)));

        return 1;
    }

    return 0;
}

exit(main($argv));
