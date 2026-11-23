@echo off
setlocal
set "SMARTFUEL_PHP="
if exist "C:\xampp\php\php.exe" set "SMARTFUEL_PHP=C:\xampp\php\php.exe"
if not defined SMARTFUEL_PHP if exist "C:\abc\php\php.exe" set "SMARTFUEL_PHP=C:\abc\php\php.exe"
if not defined SMARTFUEL_PHP set /p "SMARTFUEL_PHP=Enter the full path to your XAMPP php.exe: "
"%SMARTFUEL_PHP%" "%~dp0tools\validate.php"
if errorlevel 1 (
    echo Validation needs attention. Read the messages above.
) else (
    echo PHP syntax and required extensions passed.
)
pause
