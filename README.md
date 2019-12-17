[![TravisCI Build Status](https://travis-ci.org/mlocati/docker-php-extension-installer.svg?branch=master)](https://travis-ci.org/mlocati/docker-php-extension-installer)

# Easy installation of PHP extensions in official PHP Docker images

This repository contains a script that can be used to easily install a PHP extension inside the [official PHP Docker images](https://hub.docker.com/_/php/).

The script works both for Alpine and Debian Linux.


## Usage

Here's a sample Dockerfile that installs the GD and xdedub extensions inside a docker image:

```
FROM php:7.2-cli

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions gd xdebug
```

Installation via COPY --from

```
FROM php:7.2-cli

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/

RUN install-php-extensions gd xdebug
```

`install-php-extensions` will install all the required APT/APK packages; at the end of the script execution, the no-more needed packages will be removed.

## Supported PHP extensions

<!-- START OF EXTENSIONS TABLE -->
<!-- ########################################################### -->
<!-- #                                                         # -->
<!-- #  DO NOT EDIT THIS TABLE: IT IS GENERATED AUTOMATICALLY  # -->
<!-- #                                                         # -->
<!-- #  EDIT THE data/supported-extensions FILE INSTEAD        # -->
<!-- #                                                         # -->
<!-- ########################################################### -->
| Extension | PHP 5.6 | PHP 7.0 | PHP 7.1 | PHP 7.2 | PHP 7.3 | PHP 7.4 |
|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| amqp | V | V | V | V | V | V |
| apcu | V | V | V | V | V | V |
| bcmath | V | V | V | V | V | V |
| bz2 | V | V | V | V | V | V |
| calendar | V | V | V | V | V | V |
| cmark |  | V | V | V | V | V |
| dba | V | V | V | V | V | V |
| enchant | V | V | V | V | V | V |
| exif | V | V | V | V | V | V |
| gd | V | V | V | V | V | V |
| gettext | V | V | V | V | V | V |
| gmp | V | V | V | V | V | V |
| grpc | V | V | V | V | V | V |
| igbinary | V | V | V | V | V | V |
| imagick | V | V | V | V | V | V |
| imap | V | V | V | V | V | V |
| interbase | V | V | V | V | V |  |
| intl | V | V | V | V | V | V |
| ldap | V | V | V | V | V | V |
| mcrypt | V | V | V | V | V | V |
| memcache | V |  |  |  |  |  |
| memcached | V | V | V | V | V | V |
| mongo | V |  |  |  |  |  |
| mongodb | V | V | V | V | V | V |
| msgpack | V | V | V | V | V | V |
| mssql | V |  |  |  |  |  |
| mysql | V |  |  |  |  |  |
| mysqli | V | V | V | V | V | V |
| odbc | V | V | V | V | V | V |
| opcache | V | V | V | V | V | V |
| opencensus |  | V | V | V | V | V |
| parallel |  |  | V | V | V | V |
| pcntl | V | V | V | V | V | V |
| pcov |  | V | V | V | V | V |
| pdo_dblib | V | V | V | V | V | V |
| pdo_firebird | V | V | V | V | V | V |
| pdo_mysql | V | V | V | V | V | V |
| pdo_odbc | V | V | V | V | V | V |
| pdo_pgsql | V | V | V | V | V | V |
| pdo_sqlsrv |  | V | V | V | V | V |
| pgsql | V | V | V | V | V | V |
| protobuf | V | V | V | V | V | V |
| pspell | V | V | V | V | V | V |
| pthreads | V | V |  |  |  |  |
| recode | V | V | V | V | V |  |
| redis | V | V | V | V | V | V |
| shmop | V | V | V | V | V | V |
| snmp | V | V | V | V | V | V |
| soap | V | V | V | V | V | V |
| sockets | V | V | V | V | V | V |
| solr | V | V | V | V | V | V |
| sqlsrv |  | V | V | V | V | V |
| ssh2 | V | V | V | V | V | V |
| sybase_ct | V |  |  |  |  |  |
| sysvmsg | V | V | V | V | V | V |
| sysvsem | V | V | V | V | V | V |
| sysvshm | V | V | V | V | V | V |
| tidy | V | V | V | V | V | V |
| timezonedb | V | V | V | V | V | V |
| uopz | V | V | V | V | V | V |
| uuid | V | V | V | V | V | V |
| wddx | V | V | V | V | V |  |
| xdebug | V | V | V | V | V | V |
| xmlrpc | V | V | V | V | V | V |
| xsl | V | V | V | V | V | V |
| yaml | V | V | V | V | V | V |
| zip | V | V | V | V | V | V |

*Number of supported extensions: 67*
<!-- END OF EXTENSIONS TABLE -->

PS: the pre-installed PHP extensions are excluded from this list.
You can list them with the following command (change `php:7.2-cli` to reflect the PHP version you are interested in):

```sh
docker run --rm -it php:7.2-cli php -m
```

## Special requirements

Some extension has special requirements:

<!-- START OF SPECIAL REQUIREMENTS -->
<!-- ########################################################### -->
<!-- #                                                         # -->
<!-- #  DO NOT EDIT THIS TABLE: IT IS GENERATED AUTOMATICALLY  # -->
<!-- #                                                         # -->
<!-- #  EDIT THE data/special-requirements FILE INSTEAD        # -->
<!-- #                                                         # -->
<!-- ########################################################### -->
| Extension | Requirements |
|:---:|:---:|
| parallel | Requires images with PHP compiled with thread-safety enabled (`zts`). |
| pthreads | Requires images with PHP compiled with thread-safety enabled (`zts`). |
<!-- END OF SPECIAL REQUIREMENTS -->


## How to contribute

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

If your pull request contains multiple commits, we'll check the "Test:" message of every commit.
If you want to stop parsing next commits, add `-STOP-` in the "Test:" line, for example:

```
Improve the GD and ZIP extensions

Test: gd, zip, -STOP-
```

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/43) for an example.


## Do you want to really say thank you?

You can offer me a [monthly coffee](https://github.com/sponsors/mlocati) or a [one-time coffee](https://paypal.me/mlocati) :wink:
