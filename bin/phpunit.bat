@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../Packages/Libraries/phpunit/phpunit/phpunit
php "%BIN_TARGET%" %*
