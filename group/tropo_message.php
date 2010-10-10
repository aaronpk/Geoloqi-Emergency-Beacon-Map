<?php 
include('include/inc.php');
header('Content-type: text/javascript');
session_start();

// Debugging
$jsonInput = file_get_contents("php://input");
$input = json_decode($jsonInput);
if(DEBUG_MODE)
{
	ob_start();
		echo "\n\n\n\n" . date('Y-m-d H:i:s') . ' ' . $_SERVER['REMOTE_ADDR'] . "\n";
		print_r($_GET);
		print_r(apache_request_headers());
		print_r($input);
		echo "\n";
	$fp = fopen('/tmp/tropo.txt', 'a');
	fwrite($fp, ob_get_clean());
	fclose($fp);
}

if($input->session->userType != 'NONE')
{
	// Receiving an incoming message
	
	$message = new Message_Tropo();
	$message->receive($input->session);
	
	$tropo = $message->tropo;
}
else
{
	// Sending an outbound message

	$message = new Message();
	$from = $input->session->parameters->loqi_from;
	$msg = $input->session->parameters->loqi_message;
	
	$message->sendToSubscribers($from, 'WEB', $msg);
	
	$tropo = $message->tropo;
}

echo json_encode(array('tropo'=>$tropo));

ob_start();
print_r($tropo);
$fp = fopen('/tmp/tropo.txt', 'a');
fwrite($fp, ob_get_clean() . "\n");
fclose($fp);

?>