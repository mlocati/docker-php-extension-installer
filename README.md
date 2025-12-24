[![Downloaded GitHub Releases](https://img.shields.io/github/downloads/mlocati/docker-php-extension-installer/total?label=Downloaded%20releases)](https://github.com/mlocati/docker-php-extension-installer/releases)
[![Docker Hub pulls](https://img.shields.io/docker/pulls/mlocati/php-extension-installer?label=Docker%20Hub%20pulls)](https://hub.docker.com/r/mlocati/php-extension-installer)
[![GitHub Registry pulls](https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/refs/heads/assets/resources/ghcr-badge.svg)](https://github.com/mlocati/docker-php-extension-installer/pkgs/container/php-extension-installer)
[![Test recent](https://github.com/mlocati/docker-php-extension-installer/actions/workflows/test-recent-extensions.yml/badge.svg)](https://github.com/mlocati/docker-php-extension-installer/actions/workflows/test-recent-extensions.yml)

# Easy installation of PHP extensions in official PHP Docker images

This repository contains a script that can be used to easily install a PHP extension inside the [official PHP Docker images](https://hub.docker.com/_/php/).

The script will install all the required APT/APK packages; at the end of the script execution, the no-more needed packages will be removed so that the image will be much smaller.

Supported docker images are:

- Debian-based docker images: since jessie (Debian 8) (minimum PHP version: 5.5)
- Alpine-based docker images: since Alpine 3.9 (minimum PHP version: 7.1)

See also the notes in the [Special requirements](#special-requirements) section.

## Usage

You have many ways to use this script within your `Dockerfile`s.

Here's a list of sample `Dockerfile`s that install the GD and xdebug PHP extensions:

### Downloading the script on the fly with `ADD`

```Dockerfile
FROM php:7.2-cli

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd xdebug
```

### Downloading the script on the fly with `curl`

```Dockerfile
FROM php:7.2-cli

RUN curl -sSLf \
        -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions gd xdebug
```

### Direct execution with `curl`

```Dockerfile
FROM php:8.2-cli

RUN ( curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - || echo 'return 1' ) | sh -s \
      gd xdebug
```

### Copying the script from a Docker image

- using GitHub Container Registry
  
  ```Dockerfile
  FROM php:8.4-cli
  
  COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
  
  RUN install-php-extensions gd xdebug
  ```
- using Docker Hub
  
  ```Dockerfile
  FROM php:8.4-cli
  
  COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
  
  RUN install-php-extensions gd xdebug
  ```

**Warning**: by using this method you may use an outdated version of the Docker image.
You may want to run `docker pull ghcr.io/mlocati/php-extension-installer` or `docker pull mlocati/php-extension-installer` in order to use an up-to-date version.

### Using the script of a Docker image

- using GitHub Container Registry
  
  ```Dockerfile
  RUN  --mount=type=bind,from=ghcr.io/mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
      install-php-extensions gd xdebug
  ```
- using Docker Hub
  
  ```Dockerfile
  RUN  --mount=type=bind,from=mlocati/php-extension-installer:latest,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
      install-php-extensions gd xdebug
  ```

**Warning**: by using this method you may use an outdated version of the Docker image image.
You may want to run `docker pull ghcr.io/mlocati/php-extension-installer` or `docker pull mlocati/php-extension-installer` in order to use an up-to-date version.


## Installing specific versions of an extension

Simply append `-<version>` to the module name.
For example:

```sh
install-php-extensions xdebug-2.9.7
```

The script also supports resolving *compatible* versions by prefixing the version with a caret (`^`).
For example:

```sh
# Install the most recent xdebug 2.x version (for example 2.9.8)
install-php-extensions xdebug-^2
# Install the most recent xdebug 2.8.x version (for example 2.8.1)
install-php-extensions xdebug-^2.8
```

Please remark that with the syntax above you'll get the very latest compatible version, which may be unstable. In order to install the most recent *stable* version, you can append `@stable`:

```sh
# Install the most recent STABLE xdebug 3.x version (for example 3.2.2)
install-php-extensions xdebug-^3@stable
```

(valid suffixes are: `@snapshot`, `@devel`, `@alpha`, `@beta`, and `@stable`)

Pre-release versions extensions available on `PECL` can be setup by suffixing the extension's name with its state i.e. `alpha`, `beta`, `rc`, `preview`, `devel` or `snapshot`.
For example:

```sh
install-php-extensions xdebug-beta
```

TIP: When the latest version available on `PECL` is not stable, and you want to keep the last stable version, 
force it by suffixing the extension's name with the `stable` state.
For example:

```sh
install-php-extensions mongodb-stable
```

## Installing an extension from its source code

You can also install PHP extensions from source code (provided that it comes with a `package.xml` or a `package2.xml` file).

Accepted formats are:

- A short version for repositories hosted on GitHub.  
  For example, for the [php-memcached-dev/php-memcached](https://github.com/php-memcached-dev/php-memcached) GitHub repository,
  you can simply write:
  ```sh
  # Install from a specific commit (full commit SHA-1)
  install-php-extensions php-memcached-dev/php-memcached@8f106564e6bb005ca6100b12ccc89000daafa9d8
  # Install from a specific commit (short commit SHA-1)
  install-php-extensions php-memcached-dev/php-memcached@8f106564e6bb
  # Install from tag v3.2.0RC2
  install-php-extensions php-memcached-dev/php-memcached@v3.2.0RC2
  install-php-extensions php-memcached-dev/php-memcached@refs/tags/v3.2.0RC2
  # Install from branch master
  install-php-extensions php-memcached-dev/php-memcached@master
  install-php-extensions php-memcached-dev/php-memcached@refs/heads/master
  ```
- An URL providing an archive containing the source code.  
  Examples:
  ```sh
  # tgz archive for commit 8f106564e6bb005ca6100b12ccc89000daafa9d8
  install-php-extensions https://codeload.github.com/php-memcached-dev/php-memcached/tar.gz/8f106564e6bb005ca6100b12ccc89000daafa9d8
  # tgz archive for tag v3.1.5
  install-php-extensions https://codeload.github.com/php-memcached-dev/php-memcached/tar.gz/refs/tags/v3.1.5
  # tgz archive for branch master
  install-php-extensions https://codeload.github.com/php-memcached-dev/php-memcached/tar.gz/refs/heads/master
  ```
- The absolute path of a local directory.  
  Examples:
  ```sh
  # Download the source code
  curl -o /tmp/source.tgz https://codeload.github.com/php-memcached-dev/php-memcached/tar.gz/refs/tags/v3.1.5
  tar xzf /tmp/source.tgz -C /tmp
  install-php-extensions /tmp/php-memcached-3.1.5

## Bundled extensions or not?

There are some extensions that are bundled in the PHP source code (for example, `gd` and `zip`).

Some of these extensions are also available on PECL and/or a source code repository.

By default, `install-php-extensions` installs the bundled version.

If instead you want to use a remote version:

- for using PECL: just append the stability to the package name. For example:
  ```
  install-php-extensions zip-stable
  ```
- for installing from source code: use the syntax [specified above](#installing-an-extension-from-its-source-code). For example:
  ```
  install-php-extensions php/pecl-database-oci8@7aa1061
  ```

`install-php-extensions` supports installing remote versions instead of bundled ones for these extensions:

- pdo_oci
- oci8
- zip

## Installing composer

You can also install [composer](https://getcomposer.org/), and you also can specify a major version of it, or a full version.

Examples:

```sh
# Install the latest version
install-php-extensions @composer
# Install the latest 1.x version
install-php-extensions @composer-1
# Install a specific version
install-php-extensions @composer-2.0.2
```

## Issue with Let's Encrypt certificates

The root CA certificate of Let's Encrypt changes ([more details here](https://letsencrypt.org/docs/dst-root-ca-x3-expiration-september-2021/)).  
That breaks old linux distributions, namely:

- Debian Jessie (8)
- Debian Stretch (9)
- Alpine Linux 3.7
- Alpine Linux 3.8

This script can fix this issue: simply pass `@fix_letsencrypt` as an argument:

```sh
install-php-extensions @fix_letsencrypt
```

## Supported PHP extensions

<!-- START OF EXTENSIONS TABLE -->
<!-- ########################################################### -->
<!-- #                                                         # -->
<!-- #  DO NOT EDIT THIS TABLE: IT IS GENERATED AUTOMATICALLY  # -->
<!-- #                                                         # -->
<!-- #  EDIT THE data/supported-extensions FILE INSTEAD        # -->
<!-- #                                                         # -->
<!-- ########################################################### -->
| Extension | PHP 8.5 | PHP 8.4 | PHP 8.3 | PHP 8.2 | PHP 8.1 | PHP 8.0 | PHP 7.4 | PHP 7.3 | PHP 7.2 | PHP 7.1 | PHP 7.0 | PHP 5.6 | PHP 5.5 |
|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| amqp |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| apcu | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| apcu_bc |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; |  |  |
| ast | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| bcmath | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| bitset | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| blackfire | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| brotli | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| bz2 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| calendar | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| cassandra[*](#special-requirements-for-cassandra) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| cmark |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; |  |  |
| csv | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |
| dba | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ddtrace[*](#special-requirements-for-ddtrace) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| decimal | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| ds | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| ecma_intl[*](#special-requirements-for-ecma_intl) |  |  | &check; | &check; |  |  |  |  |  |  |  |  |  |
| enchant | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ev | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| event | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| excimer | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| exif | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ffi | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| ftp | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |  |
| gd | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gearman |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| geoip |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| geos[*](#special-requirements-for-geos) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| geospatial | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gettext | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gmagick |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gmp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gnupg | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| grpc |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| http | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| igbinary | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| imagick | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| imap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| inotify | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| interbase |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| intl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ion | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |
| ioncube_loader |  | &check; | &check; | &check; | &check; |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ip2location | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| jsmin |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| json_post | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| jsonpath | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| ldap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| luasandbox | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| lz4[*](#special-requirements-for-lz4) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| lzf | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mailparse | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| maxminddb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| mcrypt | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| md4c | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| memcache |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| memcached | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| memprof[*](#special-requirements-for-memprof) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mongo |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| mongodb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mosquitto |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| msgpack | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mssql |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| mysql |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| mysqli | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| newrelic |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| nsq |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| oauth | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| oci8 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| odbc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| opcache | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| opencensus |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| openswoole |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| opentelemetry | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| operator | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |  |
| parallel[*](#special-requirements-for-parallel) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| parle[*](#special-requirements-for-parle) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pcntl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pcov | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pdo_dblib | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_firebird | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_mysql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_oci |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pdo_odbc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_pgsql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_sqlsrv |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| pgsql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| phalcon |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| php_trie |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |
| phpy[*](#special-requirements-for-phpy) | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |  |
| pkcs11 | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| pq | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| propro |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| protobuf | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pspell | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| psr | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pthreads[*](#special-requirements-for-pthreads) |  |  |  |  |  |  |  |  |  |  | &check; | &check; | &check; |
| raphf | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| rdkafka | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| recode |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| redis | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| relay | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |
| saxon[*](#special-requirements-for-saxon) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| seasclick[*](#special-requirements-for-seasclick) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| seaslog |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| shmop | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| simdjson[*](#special-requirements-for-simdjson) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| smbclient | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snappy | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snmp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snuffleupagus |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| soap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sockets | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sodium[*](#special-requirements-for-sodium) |  |  |  |  |  |  |  |  |  | &check; | &check; | &check; |  |
| solr | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sourceguardian | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| spx | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sqlsrv[*](#special-requirements-for-sqlsrv) |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| ssh2 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| stomp |  | &check; | &check; | &check; |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| swoole |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sybase_ct |  |  |  |  |  |  |  |  |  |  |  | &check; | &check; |
| sync |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvmsg | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvsem | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvshm | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| tensor |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |
| tideways |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| tidy | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| timezonedb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| translit | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uopz |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uploadprogress | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uuid | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uv | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| vips[*](#special-requirements-for-vips) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| vld | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| wddx |  |  |  |  |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| wikidiff2[*](#special-requirements-for-wikidiff2) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| xattr | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xdebug | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xdiff | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xhprof | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xlswriter | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| xmldiff | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xmlrpc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xpass[*](#special-requirements-for-xpass) | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |  |  |  |  |
| xsl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| yac | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| yaml | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| yar | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zephir_parser | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| zip | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zmq |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zookeeper |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zstd | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |

*Number of supported extensions: 157*
<!-- END OF EXTENSIONS TABLE -->

PS: the pre-installed PHP extensions are excluded from this list.
You can list them with the following command (change `php:8.5-cli` to reflect the PHP version you are interested in):

```
$ docker run --rm php:8.5-cli php -m
[PHP Modules]
Core
ctype
curl
date
dom
fileinfo
filter
hash
iconv
json
lexbor
libxml
mbstring
mysqlnd
openssl
pcre
PDO
pdo_sqlite
Phar
posix
random
readline
Reflection
session
SimpleXML
sodium
SPL
sqlite3
standard
tokenizer
uri
xml
xmlreader
xmlwriter
Zend OPcache
zlib

[Zend Modules]
Zend OPcache
```

## Configuration

You can configure the behavior of the script, as well as fine-tune some extensions in order fit your needs, by using environment variables.

Here's an example:

```sh
IPE_LZF_BETTERCOMPRESSION=1 install-php-extensions lzf
```

Here's the list of all the supported environment variables:

| Extension | Environment variable | Description |
|---|---|---|
| | `IPE_DEBUG=1` | By setting this environment variable, the script will print all the commands it executes (it will be very verbose, useful only for debug purposes) |
| | `IPE_PROCESSOR_COUNT` | By default all available processors. Set this environment variable to override the number of processors detected by the script (used for parallel compilation) |
| | `IPE_DONT_ENABLE=1` | By default the script will install and enable the extensions.<br />If you want to only install them (without enabling them) you can set this environment variable.<br />To enable the extensions at a later time you can execute the command `docker-php-ext-enable-<extension>` (for example: `docker-php-ext-enable-xdebug`).<br />**Beware**: installing some PHP extensions requires that other PHP extensions are already enabled, so use this feature wisely. |
| | `IPE_SKIP_CHECK=1` | By default the script will check if the extensions can be enabled: if you want to skip this check, you can use this flag.<br />**Beware**: extensions may be enabled even if they break PHP: use this function wisely. |
| | `IPE_KEEP_SYSPKG_CACHE=1` | By default the script will clear the apt/apk/pear cache in order to save disk space. You can disable it by setting this environment variable |
| lzf | `IPE_LZF_BETTERCOMPRESSION=1` | By default `install-php-extensions` compiles the `lzf` extension to prefer speed over size; you can use this environment variable to compile it preferring size over speed |
| event | `IPE_EVENT_NAMESPACE=`... | By default, the `event` classes are defined in the root namespace. You can use this environment variable to specify a custom namespace |
| gd | `IPE_GD_WITHOUTAVIF=1` | Since PHP 8.1, gd supports the AVIF format. Enabling it requires compiling libaom/libdav1d/libyuv/libavif on Debian up to 11 and Alpine up to 3.14, which is time-consuming. You can disable AVIF support by setting this environment variable on those operating system versions |
| oci8 & pdo_oci | `IPE_INSTANTCLIENT_BASIC=1` | The oci8 and pdo_oci PHP extensions require [Oracle Instant Client](https://www.oracle.com/database/technologies/instant-client.html). In order to save disk space, we install the Basic Lite version: if you want to install the Basic (non-lite) version simply set this environment variable |
| http, intl, mongodb | `IPE_ICU_EN_ONLY=1` | Some extensions require the ICU library, use this flag to install a smaller, but English-only, ICU library on Alpine 3.16 and later |
| pspell | `IPE_ASPELL_LANGUAGES='...'` | Configure the languages to be made available (for example: `IPE_ASPELL_LANGUAGES='en fr'`). If omitted, we'll assume `en` |
| | `IPE_DEB_ARCHIVE` & `IPE_DEB_ARCHIVE_SECURITY` | The APT packages of very old Debian versions (eg Jessie) may have been archived: you can use these environment variables to specify custom URLs of these APT archives |
| newrelic | `IPE_NEWRELIC_DAEMON=1` | Install the NewRelic daemon  |
| newrelic | `IPE_NEWRELIC_KEEPLOG=1` | Keep the log files of NewRelic setup (`/tmp/nrinstall-….tar`)  |
| newrelic | `NR_INSTALL_KEY` | Your New Relic license key |
| swoole | `IPE_SWOOLE_WITHOUT_IOURING=1` | The io_uring kernel functionality is considered unsafe by security experts (see [here](https://security.googleblog.com/2023/06/learnings-from-kctf-vrps-42-linux.html) and [here](https://i.blackhat.com/BH-US-23/Presentations/US-23-Lin-bad_io_uring.pdf)). By default Swoole 6 and later is configured with io_uring support, use this environment variable to skip configuring io_uring |
| saxon | `IPE_SAXON_EDITION=EE` | The Saxon edition to be used: `EE` for Enterprise Edition (default), `PE` for Professional Edition, `HE` for Home Edition |

## Special requirements

Some extensions have special requirements:

<!-- START OF SPECIAL REQUIREMENTS -->
<!-- ########################################################### -->
<!-- #                                                         # -->
<!-- #  DO NOT EDIT THIS TABLE: IT IS GENERATED AUTOMATICALLY  # -->
<!-- #                                                         # -->
<!-- #  EDIT THE data/special-requirements FILE INSTEAD        # -->
<!-- #                                                         # -->
<!-- ########################################################### -->
| Extension | Requirements |
|---|---|
| <a name="special-requirements-for-cassandra"></a>cassandra | &bull; Not available in `jessie` docker images<br />&bull; Not available in `stretch` docker images<br />&bull; Not available in `buster` docker images<br />&bull; Not available in `bullseye` docker images<br />&bull; Not available in `bookworm` docker images<br />&bull; Not available in `trixie` docker images |
| <a name="special-requirements-for-ddtrace"></a>ddtrace | Not available in `jessie` docker images |
| <a name="special-requirements-for-ecma_intl"></a>ecma_intl | &bull; Not available in `buster` docker images<br />&bull; Not available in `bullseye` docker images<br />&bull; Not available in `trixie` docker images<br />&bull; Not available in `alpine3.22` docker images<br />&bull; Not available in `alpine3.23` docker images |
| <a name="special-requirements-for-geos"></a>geos | &bull; Not available in `alpine3.9` docker images<br />&bull; Not available in `alpine3.10` docker images |
| <a name="special-requirements-for-lz4"></a>lz4 | Not available in `jessie` docker images |
| <a name="special-requirements-for-memprof"></a>memprof | &bull; Not available in `alpine3.9` docker images<br />&bull; Not available in `alpine3.10` docker images<br />&bull; Not available in `alpine3.11` docker images<br />&bull; Not available in `alpine3.12` docker images<br />&bull; Not available in `alpine3.13` docker images<br />&bull; Not available in `alpine3.14` docker images<br />&bull; Not available in `alpine3.15` docker images |
| <a name="special-requirements-for-parallel"></a>parallel | Requires images with PHP compiled with thread-safety enabled (`zts`) |
| <a name="special-requirements-for-parle"></a>parle | Not available in `jessie` docker images |
| <a name="special-requirements-for-phpy"></a>phpy | Not available in `buster` docker images |
| <a name="special-requirements-for-pthreads"></a>pthreads | Requires images with PHP compiled with thread-safety enabled (`zts`) |
| <a name="special-requirements-for-saxon"></a>saxon | &bull; Not available in `alpine3.7` docker images<br />&bull; Not available in `alpine3.8` docker images<br />&bull; Not available in `alpine3.9` docker images<br />&bull; Not available in `alpine3.10` docker images<br />&bull; Not available in `alpine3.11` docker images<br />&bull; Not available in `7.2-alpine` docker images<br />&bull; Not available in `7.3-alpine` docker images<br />&bull; Not available in `7.4-alpine` docker images |
| <a name="special-requirements-for-seasclick"></a>seasclick | Not available in `alpine3.23` docker images |
| <a name="special-requirements-for-simdjson"></a>simdjson | &bull; Not available in `jessie` docker images<br />&bull; Not available in `stretch` docker images |
| <a name="special-requirements-for-sodium"></a>sodium | Not available in `jessie` docker images |
| <a name="special-requirements-for-sqlsrv"></a>sqlsrv | &bull; Not available in `7.1-alpine3.9` docker images<br />&bull; Not available in `7.1-alpine3.10` docker images |
| <a name="special-requirements-for-vips"></a>vips | &bull; Not available in `alpine3.9` docker images<br />&bull; Not available in `jessie` docker images |
| <a name="special-requirements-for-wikidiff2"></a>wikidiff2 | &bull; Not available in `jessie` docker images<br />&bull; Not available in `stretch` docker images |
| <a name="special-requirements-for-xpass"></a>xpass | Not available in `buster` docker images |
<!-- END OF SPECIAL REQUIREMENTS -->

### How do I know which Linux distribution I am using?

You can run this command:

```sh
cat /etc/os-release
```

For example:
- for Debian 11 (Bullseye) you'll see:
  ```sh
  PRETTY_NAME="Debian GNU/Linux 11 (bullseye)"
  ```
- for Alpine Linux 3.14 you'll see:
  ```sh
  PRETTY_NAME="Alpine Linux v3.14"
  ```

## Tests

When submitting a pull request, a [GitHub Action](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/test-extensions.yml) is executed to check if affected PHP extensions actually work (see below).

Furthermore, we also check that new versions of extensions in the PECL repository will still work.
This is done on a scheduled basis with another [GitHub Action](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/test-recent-extensions.yml).  
In case of failure, a message is sent to a [Telegram Channel](https://t.me/docker_php_extension_installer).  
Feel free to subscribe to it to receive failure notifications.

## How to contribute

### Formatting code

Before submitting any pull request, you should execute the `lint` script in the `scripts` directory (or `lint.bat` on Windows).

If you don't do that, and if there's a coding style error, you'll see that the `Check shell coding style` and/or the `Check PHP coding style` GitHub Actions will fail.

The error will be something like this:

```diff
--- filename.orig
+++ filename
@@ -line number,7 +line number,7 @@
     good line of code #1
     good line of code #2
     good line of code #3
-    the original line with a wrong coding style
+    the line wrong coding style that has been corrected
     good line of code #4
     good line of code #5
     good line of code #6
```

So, you should fix highlighted line (the one(s) at `line number`) by replacing what you see after the `-` with what you see after the `+`

### Adding support to a new PHP extension?

1. change the `install-php-extensions` script
2. update the `data/supported-extensions` file, adding a new line with the handle of the extension and the list of supported PHP versions
3. if the extension requires ZTS images:  
   add a new line to the `data/special-requirements` file, with the extension handle followed by a space and `zts`

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/60) for an example.

### Changing the supported PHP versions for an already supported PHP extension?

1. change the `install-php-extensions` script
2. update the `data/supported-extensions` file, adding the new PHP version to the existing line corresponding to the updated extension

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/62) for an example.

### Improving code for an already supported extension?

If you change some code that affects one or more extensions, please add a line with `Test: extension1, extension2` to the message of one of the pull request commits.
That way, the test jobs will check the extension even if you don't touch the `data/supported-extensions` file.

Here's an example of a commit message:

```
Improve the GD and ZIP extensions

Test: gd, zip
```

Tests only check the installation of a single PHP extension at a time.
If you want to test installing more PHP extensions at the same time, use a commit message like this:

```
Improve the GD and ZIP extensions

Test: gd+zip
```

If your pull request contains multiple commits, we'll check the "Test:" message of every commit.
If you want to stop parsing next commits, add `-STOP-` in the "Test:" line, for example:

```
Improve the GD and ZIP extensions

Test: gd, zip, -STOP-
```

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/43) for an example.

### PHP requirements and configure options

PHP extensions published on the PECL archive contain a `package.xml` (or `package2.xml`) file describing the supported PHP versions and the options that can be used to compile it.
When we add support for a new PHP extension, and when a new version of a PHP extension is released, we have to check those constraints.

It's a rather tedious task, so I developed a project that lets you easily check those constraints: you can find it at https://mlocati.github.io/pecl-info ([here](https://github.com/mlocati/pecl-info) you can find its source code).

## For the maintainers

See the [`MAINTAINERS.md`](https://github.com/mlocati/docker-php-extension-installer/blob/master/MAINTAINERS.md) file.

## Do you want to really say thank you?

You can offer me a [monthly coffee](https://github.com/sponsors/mlocati) or a [one-time coffee](https://paypal.me/mlocati) :wink:
