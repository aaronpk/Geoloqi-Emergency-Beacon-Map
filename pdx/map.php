<?php 
require_once('include/inc.php');

$message = new Message();
$group = $message->group();

?>
<!DOCTYPE html>
<html>
<head>
	<title><?=$group['group_name']?></title>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js"></script>
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
	<style type="text/css">
		body {
			margin: 0;
			padding: 0;
			font-family: Verdana, Arial, sans-serif;
		}
		#header {
			position: relative;
			height: 60px;
		}
		#the-table {
			width: 100%;
		}
		#header-row {
			background-color: #acacac;
			color: #4a4a4a;
			font-size: 14pt;
			font-weight: bold;
		}
		#header-row td {
			padding: 2px 10px;
		}
		#map {
			width: 100%;
		}
		#random-pin {
			position: absolute;
			top: 50px;
			left: 10px;
			width: 24px;
			height: 36px;
			background: url(images/pin.png) no-repeat;
		}
		
		#footer-row, #footer-row a {
			font-size: 8pt;
			color: white;
		}
		
		.message {
			padding: 6px;
		}
		.message.with-location.hover, .message.odd.with-location.hover, .message.with-911.hover, .message.odd.with-911.hover {
			background-color: #fde9d7;
			cursor: pointer;
		}
		.message.odd {
			background-color: #fff8f1;
		}
		.message-from {
			font-size: 11pt;
			font-weight: bold;
			color: #ff981f;
		}
		.message-from span {
			font-size: 9pt;
			color: #c9c9c9;
		}
		.message-text {
			margin: 3px 0;
		}
		.message-date {
			font-size: 8pt;
		}
		.message-location {
			float: left;
			width: 24px;
			height: 50px;
			margin-right: 5px;
			background: url(images/911-call-icon-24px.png) no-repeat center;
		}
		.message-911 {
			float: left;
			width: 24px;
			height: 50px;
			margin-right: 5px;
			background: url(images/pin.png) no-repeat center;
		}
		.message.with-911 .message-text {
			font-size: 10pt;
		}
		.legend {
			padding: 10px; 
			font-size: 10pt;
			background-color: #fff2e7;
		}
		.legend img {
			margin: 0 5px;
		}
	</style>
	<script type="text/javascript">
		var refreshInterval = 10; // in seconds
		var map;
		var last_ts = 0;
		var last_ts_911 = <?=strtotime('-4 hours')?>;
		var infowindow;
		var markers = {};
		var markers911 = {};
		var fireStations = [];
		var hospitals = [];

		var myOptions = {
			zoom: 13,
			center: new google.maps.LatLng(45.51, -122.64),
			mapTypeId: google.maps.MapTypeId.ROADMAP
	    };

		var icon911 = new google.maps.MarkerImage('images/pin.png',
			new google.maps.Size(24, 36),
			new google.maps.Point(0, 0),
			new google.maps.Point(4, 33));
		var iconFireStation = new google.maps.MarkerImage('images/fire-station-icon-24px.png',
			new google.maps.Size(24, 24),
			new google.maps.Point(0, 0),
			new google.maps.Point(12, 12));
		var iconHospital = new google.maps.MarkerImage('images/hospital-icon-24px.png',
			new google.maps.Size(24, 24),
			new google.maps.Point(0, 0),
			new google.maps.Point(12, 12));
		var iconPin = new google.maps.MarkerImage('images/911-call-icon-24px.png',
			new google.maps.Size(24, 24),
			new google.maps.Point(0, 0),
			new google.maps.Point(12, 12));
			
		$(function(){
		    map = new google.maps.Map(document.getElementById("map"), myOptions);
			resizeWindow();
			$(window).resize(resizeWindow);

			setTimeout(loadMessages, 1000); // Cheat by loading the messages delayed, so that they appear on top
			load911Calls();
			setInterval(loadMessages, refreshInterval * 1000);
			setInterval(load911Calls, refreshInterval * 1100); // Stagger the updates a bit

			google.maps.event.addListener(map, 'tilesloaded', reloadObjects);
			google.maps.event.addListener(map, 'dragend', reloadObjects);
		});

		function resizeWindow(){
			var height = (window.innerHeight - $("#header").height() - $("#header-row").height() - $("#footer-row").height());
			$("#message-log").css("height", (height - 90) + "px");
			$("#map").css("height", height + "px");
		}

		function reloadObjects(){
			loadPDXAPIdata("fire_sta", fireStations, iconFireStation);
			loadPDXAPIdata("hospital", hospitals, iconHospital);
		}

		function loadMessages(){
			$.getJSON("<?=$group['group_name']?>/load-messages.ajax", {
				since: last_ts
			}, function(data){
				for(var i in data){
					$("#message-log").prepend(data[i].html);
					last_ts = data[i].timestamp;

					if(data[i].lat != 0){
						var position = new google.maps.LatLng(data[i].lat, data[i].lng);
						
						$("#message-"+data[i].id).hover(function(){
							$(this).addClass("hover");	
						}, function(){
							$(this).removeClass("hover");
						}).click(function(){
							var id = $(this).attr("id").split("-")[1]
							google.maps.event.trigger(markers[id], "click", {id: id});
						});
						map.setCenter(position);
					
						markers[data[i].id] = new google.maps.Marker({
							map: map,
							position: position,
							title: data[i].id,
							icon: iconPin
						});
						attachInfoWindow(markers[data[i].id]);
					}
				}
			});
		}

		function load911Calls(){
			$.getJSON("civicapps_911.json", {
				since: last_ts_911
			}, function(data){
				for(var i in data){
					$("#message-log").prepend(data[i].html);
					last_ts_911 = data[i].timestamp;

					if(data[i].lat != 0){
						var position = new google.maps.LatLng(data[i].point.split(" ")[0], data[i].point.split(" ")[1]);

						$("#message-"+data[i].hash).hover(function(){
							$(this).addClass("hover");	
						}, function(){
							$(this).removeClass("hover");
						}).click(function(){
							var id = $(this).attr("id").split("-")[1]
							google.maps.event.trigger(markers911[id], "click", {id: id});
						});
						map.setCenter(position);
					
						markers911[data[i].hash] = new google.maps.Marker({
							map: map,
							position: position,
							title: data[i].hash,
							icon: icon911
						});
						attachInfoWindow(markers911[data[i].hash]);
					}
				}
			});
		}
		
		function loadPDXAPIdata(dataset, markersPDX, icon){
			var sw = map.getBounds().getSouthWest();
			var ne = map.getBounds().getNorthEast();

			$.getJSON("http://pdxapi.com/" + dataset + "/geojson?callback=?", {
				bbox: sw.lng() + "," + sw.lat() + "," + ne.lng() + "," + ne.lat()
			}, function(data){
				for(var i in markersPDX){
					markersPDX[i].setMap();
				}
				markersPDX = [];

				for(var i in data.rows){
					var geo = data.rows[i].value.geometry.coordinates;
					markersPDX.push(new google.maps.Marker({
				        position: new google.maps.LatLng(geo[1], geo[0]),
				        map: map,
				        icon: icon
				    }));			
				}
			});
		}
		
		function attachInfoWindow(marker){
			google.maps.event.addListener(marker, "click", function(event){
				var id;
				if(event.id){
					id = event.id;
				}else{
					id = $(event.target).attr("title");
				}
				if(infowindow){
					infowindow.close();
				}
				infowindow = new google.maps.InfoWindow({
					content: '<div style="width:300px;">' + $("#message-"+id).html() + '</div>'
				});
				infowindow.open(map, marker);
			});
		}
	</script>
