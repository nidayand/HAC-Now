<?php
/**
 * Checks room temperatures to a predefined limit and alerts if
 * lower than the limit
 * @author se31139
 *
 */
namespace temp_warning_hacsvc {
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
				array("key"=>"title_txt", "value"=>"Temperaturvarning", "mandatory"=>1,"description"=>"Title text"),
				array("key"=>"subtitle_txt", "value"=>"Raise the temperature", "mandatory"=>1,"description"=>"Subtitle text")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"config", "value"=>null, "mandatory"=>1,"description"=>"Configuration of data. Format: [ {\"database\" :\"1wire\", \"table\" : \"olles rum - temp\", \"id_column\": \"index\", \"column\": \"Temperature\", \"name\" : \"Olles rum\"}, {\"database\" :\"1wire\", \"table\" : \"lisas rum - temp\", \"id_column\": \"index\", \"column\": \"Temperature\", \"name\" : \"Sofies rum\"} ]"),
				array("key"=>"limit", "value"=>"19", "mandatory"=>1,"description"=>"A warning will be displayed if the actual value is less")
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
		//Get config
		$config = json_decode($setup_data["config"], true);
		$limit = $setup_data["limit"];
			
		//Return object
		$json = array();
			
		//Iternate through the list
		for($i=0; $i<sizeof($config); $i++){
			$obj = $config[$i];

			$sql = "select `".$obj["column"]."` from `".$obj["database"]."`.`".$obj["table"]."` where `".$obj["id_column"]."` in (select max(`".$obj["id_column"]."`) from `".$obj["database"]."`.`".$obj["table"]."`) and `".$obj["column"]."`<".$limit;

			$item = dbSelect($sql);
			if (dbIsNotEmpty($item)){
				foreach ($item as $row) {
					array_push($json, array("name"=>$obj["name"],
					"temperature"=>$row[($obj["column"])]));
				}
			}
		}
			
		//Delete if the array is empty else return it
		if (sizeof($json)===0)
			return null;
		else {
			//Return the array
			return array("limit"=>$limit, "entries"=>$json);
		}
	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var html = '<table width="100%" border="0"><tbody>';
		for (var i=0; i<data.entries.length; i++){
			var rowStr = '<tr><td>@</td><td>@&deg;</td></tr>';
			html += js.stringChrParams(rowStr, "@", [data.entries[i].name, (Math.round(data.entries[i].temperature*10)/10)]);
		}
	
		html += '<tr><td> </td></tr></tbody></table>';
		
		//Update the widget
		widget.infobox("option",{
			"priority" : 2,
			"content" : html,
			"headline" : ui.title_txt,
			"subheadline" : ui.subtitle_txt
		});	
	}
EOT;
}
?>
