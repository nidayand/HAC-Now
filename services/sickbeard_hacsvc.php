<?php
/**
 * Retrieves the current weather conditions
 *
 * @author se31139
 *
 */
namespace sickbeard_hacsvc {
	use debug, urlencode, json_decode;
	require(__NAMESPACE__."/TVDB.php");

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
					array("key"=>"title_txt", "value"=>"TV episodes", "mandatory"=>1,"description"=>"Infobox title text"),
					array("key"=>"season_txt", "value"=>"Season", "mandatory"=>1,"description"=>"Season text"),
					array("key"=>"episode_txt", "value"=>"Episode", "mandatory"=>1,"description"=>"Episode text"),
					array("key"=>"ok_txt", "value"=>"OK", "mandatory"=>1,"description"=>"Button text to close the infobox")
				);
	}
	function setup_data(){
		return array(
					array("key"=>"api", "value"=>null, "mandatory"=>1,"description"=>"Sickbeard API key"),
					array("key"=>"host", "value"=>null, "mandatory"=>1,"description"=>"Sickbeard host address + port. E.g. 192.168.1.1:8081"),
					array("key"=>"tvdbapi", "value"=>null, "mandatory"=>1,"description"=>"TVDB API key. Create an account at http://thetvdb.com and register for a key at http://thetvdb.com/?tab=apiregister")
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
		$tvdb_prepend = "http://www.thetvdb.com/banners/_cache/";
		$imageTargetBase = "services/".__NAMESPACE__."/banners/";
		$imagePathBase = \selfPath().$imageTargetBase;
			
		//Check if there is any old data entries that needs to be prepended
		$resp = getData();
			
		//Set TVDB API key (override setting in TVDB.php)
		define('PHPTVDB_API_KEY', $setup_data["tvdbapi"]);
		
		//Get data
		$data = @file_get_contents("http://".$setup_data["host"]."/api/".$setup_data["api"]."/?cmd=history&limit=10&type=downloaded");
		if (!$data)
			return false;
		$data = json_decode($data, true);
			
		//Get previous check time
		$checked = kvp_get("checkedTime");
			
		//Iterate through downloads
		$tvdbshows = array();
		$latestDate = null;
		for ($i=0; $i<sizeof($data["data"]); $i++){
			$date = strtotime($data["data"][$i]["date"]); //Convert to int
			$episode = $data["data"][$i]["episode"];
			$season = $data["data"][$i]["season"];
			$showName = $data["data"][$i]["show_name"];
			$tvdbid = $data["data"][$i]["tvdbid"];
		
			//Skip if entry has been retrieved previously
			if ($checked && $date<=$checked)
				continue;
		
			//Look for TVDB information
			if (isset($tvdbshows[$tvdbid]))
				$show = $tvdbshows[$tvdbid];
			else {
				$show = @\TV_Shows::findById($tvdbid);
				$tvdbshows[$tvdbid] = $show;
			}
		
			if($show){
				$episodeInfo=$show->getEpisode($season, $episode);
			}
		
			//Download banner image
			$imageFile = $tvdbid.".jpg";
			$imageTarget = $imageTargetBase.$imageFile;
		
			if(!file_exists($imageTarget)){
				$image = file_get_contents($tvdb_prepend.$show->banner);
				file_put_contents($imageTarget, $image);
			}
		
			array_push($resp, array("episode"=>$episode, "season"=>$season, "show"=>$showName, "banner"=>$imagePathBase.$imageFile, "title"=>$episodeInfo->name, "overview"=>$episodeInfo->overview, "date"=>$date));
		}
		//Get the latest time of entries
		if (isset($data["data"][0]["date"])){
			$latestDate=strtotime($data["data"][0]["date"]);
			kvp_set("checkedTime", $latestDate);
		}
			
		//Sort the data based on date, in descending order
		usort($resp, "sickbeard_hacsvc\sortentries");
			
		//Delete if the array is empty else return it
		if (sizeof($resp)===0)
			return null;
		else {

			//Return the array
			return $resp;
		}
	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var service = 'sickbeard_hacsvc';
						
		var html = '<table width="100%" border="0"><tbody>';
		
		for (var i=0; i<data.length; i++){
			var rowStr = '<tr><th><img alt="" src="@" style=""></th></tr><tr><td style="vertical-align: top;"><span class="boxtext"><strong>'+ui.season_txt+' @, '+ui.episode_txt+' @: @</strong></span><br><span class="boxtextnormal">@</span></td></tr>';
			html += js.stringChrParams(rowStr, "@", [data[i].banner, data[i].season, data[i].episode, data[i].title, data[i].overview]);
		}
		
		html += '<tr><td> </td></tr></tbody></table>';
			
		//Update the widget
		widget.infobox("option",{
			"priority" : 1,
			"content" : html,
			"headline" : ui.title_txt,
			"subheadline" : null,
			"contentpadding" : true,
			"buttons" : [{text: ui.ok_txt, exec: function(){
					$.post(serviceFunctionCallURL, { service: service, method: "dismiss" }, function(data){hac.getData(service);} );
				}}]
		});			
	}
EOT;
	

	/**
	 * Sorts the entries in the list based on the
	 * date information of the objects
	 * @param entry $a
	 * @param entry $b
	 * @return number
	 */
	function sortentries($a, $b){
		return ($b["date"] - $a["date"]);
	}
	/**
	 * To be used to acknowledge the information and to clear
	 * the information in the database
	 * @param unknown $array
	 * @return boolean
	 */
	function dismiss($array){
		deleteData();
		return true;
	}
	
}


?>