</head>
<body>

<div id="header">
	<div id="hlogo" style="background: url(images/loqime-logo.png) no-repeat; width: 145px; height: 66px; position: absolute; left: 10px;"></div>
	<div id="htext-1" style="position: absolute; top: 13px; left: 160px;">
		Organizers can instantly add themselves to<br />
		the list by texting <strong>join</strong> to <strong>(202) 618-0872</strong>.
	</div>
	<div id="htext-2" style="position: absolute; top: 13px; left: 560px;">
		Visitors can send a message from their location<br />
		by visiting <strong><a href="http://loqi.me">http://loqi.me</a></strong> in a mobile browser.
	</div>
</div>
<div id="random-pin"></div>
<table cellpadding="0" cellspacing="0" id="the-table">
	<tr id="header-row">
		<td style="background: url(images/gradient.png) repeat-y right; padding-left: 45px;"></td>
		<td>Messages</td>
	</tr>
	<tr>
		<td style="vertical-align: top;" rowspan="2">
			<div id="map"></div>
		</td>
		<td width="300" style="vertical-align: top;">
			<div id="message-log" style="overflow: auto;">
			</div>
		</td>
	</tr>
	<tr>
		<td height="90"><div class="legend">
			<table cellspacing="0" cellpadding="0">
				<tr>
					<td><img src="images/911-call-icon-24px.png" width="24" height="24" /></td>
					<td>Loqi.me Beacon</td>
					<td><img src="images/pin.png" width="24" height="36" /></td>
					<td>911 Call</td>
				</tr>
				<tr>
					<td><img src="images/fire-station-icon-24px.png" width="24" height="24" /></td>
					<td>Fire Station</td>
					<td><img src="images/hospital-icon-24px.png" width="24" height="24" /></td>
					<td>Hospital</td>
				</tr>
			</table>
		</div></td>
	</tr>
	<tr id="footer-row">
		<td colspan="2" style="background-color: #ff9a41; height: 23px; color: white; padding-left: 20px;">
			By <a href="http://aaronparecki.com">Aaron Parecki</a> and <a href="http://oakhazelnut.com">Amber Case</a> of <a href="http://geoloqi.com">Geoloqi.com</a>
		</td>
	</tr>
</table>

<?php googleAnalytics(); ?>

</body>
</html>
