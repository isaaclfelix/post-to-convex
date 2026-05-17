@echo off
setlocal
php "%~dp0..\vendor\bin\phpcbf" %*
exit /b %ERRORLEVEL%
