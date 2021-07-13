[![Downloaded GitHub Releases](https://img.shields.io/github/downloads/mlocati/docker-php-extension-installer/total?label=Downloaded%20releases)](https://github.com/mlocati/docker-php-extension-installer/releases)
[![Docker Hub pulls](https://img.shields.io/docker/pulls/mlocati/php-extension-installer?label=Docker%20Hub%20pulls)](https://hub.docker.com/r/mlocati/php-extension-installer)
[![Test recent](https://github.com/mlocati/docker-php-extension-installer/workflows/Test%20recent/badge.svg)](https://github.com/mlocati/docker-php-extension-installer/actions?query=workflow%3A%22Test+recent%22)

# Easy installation of PHP extensions in official PHP Docker images

This repository contains a script that can be used to easily install a PHP extension inside the [official PHP Docker images](https://hub.docker.com/_/php/).

The script will install all the required APT/APK packages; at the end of the script execution, the no-more needed packages will be removed so that the image will be much smaller.

Supported docker images are all the Alpine/Debian versions, except for PHP 5.5 where we only support Debian 8 (jessie) (that is, `php:5.5`, `php:5.5-apache`, `php:5.5-cli`, `php:5.5-fpm`, `php:5.5-zts`).
See also the notes in the [Special requirements](#special-requirements) section.

## Usage

You have two ways to use this script within your `Dockerfile`s: you can download the script on the fly, or you can grab it from the [`mlocati/php-extension-installer` Docker Hub image](https://hub.docker.com/r/mlocati/php-extension-installer).
With the first method you are sure you'll always get the very latest version of the script, with the second method the process is faster since you'll use a local image.

For example, here are two `Dockerfile`s that install the GD and xdebug PHP extensions:

### Downloading the script on the fly

```Dockerfile
FROM php:7.2-cli

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions gd xdebug
```

### Copying the script from a Docker image

```Dockerfile
FROM php:7.2-cli

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions gd xdebug
```

#### *Beware*

*When building locally, be sure you have the latest version of the `mlocati/php-extension-installer` image by running :*

```sh
docker pull mlocati/php-extension-installer
```

*otherwise the `COPY` instruction could use a previously downloaded, outdated version of the image stored in the local docker cache.*


### Installing specific versions of an extension

Simply append `-<version>` to the module name.
For example:

```sh
install-php-extensions xdebug-2.9.7
```

The script also support resolving *compatible* versions by prefixing the version with a caret (`^`).
For example:

```sh
# Install the most recent xdebug 2.x version (for example 2.9.8)
install-php-extensions xdebug-^2
# Install the most recent xdebug 2.8.x version (for example 2.8.1)
install-php-extensions xdebug-^2.8
```

Pre-release versions extensions available on `PECL` can be setup by suffixing the extension's name with its state i.e `alpha`, `beta`, `rc`, `preview`, `devel` or `snapshot`.
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

### Installing composer

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

## Supported PHP extensions

<!-- START OF EXTENSIONS TABLE -->
<!-- ########################################################### -->
<!-- #                                                         # -->
<!-- #  DO NOT EDIT THIS TABLE: IT IS GENERATED AUTOMATICALLY  # -->
<!-- #                                                         # -->
<!-- #  EDIT THE data/supported-extensions FILE INSTEAD        # -->
<!-- #                                                         # -->
<!-- ########################################################### -->
| Extension | PHP 5.5 | PHP 5.6 | PHP 7.0 | PHP 7.1 | PHP 7.2 | PHP 7.3 | PHP 7.4 | PHP 8.0 | PHP 8.1 |
|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| amqp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| apcu | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| apcu_bc |  |  | &check; | &check; | &check; | &check; | &check; |  |  |
| ast |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| bcmath | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| bz2 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| calendar | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| cmark |  |  | &check; | &check; | &check; | &check; | &check; |  |  |
| csv |  |  |  |  |  | &check; | &check; | &check; | &check; |
| dba | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| decimal |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ds |  |  | &check; | &check; | &check; | &check; | &check; | &check; |  |
| enchant[*](#special-requirements-for-enchant) | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ev | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| excimer |  |  |  | &check; | &check; | &check; | &check; | &check; | &check; |
| exif | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ffi |  |  |  |  |  |  | &check; | &check; | &check; |
| gd | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gearman | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| geoip | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| geospatial | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gettext | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gmagick | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gmp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| gnupg | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| grpc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| http | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| igbinary | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| imagick | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| imap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| inotify | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| interbase | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| intl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ioncube_loader | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| jsmin | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| json_post | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ldap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| lzf | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mailparse | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| maxminddb |  |  |  |  | &check; | &check; | &check; | &check; | &check; |
| mcrypt | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| memcache | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| memcached | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mongo | &check; | &check; |  |  |  |  |  |  |  |
| mongodb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mosquitto | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| msgpack | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| mssql | &check; | &check; |  |  |  |  |  |  |  |
| mysql | &check; | &check; |  |  |  |  |  |  |  |
| mysqli | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| oauth | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| oci8 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| odbc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| opcache | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| opencensus |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| parallel[*](#special-requirements-for-parallel) |  |  |  | &check; | &check; | &check; | &check; |  |  |
| pcntl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pcov |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_dblib | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| pdo_firebird | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_mysql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_oci |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_odbc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_pgsql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pdo_sqlsrv[*](#special-requirements-for-pdo_sqlsrv) |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pgsql | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| propro | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| protobuf | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| pspell | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| pthreads[*](#special-requirements-for-pthreads) | &check; | &check; | &check; |  |  |  |  |  |  |
| raphf | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| rdkafka | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| recode | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| redis | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| seaslog | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| shmop | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| smbclient | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snmp | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| snuffleupagus |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| soap | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sockets | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| solr | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sqlsrv[*](#special-requirements-for-sqlsrv) |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| ssh2 | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| swoole | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |
| sybase_ct | &check; | &check; |  |  |  |  |  |  |  |
| sysvmsg | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvsem | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| sysvshm | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| tensor |  |  |  |  | &check; | &check; | &check; | &check; |  |
| tidy | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| timezonedb | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| uopz | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| uploadprogress | &check; | &check; | &check; | &check; | &check; | &check; | &check; |  |  |
| uuid | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| vips[*](#special-requirements-for-vips) |  |  | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| wddx | &check; | &check; | &check; | &check; | &check; | &check; |  |  |  |
| xdebug | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xhprof | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xlswriter |  |  | &check; | &check; | &check; | &check; | &check; | &check; |  |
| xmldiff | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xmlrpc | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| xsl | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| yaml | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| yar | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zip | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zookeeper | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |
| zstd | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; | &check; |

*Number of supported extensions: 108*
<!-- END OF EXTENSIONS TABLE -->

PS: the pre-installed PHP extensions are excluded from this list.
You can list them with the following command (change `php:7.2-cli` to reflect the PHP version you are interested in):

```
$ docker run --rm php:7.2-cli php -m
[PHP Modules]
Core
ctype
curl
date
dom
fileinfo
filter
ftp
hash
iconv
json
libxml
mbstring
mysqlnd
openssl
pcre
PDO
pdo_sqlite
Phar
posix
readline
Reflection
session
SimpleXML
sodium
SPL
sqlite3
standard
tokenizer
xml
xmlreader
xmlwriter
zlib

[Zend Modules]
```

## Configuration

The compilation of some extensions may be fine-tuned to better fit your needs by using environment variables:

| Extension | Environment variable | Description |
|---|---|---|
| lzf | IPE_LZF_BETTERCOMPRESSION=1 | By default `install-php-extensions` compiles the `lzf` extension to prefer speed over size; you can use this environment variable to compile it preferring size over speed  |

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
| <a name="special-requirements-for-enchant"></a>enchant | &bull; Not available in `7.2-alpine3.12` docker images<br />&bull; Not available in `7.3-alpine3.12` docker images<br />&bull; Not available in `7.3-alpine3.13` docker images<br />&bull; Not available in `7.4-alpine3.12` docker images<br />&bull; Not available in `7.4-alpine3.13` docker images |
| <a name="special-requirements-for-parallel"></a>parallel | Requires images with PHP compiled with thread-safety enabled (`zts`). |
| <a name="special-requirements-for-pdo_sqlsrv"></a>pdo_sqlsrv | &bull; Not available in `7.0-alpine3.7` docker images<br />&bull; Not available in `7.1-alpine3.7` docker images<br />&bull; Not available in `7.1-alpine3.8` docker images<br />&bull; Not available in `7.2-alpine3.7` docker images<br />&bull; Not available in `7.2-alpine3.8` docker images<br />&bull; Not available in `7.3-alpine3.8` docker images |
| <a name="special-requirements-for-pthreads"></a>pthreads | Requires images with PHP compiled with thread-safety enabled (`zts`). |
| <a name="special-requirements-for-sqlsrv"></a>sqlsrv | &bull; Not available in `7.0-alpine3.7` docker images<br />&bull; Not available in `7.1-alpine3.7` docker images<br />&bull; Not available in `7.1-alpine3.8` docker images<br />&bull; Not available in `7.1-alpine3.9` docker images<br />&bull; Not available in `7.1-alpine3.10` docker images<br />&bull; Not available in `7.2-alpine3.7` docker images<br />&bull; Not available in `7.2-alpine3.8` docker images<br />&bull; Not available in `7.3-alpine3.8` docker images |
| <a name="special-requirements-for-vips"></a>vips | &bull; Not available in `7.0-alpine3.7` docker images<br />&bull; Not available in `7.1-alpine3.7` docker images<br />&bull; Not available in `7.2-alpine3.7` docker images<br />&bull; Not available in `7.1-alpine3.8` docker images<br />&bull; Not available in `7.2-alpine3.8` docker images<br />&bull; Not available in `7.3-alpine3.8` docker images<br />&bull; Not available in `7.1-alpine3.9` docker images<br />&bull; Not available in `7.2-alpine3.9` docker images<br />&bull; Not available in `7.3-alpine3.9` docker images<br />&bull; Not available in `7.0-jessie` docker images<br />&bull; Not available in `7.1-jessie` docker images<br />&bull; Not available in `7.2-jessie` docker images |
<!-- END OF SPECIAL REQUIREMENTS -->

## Tests

When submitting a pull request, a [GitHub Action](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/test-extensions.yml) is executed to check if affected PHP extensions actually work (see below).

Furthermore, we also check that new versions of extensions in the PECL repository will still work.
This is done on a scheduled basis with another [GitHub Action](https://github.com/mlocati/docker-php-extension-installer/blob/master/.github/workflows/test-recent-extensions.yml).  
In case of failure, a message is sent to a [Telegram Channel](https://t.me/docker_php_extension_installer).  
Feel free to subscribe to it to receive failure notifications.

## How to contribute

### Formatting code

Before submitting any pull request, be sure to execute the `lint` script in the `scripts` directory (or `lint.bat` on Windows).

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

It's a rather tedious taks, so I developed a project that lets you easily check those constraints: you can find it at https://mlocati.github.io/pecl-info ([here](https://github.com/mlocati/pecl-info) you can find its source code).

## For the maintainers

See the [`MAINTAINERS.md`](https://github.com/mlocati/docker-php-extension-installer/blob/master/MAINTAINERS.md) file.

## Do you want to really say thank you?

You can offer me a [monthly coffee](https://github.com/sponsors/mlocati) or a [one-time coffee](https://paypal.me/mlocati) :wink:
