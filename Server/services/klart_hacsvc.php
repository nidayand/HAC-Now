<?php
/**
 * Retrieves the current weather conditions from klart.se
 * 
 * Setup:
 * 1. Run Server/config.setup in a browser
 * 2. Fill in the mandatory parameters
 * 3. Run the Server/cron.php
 *
 * @author nidayand
 *
 */
namespace klart_hacsvc {
	use debug;

	/**
	 * Helper functions
	 */
	function kvp_get($key){
		return \kvp_get($key,__NAMESPACE__);
	}
	function kvp_set($key, $value){
		\kvp_set($key, $value, "data", __NAMESPACE__);
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
				array("key"=>"title", "value"=>"V&auml;der", "mandatory"=>1,"description"=>"The title of the infobox"),
				array("key"=>"humidity", "value"=>"Humidity", "mandatory"=>1,"description"=>"Humidity text"),
				array("key"=>"wind", "value"=>"Wind", "mandatory"=>1,"description"=>"Wind text")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"city_id", "value"=>"", "mandatory"=>1,"description"=>"The Klart.se identifier of the location. Goto http://www.klart.se, find the location that you wish to display. Look at the source code of that page and search for the integer number of \"city_id\"")
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

		//Get the data from Klart.se
		$url = 'http://www.klart.se/api/weatherapi/get_weather/'.kvp_get("city_id").'/1/sv/2';
		debug("POST url: ".$url);
		$fields_string="v=2.0&i=0&android=1.0&sun=1";
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$result = curl_exec($ch);
		curl_close($ch);
		debug("Retrieved data from klart.se");
		$result=json_decode($result, true);
			
		//Get weather info
		$weatherFull = explode("\n", $result["weather"]);

		/*Iterate through the result to get the data
		 Here follows the datastructure:
		1346104800 (timestamp): wprdx#19 (max temp);11 (min temp);7 (m/s);S (riktning);d310 (ikon);83 (molnighet);0 (% åska);3 (UV);0.2 (mm regn);3;06:07 (sol upp);20:13 (sol ner);92 (månen belyst)
		wpp#10 (klockan);17.9 (temperatur);6 (m/s vind);S (vindriktning);d200;0 (mm nederbörd);2;73 (% Molnighet);0;66 ( %RH);1017 (lufttryck);5;10 (m/s vindbyar);18 (temp avrundat)
		*/
			
		$weatherDays = array();
		$weatherCurr = null;
		for($i=0; $i<sizeof($weatherFull)-1; $i++){
			$dayStringList = explode("|", $weatherFull[$i]);
			//Check for current conditions
			if(strpos($weatherFull[$i], "wcc")>-1){
				$currData = explode(";",$dayStringList[1]);
				$weatherCurr = array("temp"=>$currData[1], "wind"=>$currData[2], "windDirection"=>$currData[3], "image"=>getImageURL($currData[4]), "humidity"=>$currData[6]);

			}
			$dayData = explode(";",$dayStringList[0]);

			$time = explode(":", $dayData[0]);
			$maxTemp = explode("#", $time[1]);
			$maxTemp = $maxTemp[1];
			$time = $time[0];

			$minTemp = $dayData[1];
			$wind = $dayData[2];
			$windDirection = $dayData[3];
			$image = $dayData[4];
			$cloud = $dayData[5];
			$thunder = $dayData[6];
			$uvindex = $dayData[7];
			$rain = $dayData[8];
			$sunUp = $dayData[10];
			$sunDown = $dayData[11];

			array_push($weatherDays, array("time"=>$time, "tempMax"=>$maxTemp, "tempMin"=>$minTemp, "wind"=>$wind, "windDirection"=>$windDirection, "image"=>getImageURL($image), "rain"=>$rain));
		}

		$current_conditionA = array('condition'=>"",
				'temp'=>$weatherCurr["temp"],
				'humidity'=>$weatherCurr["humidity"],
				//Modified to use private 1-wire data
				//'temp'=>$temp,
				//'humidity'=>$rh,
				'wind'=>$weatherCurr["windDirection"].", ".$weatherCurr["wind"]." m/s",
				'icon'=>$weatherCurr["image"]);
		$forecastA = array();

		for ($i = 0; $i <= 3; $i++){
			array_push($forecastA, array('day'=>$weatherDays[$i]["time"],
			'condition'=>"",
			'low'=>$weatherDays[$i]["tempMin"],
			'high'=>$weatherDays[$i]["tempMax"],
			'icon'=>$weatherDays[$i]["image"],
			'rain'=>$weatherDays[$i]["rain"],
			'wind'=>$weatherDays[$i]["wind"]." m/s"));
		}
		$all = array ('current'=>$current_conditionA,
				'forecast'=>$forecastA
		);

		return $all;


	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var html = '<table width="100%" border="0" class="boxtext"> <tr> <td colspan="2" width="50%"><center><img src="?" width="76" height="76" /><table class="boxtext"><tr><td>'+ui.humidity+': ?%</td></tr><tr><td>'+ui.wind+': ?</td></tr></table></center></td> <td colspan="2" class="boxtextlarge"><strong>?&deg;</strong></td> </tr> <tr> <td align="center" class=""> <table > <tr align="center"><td>?</td></tr> <tr align="center"><td><img src="?" width="51" height="51" /></td></tr> <tr align="center"><td><strong>?&deg;</strong> / ?&deg;</td></tr> <tr align="center"><td>?mm</td></tr> </table> </td> <td align="center" class="tableleftsolid"> <table > <tr align="center"><td>?</td></tr> <tr align="center"><td><img src="?" width="51" height="51" /></td></tr> <tr align="center"><td><strong>?&deg;</strong> / ?&deg;</td></tr> <tr align="center"><td>?mm</td></tr> </table> </td> <td align="center" class="tableleftsolid"> <table > <tr align="center"><td>?</td></tr> <tr align="center"><td><img src="?" width="51" height="51" /></td></tr> <tr align="center"><td><strong>?&deg;</strong> / ?&deg;</td></tr> <tr align="center"><td>?mm</td></tr> </table> </td> <td align="center" class="tableleftsolid"> <table > <tr align="center"><td>?</td></tr> <tr align="center"><td><img src="?" width="51" height="51" /></td></tr> <tr align="center"><td><strong>?&deg;</strong> / ?&deg;</td></tr> <tr align="center"><td>?mm</td></tr> </table> </td> </tr> </table>';
		
		html = js.stringChrParams(html,'?',[data.current.icon, data.current.humidity, data.current.wind, data.current.temp]);
		for(var i=0;i<=3;i++){
			html = js.stringChrParams(html,'?',[dateFormat(new Date(data.forecast[i].day*1000), "ddd"),data.forecast[i].icon,data.forecast[i].high,data.forecast[i].low,data.forecast[i].rain]);
		}
			
		//Update the widget
		widget.infobox("option",{
			"priority" : 0,
			"content" : html,
			"headline" : ui.title,
			"subheadline" : null,
			"contentpadding" : false
		});			
	}
EOT;
	
	/** Get the complete image url
	 * @param unknown $name
	 * @return string
	 */
	function getImageURL($name){
		$imageFile = $name.".png";
		return "http://dfqhb50p6jl8.cloudfront.net/img/icons/".$imageFile;
	}
}


?>
