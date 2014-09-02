<?PHP

$hostdata = 

"define host{\n" .
"	use					some-template\n" .
"	host_name		" . $instanceID . "-" . $groupName . "\n" .
"	hostgroups	" . $groupName . "\n" .
"	alias				" . $instanceID . "\n" .
"	address			" . $instanceHostName . "\n" .
"}\n";

?>
