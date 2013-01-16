<?php
/**
 * Retrieves positioning information from Google from a specific account. An infobox will be
 * display if the user is driving above a certain speed limit and if the accuracy is below
 * a specified value
 * 
 * Setup:
 * 1. Go to Google API Console: https://code.google.com/apis/console/??
 * 2. Create a project (if you don't already have one) and enable the Latitude API service
 * 3. Create an "Client ID for installed applications" under API Access.
 * 3. Run Server/config.setup in a browser
 * 4. Fill in the mandatory parameters (device_code, user_code, verification_url will be populated automatically)
 * 5. Run the Server/cron.php
 * 6. Refresh Server/config.setup in the browser
 * 7. Go to the verification_url in the browser
 * 8. Log in with the to-be-tied user account from where Calendar data will be retrieved
 * 9. Use the user_code to tie the account to the service
 * 10. Run the Server/cron.php
 *
 * @author nidayand
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
				array("key"=>"title", "value"=>"Navigating with the car", "mandatory"=>1,"description"=>"The title of the infobox"),
				array("key"=>"subtitle", "value"=>"Last seen %1 doing %2 km/h", "mandatory"=>1,"description"=>"Subtitle where %1 is date/time and %2 is speed"),
				array("key"=>"date_format", "value"=>"\"kl\" H:MM:ss \"den\" d mmm", "mandatory"=>1,"description"=>"Date format for displaying when the last report was recorded. See http://blog.stevenlevithan.com/archives/date-time-format for available options")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"client_id", "value"=>null, "mandatory"=>1,"description"=>"Go to https://code.google.com/apis/console/ and generate a 'Client ID for installed applications'. Sign up for the Latitude API service"),
				array("key"=>"client_secret", "value"=>null, "mandatory"=>1,"description"=>"Same as for client_id. The client_secret is needed for creating a new token if the previous one has expired"),
				array("key"=>"scope", "value"=>"https://www.googleapis.com/auth/latitude.current.best", "mandatory"=>1,"description"=>"The scope of the request. Not necessary to change this value"),
				array("key"=>"device_code", "value"=>null, "mandatory"=>2,"description"=>"Device code generated. Internal usage only"),
				array("key"=>"user_code", "value"=>null, "mandatory"=>2,"description"=>"Code to be used with the URL for registration to an account"),
				array("key"=>"verification_url", "value"=>null, "mandatory"=>2,"description"=>"URL for authentication"),
				array("key"=>"speed_limit", "value"=>"5", "mandatory"=>1,"description"=>"If speed limit is above this value (in m/s) the map will be displayed. Use Google to translate from e.g. km/h to m/s via a simpel search - 20 km/h to m/s"),
				array("key"=>"accuracy_limit", "value"=>"30", "mandatory"=>1,"description"=>"The map will only be displayed if the accuracy of the position is less that the value"),
				array("key"=>"speed_conv_js", "value"=>"3.6", "mandatory"=>1,"description"=>"Conversion value for m/s to desired value. m/s->km/h = 3.6,  m/s->mph = 2.23693629")
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
		$setup_data_new = \Google_Helper::verifyDeviceCode($setup_data);
		if ($setup_data_new){
			//Store the information
			if (array_diff($setup_data, $setup_data_new)){
				kvp_set("device_code",$setup_data_new["device_code"]);
				kvp_set("user_code",$setup_data_new["user_code"]);
				kvp_set("verification_url",$setup_data_new["verification_url"]);
			}

			$setup_data = $setup_data_new;
		} else {
			debug("Failed to retrieve device_code..");
			return false;
		}

		/*
		 * Check for access token
		*/
		$setup_data_new = \Google_Helper::verifyAccessToken($setup_data);
		if ($setup_data_new){
			//Store the information
			kvp_set("access_token",$setup_data_new["access_token"]);

			$setup_data = $setup_data_new;
		} else {
			debug("Failed to retrieve access_token..");
			return false;
		}
			
		$client = new \Google_Client();
		$client->setApplicationName('HAC Latitude Speed');
		$client->setClientId($setup_data["client_id"]);
		$client->setClientSecret($setup_data["client_secret"]);
		$client->setScopes(array($setup_data["scope"]));
		$client->setAccessToken($setup_data["access_token"]);

		//Add calendar
		$latitude = new \Google_LatitudeService($client);

		if ($client->getAccessToken()) {
			$currentLocation = $latitude->currentLocation->get(array("granularity"=>"best"));

			debug("Current location: ".json_encode($currentLocation));

			// We're not done yet. Remember to update the cached access token.
			// Remember to replace $_SESSION with a real database or memcached.
			kvp_set("access_token", $client->getAccessToken());

			/*
			 * Only return if there is a speed value and it is equal or exceeds the predefined value and the
			* accuracy is equal or less than the predefined value
			*/
			if (isset($currentLocation["speed"]) && $currentLocation["speed"]>= $setup_data["speed_limit"]
					&& $currentLocation["accuracy"]<=$setup_data["accuracy_limit"]){
				debug("Location and speed is reported");
				return array("position"=>$currentLocation, "speed_conv_js"=>$setup_data["speed_conv_js"]);
			}
		}
		return null;
	}
	const javascript = <<<EOT
	this.content = function (widget, ui, data){
		var html = '<div align="center"><img src="https://maps.googleapis.com/maps/api/staticmap?center='+data.position.latitude+','+data.position.longitude+'&zoom=12&size=300x200&markers=color:blue%7Clabel:S%7C'+data.position.latitude+','+data.position.longitude+'&sensor=false"/></div>';
		
		//Convert speed
		var speed = parseInt(data.speed_conv_js*data.position.speed);
		var recTime = dateFormat(parseInt(data.position.timestampMs), ui.date_format);
		var subtitle = js.stringChrParams(ui.subtitle,'%1',[recTime]);
		subtitle = js.stringChrParams(subtitle,'%2',[speed]);
		//Update the widget
		widget.infobox("option",{
			"priority" : 1,
			"content" : html,
			"headline" : ui.title,
			"subheadline" : subtitle,
			"contentpadding" : true
		});
	}
EOT;

}
?>
