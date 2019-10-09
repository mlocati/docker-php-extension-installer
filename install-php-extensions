#!/bin/sh

# This script wraps docker-php-ext-install, properly configuring the system.
#
# Copyright (c) Michele Locati, 2018
#
# Source: https://github.com/mlocati/docker-php-extension-installer
#
# License: MIT - see https://github.com/mlocati/docker-php-extension-installer/blob/master/LICENSE

# Let's set a sane environment
set -o errexit
set -o nounset

# Reset the Internal Field Separator
resetIFS () {
	IFS='	 
'
}

# Get the PHP Major-Minor version as an integer value, in format MMmm (example: 506 for PHP 5.6.15)
#
# Output:
#   The PHP numeric Major-Minor version
getPHPMajorMinor () {
	php -r '$v = explode(".", PHP_VERSION); echo $v[0] * 100 + $v[1];'
}

# Get the normalized list of already installed PHP modules
#
# Output:
#   Space-separated list of module handles
getPHPInstalledModules () {
	getPHPInstalledModules_result=''
	IFS='
'
	for getPHPInstalledModules_module in $(php -m); do
		getPHPInstalledModules_moduleNormalized=''
		case "${getPHPInstalledModules_module}" in
			\[PHP\ Modules\])
				;;
			\[Zend\ Modules\])
				break
				;;
			Core|PDO|PDO_*|Phar|Reflection|SimpleXML|SPL|SQLite|Xdebug)
				getPHPInstalledModules_moduleNormalized=$(LC_CTYPE=C printf '%s' "${getPHPInstalledModules_module}" | tr '[:upper:]' '[:lower:]')
				;;
			Zend\ OPcache)
				getPHPInstalledModules_moduleNormalized='opcache'
				;;
			*\ *|A*|*B*|*C*|*D*|*E*|*F*|*G*|*H*|*I*|*J*|*K*|*L*|*M*|*N*|*O*|*P*|*Q*|*R*|*S*|*T*|*U*|*V*|*W*|*X*|*Y*|*Z*)
				printf '### WARNING Unrecognized module name: %s ###\n' "${getPHPInstalledModules_module}" >&2
				;;
			*)
				getPHPInstalledModules_moduleNormalized="${getPHPInstalledModules_module}"
				;;
		esac
		if test -n "${getPHPInstalledModules_moduleNormalized}"; then
			if ! stringInList "${getPHPInstalledModules_moduleNormalized}" "${getPHPInstalledModules_result}"; then
				getPHPInstalledModules_result="${getPHPInstalledModules_result} ${getPHPInstalledModules_moduleNormalized}"
			fi
		fi
	done
	resetIFS
	printf '%s' "${getPHPInstalledModules_result}"
}

# Get the handles of the modules to be installed
#
# Arguments:
#   $@: all module handles
#
# Set:
#   DO_APT_REMOVE
#   PHP_MODULES_TO_INSTALL
#
# Output:
#   Nothing
getModulesToInstall () {
	getModulesToInstall_alreadyInstalled="$(getPHPInstalledModules)"
	getModulesToInstall_endArgs=''
	DO_APT_REMOVE=''
	PHP_MODULES_TO_INSTALL=''
	while :; do
		if test $# -lt 1; then
			break
		fi
		getModulesToInstall_skip=''
		if test -z "${getModulesToInstall_endArgs}"; then
			case "${1}" in
				--cleanup)
					getModulesToInstall_skip='y'
					DO_APT_REMOVE='y'
					;;
				--)
					getModulesToInstall_skip='y'
					getModulesToInstall_endArgs='y'
					;;
				-*)
					printf 'Unrecognized option: %s\n' "${1}" >&2
					exit 1
					;;
			esac
		fi
		if test -z "${getModulesToInstall_skip}"; then
			if stringInList "${1}" "${PHP_MODULES_TO_INSTALL}"; then
				printf '### WARNING Duplicated module name specified: %s ###\n' "${1}" >&2
			elif stringInList "${1}" "${getModulesToInstall_alreadyInstalled}"; then
				printf '### WARNING Module already installed: %s ###\n' "${1}" >&2
			else
				PHP_MODULES_TO_INSTALL="${PHP_MODULES_TO_INSTALL} ${1}"
			fi
		fi
		shift
	done
}

