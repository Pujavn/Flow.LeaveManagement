<?php
if (FLOW_PATH_ROOT !== '/var/www/Flow.LeaveManagement/' || !file_exists('/var/www/Flow.LeaveManagement/Data/Temporary/Development/Configuration/DevelopmentConfigurations.php')) {
	@unlink(__FILE__);
	return array();
}
return require '/var/www/Flow.LeaveManagement/Data/Temporary/Development/Configuration/DevelopmentConfigurations.php';