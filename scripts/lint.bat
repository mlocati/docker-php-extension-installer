@echo off
setlocal

docker --version >NUL 2>NUL
if errorlevel 1 goto :no-docker

cd /d "%~dp0.."
if errorlevel 1 goto err
set SRC_DIR=%CD%

docker build -t docker-php-extension-installer-shfmt:latest -f scripts\Dockerfile-shfmt -q .
if errorlevel 1 goto :err

call :fix install-php-extensions
call :fix scripts/common
call :fix scripts/lint
call :fix scripts/travisci-test-extensions
call :fix scripts/travisci-update-readme
call :fix scripts/update-readme
goto :eof

:no-docker
echo Docker is not installed, or it's not running >&2
goto :eof

:err
echo ERROR! >&2
goto :eof

:fix
echo|set /p="Fixing %1... "
docker run --rm -v "%SRC_DIR%:/src" -w /src docker-php-extension-installer-shfmt:latest shfmt -s -ln posix -i 0 -ci -kp -w %1
if not errorlevel 1 echo done.
exit /b 0