# Get the required APT packages for a specific PHP version and for the list of module handles
#
# Arguments:
#   $1: the numeric PHP Major-Minor version
#   $@: the PHP module handles
#
# Output:
#   Space-separated list of APT packages
getRequiredAptPackages () {
	getRequiredAptPackages_result=''
	getRequiredAptPackages_phpv=${1}
	while :; do
		if test $# -lt 2; then
			break
		fi
		shift
		case "${1}" in
			amqp)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} librabbitmq-dev libssh-dev"
				;;
			bz2)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libbz2-dev"
				;;
			cmark)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} cmake"
				;;
			enchant)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libenchant-dev"
				;;
			gd)
				if test $getRequiredAptPackages_phpv -lt 700; then
					getRequiredAptPackages_result="${getRequiredAptPackages_result} libvpx-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libfreetype6-dev"
				else
					getRequiredAptPackages_result="${getRequiredAptPackages_result} libwebp-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libfreetype6-dev"
				fi
				;;
			gmp)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libgmp-dev"
				;;
			imap)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libc-client-dev libkrb5-dev"
				;;
			interbase)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} firebird-dev libib-util"
				;;
			imagick)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libmagickwand-dev"
				;;
			intl)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libicu-dev"
				;;
			ldap)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libldap2-dev"
				;;
			memcache)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} zlib1g-dev"
				;;
			memcached)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libmemcached-dev zlib1g-dev"
				;;
			mcrypt)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libmcrypt-dev"
				;;
			mssql)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} freetds-dev"
				;;
			odbc)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} unixodbc-dev"
				;;
			pdo_dblib)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} freetds-dev"
				;;
			pdo_firebird)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} firebird-dev libib-util"
				;;
			pdo_pgsql)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libpq-dev"
				;;
			pdo_odbc)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} unixodbc-dev"
				;;
			pdo_sqlsrv)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} unixodbc-dev"
				;;
			pgsql)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libpq-dev"
				;;
			pspell)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libpspell-dev"
				;;
			recode)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} librecode-dev"
				;;
			ssh2)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libssh2-1-dev"
				;;
			snmp)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} snmp libsnmp-dev"
				;;
			soap)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libxml2-dev"
				;;
			solr)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libcurl4-gnutls-dev libxml2-dev"
				;;
			sqlsrv)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} unixodbc-dev"
				;;
			sybase_ct)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} freetds-dev"
				;;
			tidy)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libtidy-dev"
				;;
			uuid)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} uuid-dev"
				;;
			wddx)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libxml2-dev"
				;;
			xmlrpc)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libxml2-dev"
				;;
			xsl)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libxslt-dev"
				;;
			yaml)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} libyaml-dev"
				;;
			zip)
				getRequiredAptPackages_result="${getRequiredAptPackages_result} cmake zlib1g-dev libbz2-dev libmbedtls-dev"
				;;
		esac
	done
	printf '%s' "${getRequiredAptPackages_result}"
}

# Get the newly installed APT packages that will be no more needed after the installation of PHP extensions
#
# Arguments:
#   $1: the list of APT packages that will be installed
#
# Output:
#   Space-separated list of APT packages to be removed
getAptPackagesToRemove () {
	getAptPackagesToRemove_inNewPackages=''
	getAptPackagesToRemove_result=''
	IFS='
'
	for getAptPackagesToRemove_aptLine in $(DEBIAN_FRONTEND=noninteractive apt-get install -sy $@); do
		if test -z "${getAptPackagesToRemove_inNewPackages}"; then
			if test "${getAptPackagesToRemove_aptLine}" = 'The following NEW packages will be installed:'; then
				getAptPackagesToRemove_inNewPackages='y'
				resetIFS
			fi
		elif test "${getAptPackagesToRemove_aptLine}" = "${getAptPackagesToRemove_aptLine# }"; then
			getAptPackagesToRemove_inNewPackages=''
		else
			for getAptPackagesToRemove_newPackage in ${getAptPackagesToRemove_aptLine}; do
				case "${getAptPackagesToRemove_newPackage}" in
					*-dev|cmake|cmake-data)
						getAptPackagesToRemove_result="${getAptPackagesToRemove_result} ${getAptPackagesToRemove_newPackage}"
						;;
				esac
			done
		fi
	done
	resetIFS
	printf '%s' "${getAptPackagesToRemove_result}"
}

