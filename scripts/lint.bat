@echo off
setlocal

docker --version >NUL 2>NUL
if errorlevel 1 goto :no-docker

cd /d "%~dp0.."
if errorlevel 1 goto err
set SRC_DIR=%CD%

docker run --rm -v "%SRC_DIR%:/src" -w /src --entrypoint /src/scripts/invoke-shfmt mvdan/shfmt:latest fix
if errorlevel 1 goto :err
goto :eof

:no-docker
echo Docker is not installed, or it's not running >&2
goto :eof

:err
echo ERROR! >&2
goto :eof
