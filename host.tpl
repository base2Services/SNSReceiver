<?PHP

if ( stripos($groupName, "sdproduction") !== false ) {
  $memorytemplate = "sportsdata-memory";
} else {
	$memorytemplate = "generic-service";
}

$hostdata = 

"define host{\n" .
"        use                     linux-server\n" .
"        host_name               " . $instanceID . "\n" .
"	 hostgroups		 " . $groupName . "\n" .
"        alias                   " . $instanceID . "\n" .
"        address                 " . $instanceHostName . "\n" .
"	 contact_groups		 admins" . $contactgroups . "\n" .
"        }\n" .
"define service{\n" .
"        use                             generic-service\n" .
"        host_name                       " . $instanceID . "\n" .
"        service_description             SSH\n" .
"	 check_command			 check_ssh\n" .
"	 contact_groups		 admins" . $contactgroups .  "\n" .
"        }\n";

if ( stripos($groupName, "ImporterASGroup") === false ) {
	$hostdata .=
	"define service{\n" .
	"        use                             " . $memorytemplate . "\n" .
	"        host_name                       " . $instanceID . "\n" .
	"        service_description             CPU Load\n" .
	"        check_command                   check_nrpe!check_load\n" .
	"        contact_groups          admins" . $contactgroups .  "\n" .
	"        }\n";
} 

$hostdata .=
"define service{\n" .
"        use                             generic-service\n" .
"        host_name                       " . $instanceID . "\n" .
"        service_description             Memory\n" .
"        check_command                   check_nrpe!check_mem\n" .
"	 contact_groups		 admins" . $contactgroups .  "\n" .
"        }\n" .
"define service{\n" .
"        use                             generic-service\n" .
"        host_name                       $instanceID\n" .
"        service_description             HTTP Apache\n" .
"	 check_command			check_http! -u http://" . $instanceHostName . "/status\n" .
"	 contact_groups		 admins" . $contactgroups .  "\n" .
"	 }\n" .
"define service{\n" .
"        use                             generic-service\n" .
"        host_name                       $instanceID\n" .
"        service_description             Disk Use\n" .
"        check_command                   check_nrpe!check_sda1\n" .
"	 contact_groups		 admins" . $contactgroups .  "\n" .
"        }\n";

?>
