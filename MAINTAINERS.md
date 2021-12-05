# Informations for the repository maintainers

## Publish a new version

The creation of a new version is done automatically by the [`readme-release.yml`](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/readme-release.yml) GitHub Action.

Whenever a push to the GitHub repository changes the [`install-php-extensions`](https://github.com/mlocati/docker-php-extension-installer/blob/master/install-php-extensions) script,
that Action creates a new tag, incrementing the patch level (for example, if the previous version was `1.2.3`, it creates the tag `1.2.4`).
Before doing that, the Action waits for 30 seconds, so that maintainers can cancel the tag creation if they want to create a different tag (for example, `1.3.3`).

Once this new tag is created automatically (or when maintainers push a new version-like tag to the repository), the Action creates a new draft release, attaching it the `install-php-extensions` script to it
(so that users can download it via the `https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions` URL.

Once that draft release has been created, you have to:

1. go to the [releases page](https://github.com/mlocati/docker-php-extension-installer/releases)
2. edit the newly created draft release
3. review the release notes
4. publish the release

## Extensions to be monitored

### cmark

The `cmark` PHP extension requires the `libcmark` system library.
It's not available on Debian/Alpine Linux, so we install it maually.
We need to monitor new releases at https://github.com/commonmark/cmark/releases

### decimal

The `decimal` PHP extension requires the `libmpdec` system library.
It's not available on Alpine Linux, so we install it manually.
We need to monitor new releases at https://www.bytereef.org/mpdecimal/changelog.html

### gearman

The `gearman` PHP extension requires the `libgearman` system library.
It's not available on Alpine Linux, so we install it manually.
We need to monitor new releases at https://github.com/gearman/gearmand/releases

### geoip

The latest stable release of the `geoip` PHP extension is very old, so we install the latest beta release.
We should switch to the stable release once it will be available.

### geospatial

The only available versions of the `geospatial` PHP extension are all beta.
We should switch to the stable release once it will be available.

### gmagick

The only available versions of the `gmagick` PHP extension are all alpha/beta.
We should switch to the stable release once it will be available.

### http

The `http` PHP extension may use the `libidnkit` system library since version 3.0.0.
It's not available on Alpine Linux, so we install it manually.
We need to monitor new releases at https://jprs.co.jp/idn

### ionCube Loader

The `ionCube Loader` PHP extension is not available in the PECL archive, so we install it manually.
We need to monitor new releases at https://www.ioncube.com/news.php

### mosquitto

The only available versions of the `mosquitto` PHP extension are all alpha/beta.
We should switch to the stable release once it will be available.

### opencensus

The only available versions of the `opencensus` PHP extension are all alpha.
We should switch to the stable release once it will be available.

### snuffleupagus

The `snuffleupagus` PHP extension is not available in the PECL archive, so we install it manually.
We need to monitor new releases at https://github.com/jvoisin/snuffleupagus/releases

## spx

Currenly the `spx` PHP extension installs with a commit version.
We should switch to a tagged realease when it will be available (see https://github.com/NoiseByNorthwest/php-spx/issues/174).

### sqlsrv / pdo_sqlsrv 

The `pdo_sqlsrv` and `sqlsrv` PHP extensions require the Microsoft ODBC Driver for SQL Server.
On Alpine Linux there's no way to automatically install its latest version, so we install it manually.
We need to monitor new releases at https://docs.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server#alpine17

### ssh2

The latest stable release of the `ssh2` PHP extension is very old, so we install the latest beta release.
We should switch to the stable release once it will be available.

### zookeeper

The latest stable release of the `zookeeper` PHP extension doesn't support PHP 7.3+, so we install the alpha version.
We should switch to the stable release once it will be available.
