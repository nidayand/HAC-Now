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
	
}


?>