# Install a bundled PHP module given its handle
#
# Arguments:
#   $1: the numeric PHP Major-Minor version
#   $2: the handle of the PHP module
#
# Set:
#   UNNEEDED_APT_PACKAGE_LINKS
#
# Output:
#   Nothing
installBundledModule () {
	printf '### INSTALLING BUNDLED MODULE %s ###\n' "${2}"
	case "${2}" in
		gd)
			if test $1 -lt 700; then
				docker-php-ext-configure gd --with-gd --with-vpx-dir --with-jpeg-dir --with-png-dir --with-zlib-dir --with-xpm-dir --with-freetype-dir --enable-gd-native-ttf
			elif test $1 -lt 702; then
				docker-php-ext-configure gd --with-gd --with-webp-dir --with-jpeg-dir --with-png-dir --with-zlib-dir --with-xpm-dir --with-freetype-dir --enable-gd-native-ttf
			else
				docker-php-ext-configure gd --with-gd --with-webp-dir --with-jpeg-dir --with-png-dir --with-zlib-dir --with-xpm-dir --with-freetype-dir
			fi
			;;
		gmp)
			case "$1" in
				506)
					if ! test -f /usr/include/gmp.h; then
						ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h
						UNNEEDED_APT_PACKAGE_LINKS="${UNNEEDED_APT_PACKAGE_LINKS} /usr/include/gmp.h"
					fi
					;;
			esac
			;;
		imap)
			docker-php-ext-configure imap --with-kerberos --with-imap-ssl
			;;
		ldap)
			docker-php-ext-configure ldap --with-libdir=lib/$(gcc -dumpmachine)
			;;
		mssql|pdo_dblib)
			case "$1" in
				506|700|701|702|703)
					if ! test -f /usr/lib/libsybdb.so; then
						ln -s /usr/lib/x86_64-linux-gnu/libsybdb.so /usr/lib/libsybdb.so
						UNNEEDED_APT_PACKAGE_LINKS="${UNNEEDED_APT_PACKAGE_LINKS} /usr/lib/libsybdb.so"
					fi
					;;
			esac
			;;
		odbc)
			case "$1" in
				506|700|701|702|703)
					docker-php-source extract
					cd /usr/src/php/ext/odbc
					phpize
					sed -ri 's@^ *test +"\$PHP_.*" *= *"no" *&& *PHP_.*=yes *$@#&@g' configure
					./configure --with-unixODBC=shared,/usr
					cd -
					;;
			esac
			;;
		pdo_odbc)
			docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr
			;;
		sybase_ct)
			docker-php-ext-configure sybase_ct --with-sybase-ct=/usr
			;;
		zip)
			libZipSrc="$(getPackageSource https://libzip.org/download/libzip-1.5.2.tar.gz)"
			mkdir "$libZipSrc/build"
			cd "$libZipSrc/build"
			cmake ..
			make install
			cd -
			docker-php-ext-configure zip --with-libzip
			;;
	esac
	docker-php-ext-install -j$(nproc) "${2}"
}

# Fetch a tar.gz file, extract it and returns the path of the extracted folder.
#
# Arguments:
#   $1: the URL of the file to be downloaded
#
# Output:
#   The path of the extracted directory
getPackageSource () {
	mkdir -p /tmp/src
	getPackageSource_tempFile=$(mktemp -p /tmp/src)
	curl -L -s -S -o "${getPackageSource_tempFile}" "$1"
	getPackageSource_tempDir=$(mktemp -p /tmp/src -d)
	cd "${getPackageSource_tempDir}"
	tar -xzf "${getPackageSource_tempFile}"
	cd - >/dev/null
	unlink "${getPackageSource_tempFile}"
	getPackageSource_outDir=''
	for getPackageSource_i in $(ls "${getPackageSource_tempDir}"); do
		if test -n "${getPackageSource_outDir}" || test -f "${getPackageSource_tempDir}/${getPackageSource_i}"; then
			getPackageSource_outDir=''
			break
		fi
		getPackageSource_outDir="${getPackageSource_tempDir}/${getPackageSource_i}"
	done
	if test -n "${getPackageSource_outDir}"; then
		printf '%s' "${getPackageSource_outDir}"
	else
		printf '%s' "${getPackageSource_tempDir}"
	fi
}

