<?php
/**
 * Polls the local SOnos device for content and controls the device (play, next etc).
 * Initialization of the device is still needed from the sonos player
 *
 * @author se31139
 *
 */
namespace sonos_hacsvc {
	use debug, urlencode, json_decode, PHPSonos;
	require (__NAMESPACE__."\PHPSonos.inc.php");

	/**
	 * Helper functions
	*/
	function kvp_get($key){
		return \kvp_get($key,__NAMESPACE__);
	}
	function kvp_set($key, $value){
		\kvp_set($key, $value, __NAMESPACE__);
	}
	function getData(){
		return \getData(__NAMESPACE__);
	}
	function deleteData(){
		return \deleteData(__NAMESPACE__);
	}

	/**
	 * Setup information. Used in the configuration of the service.
	 * setup_ui: Configures UI text components
	 * setup_data: Data configuration used for delivering the data in the component
	 */
	function setup_ui(){
		return array(
				array("key"=>"now_playing_txt", "value"=>"Nu spelas", "mandatory"=>1,"description"=>"Title text before song/channel title"),
				array("key"=>"by_txt", "value"=>"By", "mandatory"=>1,"description"=>"From artist text"),
				array("key"=>"from_album_txt", "value"=>"from the album", "mandatory"=>1,"description"=>"From the album text")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"ip", "value"=>null, "mandatory"=>1,"description"=>"IP address of the Sonos component to control")
		);
	}

	/**
	 * load_data function:
	 * should return either a json, false or null response
	 * null = no data is to be stored (no infobox will be presented)
	 * false = something went wrong in the function and nothing will be removed (if a infobox was present, it will not be removed)
	 * json = a dataformat that is understandable by the client
	 */
	function load_data($setup_data){
		//Get the Sonos object
		$sonos = new PHPSonos($setup_data["ip"]);
			
		//Get current status
		$status = getStatus();
		debug("Status: ".$status);
		$muted = isMuted();
		debug("Muted: ".($muted?"yes":"no"));
		if($status<3){
			$radio = array();
			$songs = array();
			$title = "";
			$currentPlaying ="";
			$artist ="";
			//Get current playing info
			$positionInfo = $sonos-> GetPositionInfo ();

			//var_dump($positionInfo);
			//Check if radio is playing
			$mediaInfo = $sonos-> GetMediaInfo();
			debug(print_r($positionInfo,true));
			
			if (isset($mediaInfo["title"])){
				$radioIsOn=true;
				$radio = array("channel"=>$mediaInfo["title"], "title"=>$positionInfo["streamContent"]);
				debug("Radio is on: ".print_r($radio, true));
			} else {
				$radioIsOn=false;
				debug("Song is on");
				//Get next song info
				$currentPlaylist = $sonos-> GetCurrentPlaylist();
				debug(print_r($currentPlaylist,true));
				
				$next = array();
				$prev = array();
				if (isset($currentPlaylist[((int)$positionInfo["Track"])])){
					$next = array("title"=>$currentPlaylist[((int)$positionInfo["Track"])]["title"],"album"=>$currentPlaylist[((int)$positionInfo["Track"])]["album"],"artist"=>$currentPlaylist[((int)$positionInfo["Track"])]["artist"]);
				}
				if (isset($currentPlaylist[((int)$positionInfo["Track"])-2])){
					$prev = array("title"=>$currentPlaylist[((int)$positionInfo["Track"])-2]["title"],"album"=>$currentPlaylist[((int)$positionInfo["Track"])-2]["album"],"artist"=>$currentPlaylist[((int)$positionInfo["Track"])-2]["artist"]);
				}
				$songs = array("album"=>$positionInfo["album"], "artist"=>$positionInfo["artist"], "title"=>$positionInfo["title"], "next"=>$next, "previous"=>$prev);

			}
			$final = array("radioOn"=>$radioIsOn, "status"=>$status, "muted"=>$muted, "volume"=>$sonos->GetVolume(), "radio"=>$radio,"songs"=>$songs);
			//var_dump($final);
			return $final;
		} else
			return null;
	}

	const javascript = <<<EOT
	this.content = function (widget, ui, data){

			var button_fw = false;
			var button_rw = false;
			var button_play = false;
			var button_stop = true;
			var button_muteOn = (data.muted ? true: false);
			var headline = "";
			var subheadline =null;
			var html="";
			
			//Add button actions
			var url = '../HAC%20Server/serviceFunctionCall.php';
			var service = 'sonos_hacsvc';
			
			//Disable forward/reverse buttons if radio is on
			if (!data.radioOn){
				if (data.songs.next.title) button_fw=true;
				if (data.songs.previous.title) button_rw=true;
			}
			
			//Define titles
			if (data.radioOn){
				headline = ui.now_playing_txt+' <strong>'+data.radio.channel+'</strong>';
				if (data.radio.title!="")
					subheadline =data.radio.title;
			} else {
				headline = ui.now_playing_txt+' <strong>'+data.songs.title+'</strong>';
				subheadline = ui.by_txt+' '+data.songs.artist+' '+ui.from_album_txt+' '+data.songs.album;
			}
			
			/* Button visibility
			* Status: 1: PLAYING, 2: PAUS, 3: STOPPED
			* Radio: disable prev/forward, remove play (started from Sonos client)
			*/
			html = '<div align="center"> <div data-role="controlgroup" data-type="horizontal">';
			if (data.radioOn){
				switch (data.status){
					case 1: 
						html+='<a id="sonos_back" href="#" data-role="button" class="ui-disabled">Reverse</a> <a id="sonos_stop" href="#" data-role="button">Stop</a> <a id="sonos_forward" href="#" data-role="button" class="ui-disabled">Forward</a>';
						break;
				}
				 
			} else {
				switch (data.status){
					case 1: 
						html+='<a id="sonos_back" href="#" data-role="button"'+(button_rw ? '':' class="ui-disabled"')+'>Reverse</a> <a id="sonos_pause" href="#" data-role="button">Pause</a> <a id="sonos_stop" href="#" data-role="button">Stop</a> <a id="sonos_forward" href="#" data-role="button"'+(button_fw ? '':' class="ui-disabled"')+'>Forward</a>';
						break;
					case 2: 
						html+='<a id="sonos_back" href="#" data-role="button"'+(button_rw ? '':' class="ui-disabled"')+'>Reverse</a> <a id="sonos_play" href="#" data-role="button">Play</a> <a id="sonos_stop" href="#" data-role="button">Stop</a> <a id="sonos_forward" href="#" data-role="button"'+(button_fw ? '':' class="ui-disabled"')+'>Forward</a>';
						break;
				}			
			}
			
			//<a id="sonos_back" href="#" data-role="button">Reverse</a> <a id="sonos_pause" href="#" data-role="button">Pause</a> <a id="sonos_stop" href="#" data-role="button">Stop</a> <a id="sonos_play" href="#" data-role="button">Play</a> <a id="sonos_forward" href="#" data-role="button">Forward</a> 
			
			html+='</div><div align="left"><a id="sonos_mute" href="#" data-role="button" data-inline="true">'+(button_muteOn ? "Unmute": "Mute")+'</a><input type="range" name="sonos_slider" id="sonos_slider" value="'+data.volume+'" min="0" max="100" step="1" '+(button_muteOn ? ' class="ui-disabled"':'')+' data-highlight="true" /></div></div>';
			
			//Update the widget
			widget.infobox("option",{
				"priority" : 2,
				"content" : html,
				"headline" : headline,
				"subheadline" : subheadline,
				"contentpadding" : false,
				"service" : service,
				"refreshInterval" : 2000
			});
			
			//Set the button functions
			$("#sonos_back").click(function(event){
    			$.post(url, { service: service, method: "previous" }, function(data){hac.getData(service);});
     			event.preventDefault();
   			});
			$("#sonos_pause").click(function(event){
    			$.post(url, { service: service, method: "pause" }, function(data){hac.getData(service);});
     			event.preventDefault();
   			});
			$("#sonos_stop").click(function(event){
    			$.post(url, { service: service, method: "stop" }, function(data){hac.getData(service);} );
     			event.preventDefault();
   			});
			$("#sonos_play").click(function(event){
    			$.post(url, { service: service, method: "play" }, function(data){hac.getData(service);} );
     			event.preventDefault();
   			});
			$("#sonos_forward").click(function(event){
    			$.post(url, { service: service, method: "next" }, function(data){hac.getData(service);} );
     			event.preventDefault();
   			});
			
			//Volume settings
			$("#sonos_mute").click(function(event){
				$.post(url, { service: service, method: "setMute", params: {mute: (button_muteOn? 0 : 1)} }, function(data){hac.getData(service);} );
     			event.preventDefault();
   			});
			$("#sonos_slider").on('slidestop', function(event) { 
				$.post(url, { service: service, method: "setVolume", params: {volume: $("#sonos_slider").val()} }, function(data){hac.getData(service);} );
     			event.preventDefault();
			});
}
EOT;

	function play(){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->Play();
		return true;
	}
	function next(){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->Next();
		return true;
	}
	function previous(){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->Previous();
		return true;
	}
	function pause(){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->Pause();
		return true;
	}
	function stop(){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->Stop();
	}
	function isMuted(){
		$sonos = new PHPSonos(kvp_get("ip"));
		return $sonos->getMute();
	}
	//1: PLAYING, 2: PAUS, 3: STOPPED
	function getStatus(){
		$sonos = new PHPSonos(kvp_get("ip"));
		return $sonos->GetTransportInfo();
	}
	function setMute($val){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->SetMute($val["mute"]);
		return true;
	}
	function setVolume($val){
		$sonos = new PHPSonos(kvp_get("ip"));
		$sonos->SetVolume($val["volume"]);
		return true;
	}
}


?>
