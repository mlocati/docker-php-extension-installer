@echo off
setlocal

docker --version >NUL 2>NUL
if errorlevel 1 goto :no-docker

cd /d "%~dp0.."
if errorlevel 1 goto err
set SRC_DIR=%CD%

docker build -t docker-php-extension-installer-shfmt:latest -f scripts\Dockerfile-shfmt -q .
if errorlevel 1 goto :err

docker run --rm -v "%SRC_DIR%:/src" -w /src docker-php-extension-installer-shfmt:latest ./scripts/invoke-shfmt fix
if errorlevel 1 goto :err
goto :eof

:no-docker
echo Docker is not installed, or it's not running >&2
goto :eof

:err
echo ERROR! >&2
goto :eof