# Install a PHP module given its handle from source code
#
# Arguments:
#   $1: the handle of the PHP module
#   $2: the URL of the module source code
#   $3: the options of the configure command
#   $4: the value of CFLAGS
installModuleFromSource () {
	printf '### INSTALLING MODULE %s FROM SOURCE CODE ###\n' "${1}"
	installModuleFromSource_dir="$(getPackageSource "${2}")"
	cd "${installModuleFromSource_dir}"
	phpize
	./configure ${3} CFLAGS="${4:-}"
	make -j$(nproc) install
	cd --
	docker-php-ext-enable "${1}"
}

# Install a PECL PHP module given its handle
#
# Arguments:
#   $1: the numeric PHP Major-Minor version
#   $2: the handle of the PHP module
installPECLModule () {
	printf '### INSTALLING PECL MODULE %s ###\n' "${2}"
	installPECLModule_actual="${2}"
	installPECLModule_stdin=''
	case "${2}" in
		mcrypt)
			if test $1 -ge 702; then
				installPECLModule_stdin='autodetect'
			fi
			;;
		memcached)
			if test $1 -lt 700; then
				installPECLModule_actual="${2}-2.2.0"
			fi
			;;
		pcov)
			if test $1 -lt 701; then
				installPECLModule_actual="${2}-0.9.0"
			fi
			;;
		pdo_sqlsrv | sqlsrv)
			# https://docs.microsoft.com/it-it/sql/connect/php/system-requirements-for-the-php-sql-driver?view=sql-server-2017
			if test $1 -le 700; then
				installPECLModule_actual="${2}-5.3.0"
			fi
			;;
		pthreads)
			if test $1 -lt 700; then
				installPECLModule_actual="${2}-2.0.10"
			fi
			;;
		ssh2)
			if test $1 -le 506; then
				installPECLModule_stdin='autodetect'
				installPECLModule_actual="${2}-0.13"
			else
				installPECLModule_stdin='autodetect'
				# see https://bugs.php.net/bug.php?id=78560
				installPECLModule_actual='https://pecl.php.net/get/ssh2'
			fi
			;;
		xdebug)
			if test $1 -lt 501; then
				installPECLModule_actual="${2}-2.0.5"
			elif test $1 -lt 504; then
				installPECLModule_actual="${2}-2.2.7"
			elif test $1 -lt 505; then
				installPECLModule_actual="${2}-2.4.1"
			elif test $1 -lt 700; then
				installPECLModule_actual="${2}-2.5.5"
			fi
			;;
		uopz)
			if test $1 -lt 700; then
				installPECLModule_actual="${2}-2.0.7"
			elif test $1 -lt 701; then
				installPECLModule_actual="${2}-5.0.2"
			fi
			;;
		yaml)
			if test $1 -lt 700; then
				installPECLModule_actual="${2}-1.3.1"
			fi
			;;
	esac
	if test "${2}" != "${installPECLModule_actual}"; then
		printf '  (installing version %s)\n' "${installPECLModule_actual}"
	fi
	if test -z "$installPECLModule_stdin"; then
		pecl install "${installPECLModule_actual}"
	else
		printf '%s\n' "$installPECLModule_stdin" | pecl install "${installPECLModule_actual}" 
	fi
	docker-php-ext-enable "${2}"
}

# Check if a string is in a list of space-separated string
#
# Arguments:
#   $1: the string to be checked
#   $2: the string list
#
# Return:
#   0 (true): if the string is in the list
#   1 (false): if the string is not in the list
stringInList () {
	for stringInList_listItem in ${2}; do
		if test "${1}" = "${stringInList_listItem}"; then
			return 0
		fi
	done
	return 1
}

resetIFS
PHP_MAJMIN_VERSION=$(getPHPMajorMinor)
case "${PHP_MAJMIN_VERSION}" in
	506|700|701|702|703)
		;;
	*)
		printf "### ERROR: Unsupported PHP version: %s.%s ###\n" $(( PHP_MAJMIN_VERSION / 100 )) $(( PHP_MAJMIN_VERSION % 100 ))
