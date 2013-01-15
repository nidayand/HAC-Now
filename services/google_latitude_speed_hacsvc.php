<?php
/**
 * Retrieves calendar information from Google
 *
 * @author se31139
 *
 */
namespace google_latitude_speed_hacsvc {
	use debug;
	require_once $root.'/includes/google_client/Google_Client.php';
	require_once $root.'/includes/google_helper.php';
	require_once $root.'/includes/google_client/contrib/Google_LatitudeService.php';

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
				array("key"=>"title", "value"=>"V&auml;der", "mandatory"=>1,"description"=>"The title of the infobox")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"client_id", "value"=>null, "mandatory"=>1,"description"=>"Go to https://code.google.com/apis/console/ and generate a 'Client ID for installed applications'. Sign up for the Calender API service"),
				array("key"=>"client_secret", "value"=>null, "mandatory"=>1,"description"=>"Same as for client_id. The client_secret is needed for creating a new token if the previous one has expired"),
				array("key"=>"scope", "value"=>"https://www.googleapis.com/auth/latitude.current.best", "mandatory"=>1,"description"=>"The scope of the request. Not necessary to change this value"),
				array("key"=>"device_code", "value"=>null, "mandatory"=>2,"description"=>"Device code generated"),
				array("key"=>"user_code", "value"=>null, "mandatory"=>2,"description"=>"Code to be used with the URL for registration to an account"),
				array("key"=>"verification_url", "value"=>null, "mandatory"=>2,"description"=>"URL for authentication")
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

		/*
		 * Check if an device code exists. I.e. if the server has an ID
		* to use a Google Account
		* */
		$device_code=@$setup_data["device_code"];
		if ($device_code)
			debug ("Device code: ".$device_code);
		else
			debug ("Missing device code registration. Retrieves data from Google for registration...");
		if (!$device_code){
			$device_code_call = \Google_Helper::getDeviceCode($setup_data["client_id"], $setup_data["scope"]);
			if (!$device_code_call)
				//Failed to retrieve the information. Quitting
				return false;
			//Store the information
			kvp_set("device_code",$device_code_call["device_code"]);
			kvp_set("user_code",$device_code_call["user_code"]);
			kvp_set("verification_url",$device_code_call["verification_url"]);
				
			$setup_data["device_code"]= $device_code_call["device_code"];
		}

		/*
		 * Check for access token
		*/
		$access_token = @$setup_data["access_token"];
		if (!$access_token)
			debug ("Missing Access token. Retrieves from Google...");
		if (!$access_token){
			$access_token = \Google_Helper::getAccessToken($setup_data["client_id"], $setup_data["client_secret"], $setup_data["device_code"]);

			if (!$access_token)
				return false;
				
			//Convert to json
			$access_token=json_encode($access_token);

			//Store it persistent and in variable
			kvp_set("access_token", $access_token);
			$setup_data["access_token"] = $access_token;
		}
		debug ("Access token: ".$access_token);
			
		$client = new \Google_Client();
		$client->setApplicationName('HAC Latitude Speed');
		$client->setClientId($setup_data["client_id"]);
		$client->setClientSecret($setup_data["client_secret"]);		
		$client->setScopes(array($setup_data["scope"]));

		//Add calendar
		$latitude = new \Google_LatitudeService($client);

		//Set access token
		$client->setAccessToken($access_token);

		if ($client->getAccessToken()) {
			$currentLocation = $latitude->currentLocation->get(array("granularity"=>"best"));
			
			debug("Current location: ".json_encode($currentLocation));

			// We're not done yet. Remember to update the cached access token.
			// Remember to replace $_SESSION with a real database or memcached.
			kvp_set("access_token", $client->getAccessToken());
			
			return $currentLocation;
		}
		return false;
	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var html = '<table width="100%" border="0"><tbody><tr><td><img src="https://maps.googleapis.com/maps/api/staticmap?center='+data.latitude+','+data.longitude+'&zoom=12&size=400x200&markers=color:blue%7Clabel:S%7C'+data.latitude+','+data.longitude+'&sensor=false"/></td></tr></tbody></table>';
			
		//Update the widget
		widget.infobox("option",{
			"priority" : 1,
			"content" : html,
			"headline" : ui.title,
			"subheadline" : null,
			"contentpadding" : true
		});			
	}
EOT;

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
	/**
	 * Sorts the calender entries based on start time
	 * @param object $a
	 * @param object $b
	 * @return number Sorting result
	 */
	function sortevents($a, $b){
		return ($a["start"] - $b["start"]);
	}
}
?>
