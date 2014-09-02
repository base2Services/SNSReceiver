<?PHP

date_default_timezone_set("Australia/Melbourne");

$MobileNOs = array("614xxxxxxxx","614xxxxxxxx");
#$Accounts = array(
#  "AccountID" => "AccountAlias",
#	"AccountID2" => "AccountAlias2"
#	);
$SNSDIR = "<SNS Spool directory for outputting requests>";
$TMPDIR = "<local temporary location>";
$NAGDIR = "<directory to create hostgroups and drop config files>";
$SSHUSER = "<your nagios/icinga ssh user>";
$NAGHOST = "<your nagios/icinga host>";
$SSHKEY = "<your ssh key>";

if (isset($_GET['contacts'])) {
	$contactgroups = "," . $_GET['contacts'];
} else {
	$contactgroups = "";
}

error_reporting(-1);
header("Content-type: text/html; charset=utf-8");
require_once 'aws.phar';

#$ec2 = new AmazonEC2();

use Aws\Common\Aws;

$aws = Aws::factory(array(
    'profile' => '',
    'region'  => '',
));

use Aws\Ec2\Ec2Client;

$ec2 = $aws->get('Ec2');

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
							nag_creategroup_remote($SNSMessagePayload->{'AutoScalingGroupName'}, $NAGDIR, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST);
							nag_createhost_remote($SNSMessagePayload->{'AutoScalingGroupName'}, $SNSMessagePayload->{'AccountId'}, $SNSMessagePayload->{'EC2InstanceId'}, $contactgroups, $NAGDIR, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST);
							nag_reload_remote('up', $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST);
						break;

						case 'autoscaling:EC2_INSTANCE_TERMINATE';
							nag_deletehost_remote($SNSMessagePayload->{'EC2InstanceId'}, $SNSMessagePayload->{'AutoScalingGroupName'}, $NAGDIR, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST);
							nag_reload_remote('down', $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST);
						break;
					}
				break;

				default:
					$MessageToSend=$SNSMessagePayload->{'NewStateValue'} . ":" . $SNSMessagePayload->{'AlarmName'} . " in account: " . $Accounts[$SNSMessagePayload->{'AWSAccountId'}];
					foreach ($MobileNOs as &$Number) {
						#sendmessage($MessageToSend, $Number);
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
         $aws = Aws::factory(array(
            'profile' => '',
            'region'  => '',
        ));

        $ec2 = $aws->get('Ec2');
        $response = $ec2->describeInstances(array('InstanceIds'=>array($instanceID)));
        $reservations = $response['Reservations'];
        foreach ($reservations as $reservation) {
                $instances = $reservation['Instances'];
                foreach ($instances as $instance) {
                        $instanceHostName  = $instance['PrivateIpAddress'];
                }
        }

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
		exec('nohup nagreload.sh');
	} else {
		exec('sudo /usr/sbin/service nagios3 reload');
	}
};


function nag_creategroup_remote($groupname, $confdir, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST) {

	include('group.tpl');
	shell_exec("ssh -i $SSHKEY $SSHUSER@$NAGHOST 'mkdir $confdir/$groupname'");
	file_put_contents($TMPDIR . '/group_' . $groupname . '.cfg', $groupdata);
	shell_exec("scp -i $SSHKEY $TMPDIR/group_$groupname.cfg $SSHUSER@$NAGHOST:$confdir/group_$groupname.cfg");

}

function nag_createhost_remote($groupName, $creds, $instanceID, $contactgroups, $confdir, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST) {
	 $aws = Aws::factory(array(
	    'profile' => '',
	    'region'  => '',
	));

        $ec2 = $aws->get('Ec2');
        $response = $ec2->describeInstances(array('InstanceIds'=>array($instanceID)));
	$reservations = $response['Reservations'];
	foreach ($reservations as $reservation) {
		$instances = $reservation['Instances'];
		foreach ($instances as $instance) {
		        $instanceHostName  = $instance['PrivateIpAddress'];
		}
	}

	include('host.tpl');
	file_put_contents($TMPDIR . '/' . $instanceID . '.cfg', $hostdata);
	shell_exec("scp -i $SSHKEY $TMPDIR/$instanceID.cfg $SSHUSER@$NAGHOST:$confdir/$groupName/$instanceID.cfg");
}

function nag_deletehost_remote($instanceID, $groupName, $confdir, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST) {
	shell_exec("ssh -i $SSHKEY $SSHUSER@$NAGHOST 'rm -rf $confdir/$groupName/$instanceID.cfg'");
}

function nag_reload_remote($updown, $TMPDIR, $SSHKEY, $SSHUSER, $NAGHOST) {
	if ( $updown == 'up' ) {
		exec("nohup /var/www/html/SNSReceiver/nagreloadremote.sh $NAGHOST $SSHKEY $SSHUSER 300");
	} else {
		exec("nohup /var/www/html/SNSReceiver/nagreloadremote.sh $NAGHOST $SSHKEY $SSHUSER 0");
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