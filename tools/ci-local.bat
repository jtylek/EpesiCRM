@echo off
REM Local equivalent of .github\workflows\ci.yml, which is disabled on GitHub
REM (no Actions minutes on this repo) - see AI-shared\ci-workflow.md for why.
REM Run from anywhere; paths below are relative to the repo root.

setlocal enabledelayedexpansion

set "PHP=C:\xampp82\php\php.exe"
set "ROOT=%~dp0.."
cd /d "%ROOT%"

if not exist "%PHP%" (
  echo ERROR: %PHP% not found - the bare "php" on PATH resolves to an unrelated
  echo XAMPP 7.4 install on this machine ^(see CLAUDE.md, Environment quirks^).
  exit /b 1
)

echo ============================================================
echo  CI (local)  -  lint / phpstan / rector / console list
echo ============================================================

set "LINT_FAIL=0"
set "PHPSTAN_FAIL=0"
set "LINT_TMP=%TEMP%\ci-local-lint-%RANDOM%.tmp"

echo.
echo --- [1/4] php -l (first-party PHP: include, modules, admin, console, root) ---
for %%D in (include modules admin console) do (
  if exist "%%D" (
    for /r "%%D" %%F in (*.php) do (
      set "F=%%F"
      echo(!F!| findstr /i /c:"\vendor\" /c:"\Libs\RoundCube\RC\" /c:"\Base\Theme\smarty\" /c:"\Tests\" >nul
      if errorlevel 1 (
        "%PHP%" -d error_reporting=E_ALL -l "!F!" >"%LINT_TMP%" 2>&1
        if errorlevel 1 (
          echo   FAIL: !F!
          type "%LINT_TMP%"
          set "LINT_FAIL=1"
        )
      )
    )
  )
)
for %%F in (*.php) do (
  "%PHP%" -d error_reporting=E_ALL -l "%%F" >"%LINT_TMP%" 2>&1
  if errorlevel 1 (
    echo   FAIL: %%F
    type "%LINT_TMP%"
    set "LINT_FAIL=1"
  )
)
del "%LINT_TMP%" >nul 2>&1
if "!LINT_FAIL!"=="0" (echo   OK) else (echo   ** lint errors found above **)

echo.
echo --- [2/4] PHPStan (level 2, baselined - fails only on NEW findings) ---
if exist "tools\vendor\bin\phpstan.bat" (
  call tools\vendor\bin\phpstan.bat analyse -c phpstan.neon
  if errorlevel 1 set "PHPSTAN_FAIL=1"
) else (
  echo   SKIPPED - run "composer install -d tools" first ^(tools\vendor not installed^).
)

echo.
echo --- [3/4] Rector PHP 8.2 dry-run (advisory - never fails this script) ---
if exist "tools\vendor\bin\rector.bat" (
  call tools\vendor\bin\rector.bat process --dry-run --config rector-php82.php
) else (
  echo   SKIPPED - run "composer install -d tools" first ^(tools\vendor not installed^).
)

echo.
echo --- [4/4] console.php commands (docs check - compare by eye against CLAUDE.md) ---
"%PHP%" console.php list --no-ansi

echo.
echo ============================================================
if "!LINT_FAIL!"=="1" (
  echo RESULT: lint FAILED
) else if "!PHPSTAN_FAIL!"=="1" (
  echo RESULT: phpstan FAILED
) else (
  echo RESULT: lint + phpstan OK  ^(rector and the console list above are advisory - review by eye^)
)
echo ============================================================

set "EXIT=0"
if "!LINT_FAIL!"=="1" set "EXIT=1"
if "!PHPSTAN_FAIL!"=="1" set "EXIT=1"
exit /b %EXIT%
