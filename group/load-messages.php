<?php 
include('include/inc.php');

$message = new Message();
$group = $message->group();

$query = db()->prepare('
	SELECT * 
	FROM `messages` 
	WHERE `group_id` = :group_id 
		AND `is_join` = 0
		AND `date` > :date
	ORDER BY `date` ASC');
$query->bindValue(':group_id', $group['id']);
$query->bindValue(':date', get('since') ? date('Y-m-d H:i:s', get('since')) : '0000-00-00 00:00:00');
$query->execute();
$i = 0;

$json = array();

while($row = $query->fetch(PDO::FETCH_ASSOC))
{
	ob_start();
	echo '<div class="message ' . ($i % 2 == 0 ? 'even' : 'odd') . ($row['lat'] ? ' with-location' : '') . '" id="message-' . $row['id'] . '">';
		if($row['lat'])
			echo '<div class="message-location"></div>';
		echo '<div class="message-from"><span>[' . strtolower($row['network']) . ']</span> ' . $row['from'] . '</div>';
		echo '<div class="message-text">' . $row['msg'] . '</div>';
		echo '<div class="message-date">' . timeAgoInWords($row['date']) . '</div>';
		echo '<div style="clear: both;"></div>';
	echo '</div>';
	$return['html'] = ob_get_clean();
	$return['timestamp'] = strtotime($row['date']);
	$return['lat'] = $row['lat'];
	$return['lng'] = $row['lng'];
	$return['id'] = $row['id'];

	$json[] = $return;
	$i++;
}

echo json_encode($json);

?>