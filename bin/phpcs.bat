@echo off
setlocal
php "%~dp0..\vendor\bin\phpcs" %*
exit /b %ERRORLEVEL%
