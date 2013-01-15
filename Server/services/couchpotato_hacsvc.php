<?php
/**
 * Presents new downloads of movies via
 * your CouchPotato Server
 *
 * @author se31139
 *
 */
namespace couchpotato_hacsvc {
	use debug, urlencode, json_decode;

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
					array("key"=>"title_txt", "value"=>"Movies", "mandatory"=>1,"description"=>"Infobox title text"),
					array("key"=>"ok_txt", "value"=>"OK", "mandatory"=>1,"description"=>"Button text to close the infobox")
				);
	}
	function setup_data(){
		return array(
				array("key"=>"api", "value"=>null, "mandatory"=>1,"description"=>"Couchpotato API key"),
				array("key"=>"host", "value"=>null, "mandatory"=>1,"description"=>"Host address + port. E.g. 192.168.1.1:5000")
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
		$imageTargetBase = "services/".__NAMESPACE__."/banners/";
		$imagePathBase = \selfPath().$imageTargetBase;
			
		//Check if there is any old data entries that needs to be prepended
		$dList = @$setup_data["infobox_data"] ? json_decode($setup_data["infobox_data"], true) : array();
			
		//Get data
		$url = "http://".$setup_data["host"]."/api/".$setup_data["api"]."/notification.list/";
		debug("Contacting CouchPotato on url: ".$url);
		$data = @file_get_contents($url);
		if (!$data)
			return false;
		$data = json_decode($data, true);
			
		//Get previous check time
		$checked = @$setup_data["checkedTime"] ? $setup_data["checkedTime"] : false;
		
		for ($i=0; $i<sizeof($data["notifications"]); $i++){
			$obj = $data["notifications"][$i];
		
			//Skip if entry has been retrieved previously
			if ($checked && $obj["added"]<=$checked)
				continue;
		//var_dump($obj);
			if ($obj["data"]["notification_type"] != "renamer.after") //Skip info if snatched
				continue;
				
			$title = $obj["data"]["library"]["info"]["titles"][0];
			$plot = $obj["data"]["library"]["info"]["plot"];
			$image = $obj["data"]["library"]["info"]["images"]["poster"][0];
		
			$id = $obj["data"]["library"]["identifier"];
		
			array_push($dList,array("title"=>$title, "plot"=>trim($plot), "banner"=>$image, "added"=>$obj["added"]));
		}
		debug("Current number of not acknowledged: ".sizeof($dList));
		
		//Sort the data based on date, in descending order
		usort($dList, "couchpotato_hacsvc\sortentries");
			
		//Get the latest time of entries
		if (isset($dList[0]["added"]))
			kvp_set("checkedTime", $dList[0]["added"]);
		
		//Delete if the array is empty else return it
		if (sizeof($dList)===0)
			return null;
		else {
		
			//Return the array
			return $dList;
		}
	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var service = 'couchpotato_hacsvc';
		var html = '<table width="100%" border="0"><tbody>';
		for (var i=0; i<data.length; i++){
			var rowStr = '<tr><td><img alt="" src="@" style="width: 154px"></td><td style="vertical-align: top;"><span><strong>@</strong></span><br><span>@</span></td></tr>';
			
			html += js.stringChrParams(rowStr, "@", [data[i].banner, data[i].title, data[i].plot]);
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
		return ($b["added"] - $a["added"]);
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
