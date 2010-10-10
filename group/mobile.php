<?php 
require_once('include/inc.php');

if($_GET['method'] == 'send')
{
	$message = new Message_MobileWeb();
	$message->receive();

	echo 'ok';
	die();
}

$message = new Message();
$group = $message->group();

?>
<!DOCTYPE html>
<html>
<head>
	<title><?=$group['group_name']?></title>
	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.1/jquery.min.js"></script>
	<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=true"></script>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
	<style type="text/css">
		body {
			margin: 0;
			padding: 0;
			-webkit-text-size-adjust:none;
		}
		#toolbar {
			position: absolute;
			top: 0;
			left: 0;
			z-index: 200;
			width: 320px;
			height: 54px;
			background: url(/group/images/iphone-bar-bkg.png) repeat-x;
		}
		#messagebox {
			position: absolute;
			top: 54px;
			z-index: 200;
			width: 320px;
		}
		#messagebox input {
			width: 320px;
			font-size: 18pt;
			margin: 0 auto;
			color: #999;
		}
		a img {
			border: 0;
		}
		#saveBtnWrapper {
			padding-top: 9px;
			width: 103px;
			height: 32px;
			margin: 0 auto;
		}
		#saveBtn {
			width: 103px;
			height: 32px;
			cursor: normal;
		}
		#saveBtn span {
			display: block;
			background: url(/group/images/send-button.png) bottom no-repeat;
			width: 103px;
			height: 32px;
		}
		#refreshBtn {
			width: 32px;
			height: 32px;
			z-index: 200;
		}
		#refreshBtnWrapper {
			margin-top: 9px;
			margin-right: 20px;
			float: right;
			z-index: 200;
		}
		#ajaxLoader {
			float: left;
			margin-left: 20px;
			margin-top: 12px;
		}
		#ajaxLoader img {
			display: none;		
		}
	</style>
	<script type="text/javascript">

		var positionFound = false;
	
		var map;
		var marker;
		var latlng;
		var myOptions = {
			zoom: 14,
			center: latlng,
			disableDefaultUI: true,
			mapTypeId: google.maps.MapTypeId.ROADMAP
	    };
	    
		$(function(){
			geoLocate();
			resizeWindow();
			$(window).resize(resizeWindow);

			$("#messagebox input").focus(function(){
				if($(this).val() == $(this).attr("title")){
					$(this).val("").css({color: "#000"});
				}
			});
		});
		
		function resizeWindow(){
			$("#map_canvas, body").css("height", (window.innerHeight)+"px").css("width", window.innerWidth+"px");
			$("#messagebox input").css("width", (window.innerWidth)+"px");
			$("#toolbar").css("width", window.innerWidth+"px");
		}

		function geoLocate(){
			loading();
			positionFound = false;
			// try to get the user's location
			if (typeof(navigator.geolocation) != 'undefined') {
				navigator.geolocation.getCurrentPosition(function(position) {
					var lat = position.coords.latitude;
					var lng = position.coords.longitude;
					var position = new google.maps.LatLng(lat, lng);
					loadingComplete();
					positionFound = true;

				    map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
					
				    // Create the marker at the last known location
					marker = new google.maps.Marker({
						position: position,
						map: map,
						title: 'Your Location'
					});
					
					map.setCenter(marker.getPosition());
					
					// Resize the map canvas to full screen
					resizeWindow();
				});
			}else{
				alert("This browser does not support geolocating.");
			}
		}

		function loading(){
			$("#ajaxLoader img").show();
			$("#saveBtn span").css({backgroundPosition: "bottom"});
			$("#saveBtn").css({cursor: "normal"});
		}
		function loadingComplete(){
			$("#ajaxLoader img").hide();
			$("#saveBtn span").css({backgroundPosition: "top"});
			$("#saveBtn").css({cursor: "pointer"});
		}			
		
		function saveLocation(){
			var phone = ($("#phone").val() == $("#phone").attr("title") ? "" : $("#phone").val());
			var message = ($("#message").val() == $("#message").attr("title") ? "" : $("#message").val());
			if(positionFound == true){
				loading();
				$.post("<?=$group['group_name']?>.ajax", {
					lat: marker.getPosition().lat(),
					lng: marker.getPosition().lng(),
					phone: phone,
					message: message
				}, function(response){
					loadingComplete();
					if(response == "ok"){
						alert("Message sent successfully");
						$("#phone").val($("#phone").attr("title")).css({color: "#999"});
						$("#message").val($("#message").attr("title")).css({color: "#999"});
					}else{
						alert("There was an error sending your message!");
					}
				});
			}
		}
	</script>
</head>
<body>
	<div id="toolbar">
		<div id="ajaxLoader"><img src="/group/images/ajax-loader.gif" height="24" width="24" /></div>
		<div id="refreshBtnWrapper"><a id="refreshBtn" href="javascript:geoLocate();"><img src="/group/images/refresh-location.png" height="32" width="32" /></a></div> 
		<div id="saveBtnWrapper"><a href="javascript:saveLocation();" id="saveBtn"><span></span></a></div>
	</div>
	<div id="messagebox">
		<input id="phone" value="Phone Number" title="Phone Number" /><br />
		<input id="message" value="Message" title="Message" />
	</div>
	<div id="map_canvas" style="width: 320px; height: 480px;"></div>
</body>
</html>