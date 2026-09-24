<?php

declare(strict_types=1);

/*
 * Check whether the external dependencies used by install-php-extensions have newer versions:
 * - PECL extensions that we install as non-stable by default (beta, alpha, ...): is there a more stable version?
 * - libraries and PHP extensions that we download manually (listed in data/dependencies.json): is there a newer version?
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

require_once __DIR__ . '/dependencies.php';

const INSTALLER_PATH = __DIR__ . '/../install-php-extensions';

const USER_AGENT = 'mlocati/docker-php-extension-installer dependency checker';

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
        foreach (array_keys($items) as $key) {
            $handledVariables[] = getDependencyVariable($section, (string) $key);
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
 * @param array<string, mixed> $dependency an item of data/dependencies.json
 * @param string[]             $usedBy
 *
 * @throws ConnectionException
 * @throws ActionRequiredException
 */
function checkDependency(array $dependency, string $variable, array $usedBy, string $installer): ?Update
{
    $name = $dependency['name'];
    $current = getInstallerVariableDefault($installer, $variable);
    if ($current === null) {
        throw new ActionRequiredException("Unable to find the {$variable} variable in install-php-extensions (it should contain the current version of {$name})");
    }
    $latestVersion = $dependency['latestVersion'];
    $source = LatestVersionSource::tryFrom($latestVersion['source'] ?? '');
    if ($source === null) {
        throw new ActionRequiredException("Invalid latestVersion.source of {$name} in data/dependencies.json");
    }
    $timeout = $latestVersion['timeout'] ?? DEFAULT_TIMEOUT;
    $latest = match ($source) {
        LatestVersionSource::GitTags => getLatestGitTag($latestVersion['repository'], $latestVersion['ignoreTags'] ?? '', $timeout),
        LatestVersionSource::GitRef => getGitRefHash($latestVersion['repository'], $latestVersion['ref'], $timeout),
        LatestVersionSource::WebPage => getLatestVersionFromWebPage($latestVersion['url'], $latestVersion['regex'], $timeout),
    };
    if ($latest === null) {
        throw new ActionRequiredException("Unable to detect the latest version of {$name}");
    }
    $isNewer = match ($source) {
        LatestVersionSource::GitTags => version_compare(normalizeStableVersion($latest) ?? '0', normalizeStableVersion($current) ?? $current) > 0,
        LatestVersionSource::GitRef => $latest !== $current,
        LatestVersionSource::WebPage => version_compare(getVersionWithoutSuffix($latest), getVersionWithoutSuffix($current)) > 0,
    };
    if (!$isNewer) {
        logInfo("- {$name}: {$current} is up to date");

        return null;
    }
    logInfo("- {$name}: {$current} => {$latest}");

    return new Update(
        "lib:{$name}",
        $name,
        "{$name} (used by " . implode(', ', $usedBy) . "): we use {$current}, {$latest} is available",
        $latest,
        $dependency['url'] ?? '',
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

    logInfo('Checking manually installed libraries and PHP extensions');

    try {
        $dependencies = readDependencies();
    } catch (RuntimeException $x) {
        $errors[] = $x->getMessage();
        logError($x->getMessage());
        $dependencies = ['libraries' => [], 'extensions' => []];
    }
    foreach (checkDependenciesConsistency($installer, $dependencies) as $error) {
        $errors[] = $error;
        logError($error);
    }
    foreach ($dependencies as $section => $items) {
        foreach ($items as $key => $dependency) {
            $name = $dependency['name'];
            if (isset($dependency['skipCheck'])) {
                logInfo("- {$name}: skipped");

                continue;
            }

            try {
                $update = checkDependency(
                    $dependency,
                    getDependencyVariable($section, (string) $key),
                    $section === 'libraries' ? $dependency['usedBy'] : [(string) $key],
                    $installer,
                );
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