esac
UNNEEDED_APT_PACKAGES=''
UNNEEDED_APT_PACKAGE_LINKS=''
getModulesToInstall "$@"
if test -n "${PHP_MODULES_TO_INSTALL}"; then
	REQUIRED_APT_PACKAGES=$(getRequiredAptPackages ${PHP_MAJMIN_VERSION} ${PHP_MODULES_TO_INSTALL})
	if test -n "${REQUIRED_APT_PACKAGES}"; then
		printf '### INSTALLING REQUIRED APT PACKAGES ###\n'
		DEBIAN_FRONTEND=noninteractive apt-get update -y
		if test -n "${DO_APT_REMOVE}"; then
			UNNEEDED_APT_PACKAGES=$(getAptPackagesToRemove ${REQUIRED_APT_PACKAGES})
		fi
		DEBIAN_FRONTEND=noninteractive apt-get install -y ${REQUIRED_APT_PACKAGES}
	fi
	docker-php-source extract
	BUNDLED_MODULES="$(find /usr/src/php/ext -mindepth 2 -maxdepth 2 -type f -name 'config.m4' | xargs -n1 dirname | xargs -n1 basename | xargs)"
	for PHP_MODULE_TO_INSTALL in ${PHP_MODULES_TO_INSTALL}; do
		if stringInList "${PHP_MODULE_TO_INSTALL}" "${BUNDLED_MODULES}"; then
			installBundledModule ${PHP_MAJMIN_VERSION} "${PHP_MODULE_TO_INSTALL}"
		else
			MODULE_SOURCE=''
			MODULE_SOURCE_CONFIGOPTIONS=''
			MODULE_SOURCE_CFLAGS=''
			case "${PHP_MODULE_TO_INSTALL}" in
				cmark)
					MODULE_SOURCE=https://github.com/krakjoe/cmark/archive/v1.0.0.tar.gz
					cd "$(getPackageSource https://github.com/commonmark/cmark/archive/0.28.3.tar.gz)"
					make install
					cd -
					MODULE_SOURCE_CONFIGOPTIONS=--with-cmark
					;;
				igbinary)
					if test ${PHP_MAJMIN_VERSION} -lt 700; then
						MODULE_SOURCE="https://github.com/igbinary/igbinary/archive/2.0.8.tar.gz"
					else
						MODULE_SOURCE="https://github.com/igbinary/igbinary/archive/3.0.1.tar.gz"
					fi
					MODULE_SOURCE_CONFIGOPTIONS=--enable-igbinary
					MODULE_SOURCE_CFLAGS='-O2 -g'
					;;
			esac
			if test -n "${MODULE_SOURCE}"; then
				installModuleFromSource "${PHP_MODULE_TO_INSTALL}" "${MODULE_SOURCE}" "${MODULE_SOURCE_CONFIGOPTIONS}" "${MODULE_SOURCE_CFLAGS}"
			else
				installPECLModule ${PHP_MAJMIN_VERSION} "${PHP_MODULE_TO_INSTALL}"
			fi
		fi
	done
	if test -n "${DO_APT_REMOVE}"; then
		printf '### REMOVING NO LONGER REQUIRED PACKAGES ###\n'
		DEBIAN_FRONTEND=noninteractive apt autoremove -y
	fi
	if test -n "${UNNEEDED_APT_PACKAGES}"; then
		printf '### REMOVING UNNEEDED APT PACKAGES ###\n'
		if test -n "${UNNEEDED_APT_PACKAGE_LINKS}"; then
			for unneededAptPackageLink in ${UNNEEDED_APT_PACKAGE_LINKS}; do
				if test -L "${unneededAptPackageLink}"; then
					rm -f "${unneededAptPackageLink}"
				fi
			done
		fi
		DEBIAN_FRONTEND=noninteractive apt-get remove --purge -y ${UNNEEDED_APT_PACKAGES}
	fi
fi

docker-php-source delete
rm -rf /tmp/pear
rm -rf /var/lib/apt/lists/*
rm -rf /tmp/src
