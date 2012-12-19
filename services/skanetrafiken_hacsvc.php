<?php
/**
 * Retrieves bus trafic information from Skånetrafiken
 *
 * @author se31139
 *
 */
namespace skanetrafiken_hacsvc {
	use debug;

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
				array("key"=>"name", "value"=>"Lerbergets busstation", "mandatory"=>1,"description"=>"Station (subtitle)"),
				array("key"=>"title", "value"=>"Kollektivtrafik", "mandatory"=>1,"description"=>"Title text")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"config", "value"=>'[{"url": "http://www.labs.skanetrafiken.se/v2.2/stationresults.asp?selPointFrKey=84104&selDirection=0","days" : [1,2,3,4,5],"startTime" : "06:00","endTime" : "09:00","rows" : 6, "stopPoint":"B"},{"url": "http://www.labs.skanetrafiken.se/v2.2/stationresults.asp?selPointFrKey=84104&selDirection=0&inpTime=0600&inpDate=[tomorrow]","days" : [0,1,2,3,4],"startTime" : "17:30","endTime" : "23:59","rows" : 15, "stopPoint":"B"}]', "mandatory"=>1,"description"=>"Data that is to be retrieved and when. Use http://www.labs.skanetrafiken.se/v2.2/querystation.asp?inpPointfr=malm%F6 (edit search param) to find the station id. Days is an array of integers where 0=Sunday and 6=Saturday.")
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
		//Get current day of week (0-6) 0 = Sunday
		$dayOfWeek = date("w");
			
		//Get current time
		$time = new \DateTime('now');
			
		//Get config
		$config = json_decode($setup_data["config"], true);
		
		//var_dump($config);
		//Iterate through the list to find a valid time, url, rows
		$rows =0;
		$url = "";
		$stopPoint = null;
		$found = false;
		for($i=0; $i<sizeof($config); $i++){
			$obj = $config[$i];
			if (!in_array($dayOfWeek, $obj["days"])){
				continue;
			}
			$hour = substr($obj["startTime"],0,strpos($obj["startTime"],":"));
			$minute = substr($obj["startTime"],strpos($obj["startTime"],":")+1);
			$startTime = date_time_set(new \DateTime('now'),(int)$hour, (int) $minute);
		
			$hour = substr($obj["endTime"],0,strpos($obj["endTime"],":"));
			$minute = substr($obj["endTime"],strpos($obj["endTime"],":")+1);
			$endTime = date_time_set(new \DateTime('now'),(int)$hour, (int) $minute);
			//Reset time
			$time = new \DateTime('now');
		
			//echo "now: ".$time->format('Y-m-d H:i:s') . "\n";
			//echo "start: ".$startTime->format('Y-m-d H:i:s') . "\n";
			//echo "end: ".$endTime->format('Y-m-d H:i:s') . "\n";
				
			if(($startTime < $time && $time<$endTime)){
				$url = $obj["url"];
				$rows = $obj["rows"];
				$stopPoint = $obj["stopPoint"];
				$found = true;
				break;
			}
		
		}
		if (!$found){
			return null;
		}
		//Replace date if needed
		$strTomorrow = (new \DateTime("tomorrow"))->format("ymd");
		$url = str_replace("[tomorrow]",$strTomorrow, $url);
		echo $url;
			
		$resp = @file_get_contents($url);
			
		if (!$resp){
			debug ("Failed to load url. Quitting...");
			return false;
		}
			
		//Get rid of the soap headers
		$resp = "<".substr($resp, strpos($resp, "GetDepartureArrivalResult"));
		$resp = substr($resp, 0, strpos($resp, "</GetDepartureArrivalResult>"))."</GetDepartureArrivalResult>";
			
		$xml = simplexml_load_string ($resp);
		$timzoneDiff = getTimezoneDiffFromUTC("Europe/Stockholm");
		//Iterate through the list
		$json = array();
		for($i=0; ($i<$rows && $i<sizeof($xml->Lines->Line)); $i++){
			$obj = $xml->Lines->Line[$i];
			$time = ((String) $obj->JourneyDateTime).$timzoneDiff;
			$currStopPoint = (String) $obj->StopPoint;
			//Only want B
			if ($stopPoint !=null && $stopPoint!="" && $currStopPoint != $stopPoint){
				$rows++;
				continue;
			}
			array_push($json, array("number"=>(String) $obj->No, "start"=>strtotime($time), "name"=>(String) $obj->LineTypeName, "towards"=>(String) $obj->Towards, "delay"=>(String) (isset($obj->RealTime->RealTimeInfo)?$obj->RealTime->RealTimeInfo->DepTimeDeviation:0)));
		}
		return $json;
	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var html = '<table width="100%" border="0">';
		
		for(var i=0;i<data.length;i++){
			var obj = data[i];
			var tmp = '<tr><td valign="top">?</td><td valign="top">?</td><td valign="top">?</td><td valign="top">?</td></tr>';
			tmp = js.stringChrParams(tmp,'?',[obj.number, obj.towards, dateFormat(obj.start*1000, "HH:MM"), (obj.delay>0 ? "Sen "+obj.delay+" min" : "")]);
			html+=tmp;
		}
		html+='</table>';
			
		//Update the widget
		widget.infobox("option",{
			"priority" : 0,
			"content" : html,
			"headline" : ui.title,
			"subheadline" : ui.name,
			"contentpadding" : false
		});			
	}
EOT;
	
	/**
	 * Custom functions
	 */
	function getTimezoneDiffFromUTC($timezone){
		//Get timezone info
		$origin_dtz = new \DateTimeZone($timezone);
		$remote_dtz = new \DateTimeZone('UTC');
		$origin_dt = new \DateTime("now", $origin_dtz);
		$remote_dt = new \DateTime("now", $remote_dtz);
		$offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt); //In secs
		$negative = $offset<0 ? true : false;
		$utcdiff_text = ($negative? "-" : "+" ).gmdate("H:i", $negative ? -$offset : $offset);
		//debug("Timezone diff: ".$utcdiff_text);
		return $utcdiff_text;
	}	
}


?>
