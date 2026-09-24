# Informations for the repository maintainers

## Publish a new version

The creation of a new version is done automatically by the [`readme-release.yml`](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/readme-release.yml) GitHub Action.

Whenever a push to the GitHub repository changes the [`install-php-extensions`](https://github.com/mlocati/docker-php-extension-installer/blob/master/install-php-extensions) script,
that Action creates a new tag, incrementing the patch level (for example, if the previous version was `1.2.3`, it creates the tag `1.2.4`).
Before doing that, the Action waits for the approval of a maintainer (`readme-release-approval` environment).
If maintainers want to create a different tag (for example, `1.3.3`), they can push it manually: the pending release is then canceled automatically.

Once this new tag is created automatically (or when maintainers push a new version-like tag to the repository), the Action creates a new release, attaching it the `install-php-extensions` script to it
(so that users can download it via the `https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions` URL).

## Monitoring external dependencies

`install-php-extensions` relies on some external dependencies that need to be checked periodically:

- some PECL extensions don't have stable versions (or their stable versions are very old), so by default we install them in a non-stable version (`beta`, `alpha`, ...)
- some libraries and extensions aren't available in the Linux distributions or in the PECL archive, so we download a specific version of them (or a specific git commit)
- some extensions (or the libraries they use) are downloaded in their latest version from outside the PECL archive

The [`check-updates.yml`](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/check-updates.yml) GitHub Action runs every day the [`scripts/check-updates.php`](https://github.com/mlocati/docker-php-extension-installer/blob/master/scripts/check-updates.php) script, which detects:

- the new versions of the PECL extensions (from the feed of the latest PECL releases).
- if the PECL extensions that we install in a non-stable version by default have a more stable release (for example, a `beta` or `stable` release for an extension we install as `alpha`).
  These extensions are detected automatically by parsing `install-php-extensions`.
- the new versions of the libraries and extensions we download manually.
  These are listed in the [`data/dependencies.json`](https://github.com/mlocati/docker-php-extension-installer/blob/master/data/dependencies.json) file: for the ones with `"pinnedVersion": true`, the version in use is read from the `IPE_LIBVERSION_...` (libraries) and `IPE_EXTVERSION_...` (PHP extensions) variables defined at the beginning of `install-php-extensions`, so there's no need to update the script when upgrading a dependency.
  The ones with `"pinnedVersion": false` are downloaded by `install-php-extensions` in their latest version.

The new versions are then tested on all the supported Linux distributions (the new versions of the libraries and extensions we download manually are tested by setting the corresponding `IPE_LIBVERSION_...`/`IPE_EXTVERSION_...` variable).
New versions that fail are tested again every day, until they work.

A Telegram notification is sent:

- every time some tests fail
- when a new version of a library or extension we download manually is available (only once, saying whether its tests passed)
- when a PECL extension that we install in a non-stable version has a more stable release (only once)
- when there are problems that require updating the script, `data/dependencies.json` or `install-php-extensions` (for example, when the version in use or the latest version of a dependency can't be detected)

Temporary problems (like websites that can't be reached) are only reported as warnings in the Action log.

The detection can also be executed locally:

```sh
php scripts/check-updates.php detect --state-file=check-updates-state.json --tests-file=check-updates-tests.txt
```

When adding to `install-php-extensions` a new library or extension that is downloaded manually, remember to add it to `data/dependencies.json`.
If we download a specific version of it, set `"pinnedVersion": true` and define its version in a new `IPE_LIBVERSION_...` variable (for libraries) or `IPE_EXTVERSION_<EXTENSION>` variable (for PHP extensions), with the format `VARIABLE="${VARIABLE:-version}"` so that it can be overridden
(the script fails if one of these variables doesn't have a corresponding entry in `data/dependencies.json`, and vice versa).
The libraries with a pinned version are also documented in the README.md file (the default versions are read from `install-php-extensions`).
If a library or extension shouldn't be checked for updates, add a `skipCheck` property explaining why; if the new versions of a pinned library or extension can't be tested automatically, add a `skipTest` property explaining why.
If the latest version can't be detected with the generic sources, use the `custom` source and add to the `CustomLatestVersion` class of `scripts/check-updates.php` a method with the name of the key of the item.
