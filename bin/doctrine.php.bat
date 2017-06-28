@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../Packages/Libraries/doctrine/orm/bin/doctrine.php
php "%BIN_TARGET%" %*
