<?PHP

$groupdata =  "define hostgroup {\n";
$groupdata .= "	hostgroup_name	" . $groupname . "\n";
$groupdata .= "	alias						AWS AS Group " . $groupname . "\n";
$groupdata .= "}\n";
$groupdata .= "\n";
//$groupdata .= "define service {\n";
//$groupdata .= "	use									some-service\n";
//$groupdata .= "	hostgroup_name			" . $groupname . "\n";
//$groupdata .= "	service_description	Check Nginx\n";
//$groupdata .= "	check_command				check_nrpe_ssl!check_nginx\n";
//$groupdata .= "}\n";


?>

