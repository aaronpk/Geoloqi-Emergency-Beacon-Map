<?php 
require_once('config.php');

class Message
{
	protected $_data;
	protected $_group;
	
	protected $_isJoin = FALSE;
	
	// The response that will be sent to tropo
	public $tropo = array();
	
	public function __construct()
	{
		// Find the group ID
		$query = db()->prepare('SELECT * FROM `groups` WHERE `group_name` = :name');
		$query->bindParam(':name', $_GET['group_name']);
		$query->execute();
		$this->_group = $query->fetch();
		$this->_data['group_id'] = $this->_group['id'];
	}

	public function group()
	{
		return $this->_group;
	}
	
	public function getUser($user, $network)
	{
		// See if the incoming message was sent from a known user
		$query = db()->prepare('SELECT * FROM `users` WHERE `user` = :user AND `network` = :network AND `group_id` = :group_id');
		$query->bindValue(':group_id', $this->_data['group_id']);
		$query->bindParam(':user', $user);
		$query->bindParam(':network', $network);
		$query->execute();
		
		if($user = $query->fetch())
			return $user['id'];
		else
			return FALSE;
	}
	
	public function insert()
	{
		if($userID = $this->getUser($this->_data['from'], $this->_data['network']))
			$this->_data['user_id'] = $userID;
		
		$fields = array('group_id', 'user_id', 'date', 'from', 'network', 'msg', 'lat', 'lng', 'remote_addr', 'is_join');

		$this->_data['is_join'] = ($this->_isJoin ? 1 : 0);
		
		$insertData = array();
		$insertParams = array();
		$bindParams = array();
		foreach($fields as $param)
		{
			if(array_key_exists($param, $this->_data))
			{
				$insertData[$param] = $this->_data[$param];
				$insertParams[] = '`' . $param . '`';
				$bindParams[] = ':' . $param;
			}
		}
		
		$query = db()->prepare('INSERT INTO `messages` (' . implode(', ', $insertParams) . ') 
			VALUES(' . implode(', ', $bindParams) . ')');

		foreach($insertData as $k=>$v)
			$query->bindValue(':'.$k, $v);

		$query->execute();
	}
	
	public function receive()
	{
		$subscribe_words = array('subscribe', 'join', 'signup', 'sign up');
		$unsubscribe_words = array('unsubscribe', 'leave', 'stop', 'quit');

		if(in_array(strtolower($this->_data['msg']), $subscribe_words))
		{
			// Check if they're already subscribed
			if(!$this->getUser($this->_data['from'], $this->_data['network']))
			{
				$query = db()->prepare('INSERT INTO users (`group_id`, `user`, `network`, `date_subscribed`) VALUES(:group_id, :user, :network, NOW())');
				$query->bindValue(':group_id', $this->_data['group_id']);
				$query->bindValue(':user', $this->_data['from']);
				$query->bindValue(':network', $this->_data['network']);
				$query->execute();
			}
			else
			{
				$query = db()->prepare('UPDATE users SET `date_subscribed` = NOW(), `date_unsubscribed` = "0000-00-00 00:00:00" 
					WHERE `user` = :user AND `network` = :network AND `group_id` = :group_id');
				$query->bindValue(':group_id', $this->_data['group_id']);
				$query->bindValue(':user', $this->_data['from']);
				$query->bindValue(':network', $this->_data['network']);
				$query->execute();
			}
			
			$this->_isJoin = TRUE;
			
			$this->tropo[] = array('say' => array('value' => 'You are now subscribed! To send your location, go to http://loqi.me'));
		}
		elseif(in_array(strtolower($this->_data['msg']), $unsubscribe_words))
		{
			$query = db()->prepare('UPDATE users SET `date_unsubscribed` = NOW() WHERE `user` = :user AND `network` = :network AND `group_id` = :group_id');
			$query->bindValue(':group_id', $this->_data['group_id']);
			$query->bindValue(':user', $this->_data['from']);
			$query->bindValue(':network', $this->_data['network']);
			$query->execute();
			
			$this->_isJoin = TRUE;
			
			$this->tropo[] = array('say' => array('value' => 'You have unsubscribed. You will stop receiving alerts from us.'));
		}
		else
		{
			// Broadcast the message to all other users!
			$query = $this->sendToSubscribers($this->_data['from'], $this->_data['network'], $this->_data['msg']);
		}
		
		$this->insert();
	}
	
	public function sendToSubscribers($from, $network, $message)
	{
		$query = db()->prepare('SELECT * FROM users 
			WHERE `group_id` = :group_id
				AND `network` != "twitter"
				AND `date_unsubscribed` = "0000-00-00 00:00:00"');
		$query->bindValue(':group_id', $this->_data['group_id']);
		$query->execute();
		
		while($row = $query->fetch(PDO::FETCH_ASSOC))
		{
			// Don't send the message back to the person who sent it
			if(!($row['user'] == $from && strtolower($row['network']) == strtolower($network)))
			{
				$this->tropo[] = array('message' => array(
					'say' => array('value' => '[' . strtolower($network) . '] ' . $from . ': ' . $message),
					'to' => $row['user'],
					'network' => $row['network']
				));
			}
		}
		
		// Also tweet it
		$this->tropo[] = array('message' => array(
			'say' => array('value' => '[' . strtolower($network) . '] ' . $from . ': ' . $message),
			'to' => 'loqime',
			'network' => 'TWITTER'
		));		
	}
	
}

class Message_Tropo extends Message
{
	public function receive($tropoSession)
	{
		$this->_data['date'] = date('Y-m-d H:i:s');
		$this->_data['from'] = $tropoSession->from->id;
		$this->_data['network'] = $tropoSession->from->network;
		$this->_data['msg'] = $tropoSession->initialText;

		parent::receive();
	}

}

class Message_MobileWeb extends Message
{
	public function receive()
	{
		$phone = trim(post('phone'));
		if(preg_match('/^[0-9\(\)\-]+$/', trim(post('phone'))))
			$network = 'SMS';
		else
			$network = 'WEB';
		
		// Handle the form post data
		$this->_data['date'] = date('Y-m-d H:i:s');
		$this->_data['from'] = $phone;
		$this->_data['network'] = $network;
		$this->_data['msg'] = post('message');
		$this->_data['lat'] = post('lat');
		$this->_data['lng'] = post('lng');
		$this->_data['remote_addr'] = $_SERVER['REMOTE_ADDR'];
	
		parent::receive();

		// After inserting the message, trigger Tropo to send outbound messages
		
		$params = array();
		$params['action'] = 'create';
		$params['token'] = $this->_group['tropo_outbound_token'];
		$params['loqi_from'] = $phone;
		$params['loqi_message'] = post('message') . ' (at ' . round(post('lat'), 4) . ',' . round(post('lng'), 4) . ')';
		file_get_contents('http://api.tropo.com/1.0/sessions?' . http_build_query($params, '', '&')) . "\n";
	}	
	
}


function db()
{
	static $db;
	
	if(!isset($db))
	{
		try {
			$db = new PDO(PDO_DSN, PDO_USER, PDO_PASS);
		} catch (PDOException $e) {
		header('HTTP/1.1 500 Server Error');
		die('Connection failed: ' . $e->getMessage());
		}
	}
	
	return $db;
}

function get($k)
{
	if(array_key_exists($k, $_GET))
		return $_GET[$k];
	else
		return FALSE;
}

function post($k)
{
	if(array_key_exists($k, $_POST))
		return $_POST[$k];
	else
		return FALSE;
}

function ircdebug($msg)
{
	$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
	socket_sendto($sock, $msg, strlen($msg), 0, 'nerdle.pin13.net', 51779);
}

function filedebug($obj)
{
	static $fp;
	
	if(!isset($fp))
		$fp = fopen('/tmp/tropo.txt', 'a');
	
	ob_start();
		print_r($obj);
		echo "\n";
	fwrite($fp, ob_get_clean());
}

/**
 * Returns either a relative date or a formatted date depending
 * on the difference between the current time and given datetime.
 * $datetime should be in a <i>strtotime</i>-parsable format, like MySQL's datetime datatype.
 *
 * Relative dates look something like this:
 *	3 weeks, 4 days ago
 *	15 seconds ago
 * Formatted dates look like this:
 *	on 02/18/2004
 *
 * The returned string includes 'ago' or 'on' and assumes you'll properly add a word
 * like 'Posted ' before the function output.
 *
 * @param string $date_string Datetime string or Unix timestamp
 * @param string $format Default format if timestamp is used in $date_string
 * @param string $countFrom Calculate time difference from this date. Defaults to time()
 * @param string $backwards False if $date_string is in the past, true if in the future
 * @return string Relative time string.
 */
function timeAgoInWords($datetime_string, $format = 'n/j/y', $since_string = 'now') {
	$datetime = strtotime($datetime_string);

	if( $since_string == 'now' )
		$since = time();
	else
		$since = strtotime($since_string);

	$in_seconds = $datetime;

	$diff = $since - $in_seconds;

	$months = floor($diff / 2419200);
	$diff -= $months * 2419200;
	$weeks = floor($diff / 604800);
	$diff -= $weeks * 604800;
	$days = floor($diff / 86400);
	$diff -= $days * 86400;
	$hours = floor($diff / 3600);
	$diff -= $hours * 3600;
	$minutes = floor($diff / 60);
	$diff -= $minutes * 60;
	$seconds = $diff;

	if ($days > 7 && $since_string == 'now') {
		// over a week old, just show date (mm/dd/yyyy format)
		$relative_date = 'on ' . date($format, $in_seconds);
		$old = true;
	} else {
		$relative_date = '';
		$old = false;

		if ($months > 6) {
			// months only
			$relative_date .= ($relative_date ? ', ' : '') . $months . ' months';
			$relative_date .= ' ago';
		} elseif ($months > 0) {
			// months and weeks
			$relative_date .= ($relative_date ? ', ' : '') . $months . ' month' . ($months > 1 ? 's' : '');
			$relative_date .= $weeks > 0 ? ($relative_date ? ', ' : '') . $weeks . ' week' . ($weeks > 1 ? 's' : '') : '';
			$relative_date .= ' ago at ' . date('g:ia', $datetime);
		} elseif ($weeks > 0) {
			// weeks and days
			$relative_date .= ($relative_date ? ', ' : '') . $weeks . ' week' . ($weeks > 1 ? 's' : '');
			$relative_date .= $days > 0 ? ($relative_date ? ', ' : '') . $days . ' day' . ($days > 1 ? 's' : '') : '';
			$relative_date .= ' ago at ' . date('g:ia', $datetime);
		} elseif($days > 0) {
			// days and hours
			$relative_date .= ($relative_date ? ', ' : '') . $days . ' day' . ($days > 1 ? 's' : '');
			#$relative_date .= $hours > 0 ? ($relative_date ? ', ' : '') . $hours . ' hour' . ($hours > 1 ? 's' : '') : '';
			$relative_date .= ' ago at ' . date('g:ia', $datetime);
		} elseif($hours > 0) {
			// hours and minutes
			$relative_date .= ($relative_date ? ', ' : '') . $hours . ' hour' . ($hours > 1 ? 's' : '');
			#$relative_date .= $minutes > 0 ? ($relative_date ? ', ' : '') . $minutes . ' minute' . ($minutes > 1 ? 's' : '') : '';
			$relative_date .= ' ago at ' . date('g:ia', $datetime);
		} elseif($minutes > 0) {
			// minutes only
			$relative_date .= ($relative_date ? ', ' : '') . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
			$relative_date .= ' ago';
		} else {
			// seconds only
			$relative_date .= ($relative_date ? ', ' : '') . $seconds . ' second' . ($seconds != 1 ? 's' : '');
			$relative_date .= ' ago';
		}
	}

	return $relative_date;
}

?>