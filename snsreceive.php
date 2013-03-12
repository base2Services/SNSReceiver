<?PHP

$MobileNOs = array("614xxxxxxxx","614xxxxxxxx");
$Accounts = array(
  "AccountID" => "AccountAlias",
	"AccountID2" => "AccoutnAlias2"
	);
$SNSDIR = "/var/sns";
$NAGDIR = "/etc/nagios3/aws/";

if (isset($_GET['contacts'])) {
	$contactgroups = "," . $_GET['contacts'];
} else {
	$contactgroups = "";
}

error_reporting(-1);
header("Content-type: text/html; charset=utf-8");
require_once 'sdk-1.5.3/sdk.class.php';

$ec2 = new AmazonEC2();

if ( $_SERVER['REQUEST_METHOD'] == "POST" ) {

	$postdata = file_get_contents("php://input");

	$SNSFile = $SNSDIR . "/" . time() . "_SNS_Message.dat";
	$SNSFileHandle = fopen($SNSFile, "w");
	fwrite($SNSFileHandle, $postdata);
	fclose($SNSFileHandle);

	$SNSPayload = json_decode($postdata);
	
	switch ($SNSPayload->{'Type'}) {

		case 'SubscriptionConfirmation':
			$SNSConfirmationURLArray = parse_url($SNSPayload->{'SubscribeURL'});
			$SNSConfirmationURLQueryArray = explode("&", $SNSConfirmationURLArray['query']);
			foreach ($SNSConfirmationURLQueryArray as $QueryPartNum => $QueryPart) {
				$QueryPartArray = explode("=", $QueryPart);
				$SNSConfirmationURLQueryArray[$QueryPartNum] = $QueryPartArray[0] . "=" . urlencode($QueryPartArray[1]);
			}
			$SNSConfirmationURLQuery=implode("&", $SNSConfirmationURLQueryArray);

			$SNSConfirmationURL=$SNSConfirmationURLArray['scheme'] . "://" . $SNSConfirmationURLArray['host'] . $SNSConfirmationURLArray['path'] . "?" . $SNSConfirmationURLQuery;
			file_put_contents("/tmp/sns.log", $SNSConfirmationURL);
			$SNSConfirmationCurl = curl_init($SNSConfirmationURL);
			curl_exec($SNSConfirmationCurl);
			curl_close($SNSConfirmationCurl);
		break;



		case 'Notification':
			$SNSMessagePayload = json_decode(stripslashes($SNSPayload->{'Message'}));
			switch(substr($SNSPayload->{'Subject'}, 0, 12)) {

				case 'Auto Scaling':
					switch($SNSMessagePayload->{'Event'}) {

						case 'autoscaling:EC2_INSTANCE_LAUNCH';
							nag_creategroup($SNSMessagePayload->{'AutoScalingGroupName'}, $NAGDIR);
							nag_createhost($SNSMessagePayload->{'AutoScalingGroupName'}, $Accounts[$SNSMessagePayload->{'AccountId'}], $SNSMessagePayload->{'EC2InstanceId'}, $contactgroups, $NAGDIR);
							nag_reload('up');
						break;
	
						case 'autoscaling:EC2_INSTANCE_TERMINATE';
							nag_deletehost($SNSMessagePayload->{'EC2InstanceId'}, $SNSMessagePayload->{'AutoScalingGroupName'}, $NAGDIR);
							nag_reload('down');
						break;
					}
				break;

				default:
					$MessageToSend=$SNSMessagePayload->{'NewStateValue'} . ":" . $SNSMessagePayload->{'AlarmName'} . " in account: " . $Accounts[$SNSMessagePayload->{'AWSAccountId'}];
					foreach ($MobileNOs as &$Number) {
						sendmessage($MessageToSend, $Number);
					}
				break;
			}
		break;
	}
}

function nag_creategroup($groupname, $confdir) {

	//First we need to check if the group file already exists?
	if ( !file_exists($confdir . 'group_' . $groupname . '.cfg') ) {
		include('group.tpl');
		mkdir($confdir . '/' . $groupname);
		file_put_contents($confdir . 'group_' . $groupname . '.cfg', $groupdata);
	}

}

function nag_createhost($groupName, $creds, $instanceID, $contactgroups, $confdir) {

	$ec2 = new AmazonEC2(array('credentials'=>$creds));
	$ec2->set_region(AmazonEC2::REGION_US_W2);
	$response = $ec2->describe_instances(array('InstanceId'=>$instanceID));
	$instanceHostName  = $response->{'body'}->{'reservationSet'}->{'item'}->{'instancesSet'}->{'item'}->{'dnsName'};
	include('host.tpl');
	if ( !file_exists($confdir . '/' . $groupName . '/' . $instanceID . '.cfg') ) {
		include('host.tpl');
		file_put_contents($confdir . '/' . $groupName . '/' . $instanceID . '.cfg', $hostdata);
        }
}

function nag_deletehost($instanceID, $groupName, $confdir) {
	if ( file_exists($confdir . '/' . $groupName . '/' . $instanceID . '.cfg') ) {
		unlink($confdir . '/' . $groupName . '/' . $instanceID . '.cfg');
	}
}

function nag_reload($updown) {
	if ( $updown == 'up' ) {
		exec('nohup /var/www/snsreceive/nagreload.sh');
	} else {
		exec('sudo /usr/sbin/service nagios3 reload');
	}
};

function nag_cleanup($groupName, $confdir) {

	

}

function sendmessage($message, $phone) {

	$ch = curl_init("http://api.clickatell.com/http/sendmsg?api_id=3379947&user=<clickatelluser>&password=<clickatellpassword>&to=" . $phone . "&text=" . $message);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_TIMEOUT, 90);
	$response = curl_exec ($ch);
	curl_close ($ch);
}

?>
