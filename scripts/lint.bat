@echo off
setlocal

cd /D "%~dp0.."
if errorlevel 1 (
    echo ERROR! >&2
    exit /B 1
)

set SRC_DIR=%CD%
set rc=0

echo # Linting shell scripts
docker --version >NUL 2>NUL
if errorlevel 1 (
    echo Docker is not installed, or it's not running >&2
    set rc=1
) else (
    docker run --rm -v "%SRC_DIR%:/src" -w /src --entrypoint /src/scripts/invoke-shfmt mvdan/shfmt:v3.10.0-alpine fix
    if errorlevel 1 (
        echo ERROR! >&2
        set rc=1
    )
)

echo # Linting PHP files
call composer --version >NUL 2>NUL
if errorlevel 1 (
    echo Composer is not installed. >&2
    set rc=1
) else (
    if not exist .\vendor\autoload.php (
        echo Composer dependencies are not installed. >&2
        set rc=1
    ) else (
        call composer run-script lint
        if errorlevel 1 (
            echo ERROR! >&2
            set rc=1
        )
    )
)

exit /B %rc%
