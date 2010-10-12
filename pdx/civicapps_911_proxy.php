<?php 
include('include/inc.php');

$url = 'http://www.portlandonline.com/scripts/911incidents.cfm';

$categories = array(
	'HAZARDOUS CONDITION',
	'APPLIANCE/EQUIP FIRE',
	'AUTOMATIC FIRE ALARM-RES',
	'POWER LINE/POLE DOWN/ARCING',
	'TRAFFIC ACC/ROLL OVER',
	'VEHICLE/TRAILER FIRE',
	'HAZ MAT LEVEL 1 (NO FIRE/INJ)',
	'TRAFFIC ACC/1ST RESP',
	'AUTOMATIC FIRE ALARM-COMM'
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_REFERER, 'http://loqi.me/pdx/civicapps');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
$xml_string = curl_exec($ch);

$xml = new SimpleXMLElement($xml_string);

if(array_key_exists('since', $_GET))
	$since = $_GET['since'];
else
	$since = FALSE;
	
if(array_key_exists('callback', $_GET))
	$jsonp = $_GET['callback'];
else
	$jsonp = FALSE;

$json = array();

foreach($xml->entry as $entry)
{
	if($since == FALSE || strtotime($entry->published) > $since)
	{
		$element = array();
		foreach($entry->category->attributes() as $key=>$attr)
			$element['category'][$key] = (string)$attr;

		if(in_array($element['category']['term'], $categories))
		{
			$georss = $entry->children('http://www.georss.org/georss');
			$element['id'] = (string)$entry->id;
			$element['hash'] = md5($entry->id);
			$element['title'] = (string)$entry->title;
			$element['updated'] = (string)$entry->updated;
			$element['published'] = (string)$entry->published;
			$element['timestamp'] = strtotime($entry->published);
			$element['summary'] = (string)$entry->summary;
			$element['point'] = (string)$georss->point;
			$element['content'] = (string)$entry->content;
	
			ob_start();
			echo '<div class="message with-911" id="message-' . $element['hash'] . '">';
				echo '<div class="message-911"></div>';
				echo '<div class="message-from"><span>[911]</span> ' . preg_replace('/(\b)([a-z])/e', '"\\1".ucfirst("\\2")', strtolower($element['category']['label'])) . '</div>';
				echo '<div class="message-text">' . trim(str_replace($element['category']['label'], '', $element['summary'])) . '</div>';
				echo '<div class="message-date">' . timeAgoInWords($element['published']) . '</div>';
				echo '<div style="clear: both;"></div>';
			echo '</div>';
			$element['html'] = ob_get_clean();
			
			$json[] = $element;
		}
	}
}

usort($json, function($a, $b){
	return $a['timestamp'] > $b['timestamp'];
});

header('Content-type: text/javascript');

if($jsonp)
	echo $jsonp . '(' . json_encode($json) . ');';
else
	echo json_encode($json);

?>