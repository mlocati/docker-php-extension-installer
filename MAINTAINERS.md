# Informations for the repository maintainers

## Publish a new version

The creation of a new version is done automatically by the [`readme-release.yml`](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/readme-release.yml) GitHub Action.

Whenever a push to the GitHub repository changes the [`install-php-extensions`](https://github.com/mlocati/docker-php-extension-installer/blob/master/install-php-extensions) script,
that Action creates a new tag, incrementing the patch level (for example, if the previous version was `1.2.3`, it creates the tag `1.2.4`).
Before doing that, the Action waits for 30 seconds, so that maintainers can cancel the tag creation if they want to create a different tag (for example, `1.3.3`).

Once this new tag is created automatically (or when maintainers push a new version-like tag to the repository), the Action creates a new release, attaching it the `install-php-extensions` script to it
(so that users can download it via the `https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions` URL).

## Monitoring external dependencies

`install-php-extensions` relies on some external dependencies that need to be checked periodically:

- some PECL extensions don't have stable versions (or their stable versions are very old), so by default we install them in a non-stable version (`beta`, `alpha`, ...)
- some libraries and extensions aren't available in the Linux distributions or in the PECL archive, so we download a specific version of them (or a specific git commit)

The [`check-dependency-updates.yml`](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/check-dependency-updates.yml) GitHub Action runs every two days the [`scripts/check-dependency-updates.php`](https://github.com/mlocati/docker-php-extension-installer/blob/master/scripts/check-dependency-updates.php) script, which checks:

- if the PECL extensions that we install in a non-stable version by default have a more stable release (for example, a `beta` or `stable` release for an extension we install as `alpha`).
  These extensions are detected automatically by parsing `install-php-extensions`.
- if the libraries and extensions we download manually have newer versions.
  These are listed in the `LIBRARIES` constant of the script: the version in use is read from the `IPE_LATESTVERSION_...` variables defined at the beginning of `install-php-extensions`, so there's no need to update the script when upgrading a dependency.

When new versions are found, a Telegram notification is sent (every new version is notified only once).
Temporary problems (like websites that can't be reached) don't make the Action fail: they are reported as warnings in the Action log.
Problems that require updating the script or `install-php-extensions` (for example, when the version in use or the latest version of a dependency can't be detected) make the Action fail, and a Telegram notification listing them is sent.

The script can also be executed locally:

```sh
php scripts/check-dependency-updates.php
```

When adding to `install-php-extensions` a new library or extension that is downloaded manually, remember to define its version in a new `IPE_LATESTVERSION_...` variable, and to add it to the `LIBRARIES` constant of the script
(the script fails if an `IPE_LATESTVERSION_...` variable doesn't have a corresponding entry in `LIBRARIES`).
If a library shouldn't be checked for updates, add it to the `SKIPPED_LIBRARIES` constant of the script, explaining why.
