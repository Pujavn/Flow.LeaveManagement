@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../Packages/Libraries/doctrine/orm/bin/doctrine
php "%BIN_TARGET%" %*
