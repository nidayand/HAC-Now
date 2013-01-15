<?php

class Google_Helper {

	/**
	 * Verifies that the service has an device code and if not it will retrieve necessary
	 * registration details for manual registration of the service to an device (the server). The caller needs to
	 * save the array details for further processing (to be used in creation of an access token)
	 * @param array $setup_data Needs to contain client_id and scope
	 * @return boolean|array False if the request fails or an array with the data user_code, verification_url and device_code
	 */
	public static function verifyDeviceCode($setup_data){
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
			$device_code_call = getDeviceCode($setup_data["client_id"], $setup_data["scope"]);
			if (!$device_code_call)
				//Failed to retrieve the information. Quitting
				return false;

			//Store the information
			$setup_data["user_code"]= $device_code_call["user_code"];
			$setup_data["verification_url"]= $device_code_call["verification_url"];
			$setup_data["device_code"]= $device_code_call["device_code"];
		}
		return $setup_data;
	}

	/**
	 * Verifies that a valid access token exists. If not it initiates the retrieval of a token. The caller needs to
	 * save the array details for further processing (access token is needed in subsequent calls)
	 * @param array $setup_data Needs to contain client_id, client_secret and device_code
	 * @return boolean|array False if creation of an access_token fails or an array with the created access token
	 */
	public static function verifyAccessToken($setup_data){
		/*
		 * Check for access token
		*/
		$access_token = @$setup_data["access_token"];
		if (!$access_token)
			debug ("Missing Access token. Retrieves from Google...");
		if (!$access_token){
			$access_token = getAccessToken($setup_data["client_id"], $setup_data["client_secret"], $setup_data["device_code"]);

			if (!$access_token)
				return false;

			//Convert to json
			$access_token=json_encode($access_token);

			//Store the information
			$setup_data["access_token"] = $access_token;
		}
		debug ("Access token: ".$access_token);
		return $setup_data;
	}

	/**
	 * Will retrieve the initial device code that is needed for connecting the service to
	 * a Google user profile. As described at https://developers.google.com/accounts/docs/OAuth2InstalledApp
	 *
	 * @param String $client_id The client id as registered in Google API console
	 * @param String $scope The scope for the device registration. Depending on the service to be used
	 * @return boolean|array False if the request failed or an array with device_code, user_code and verification_url
	 */
	private static function getDeviceCode($client_id, $scope){
		/* Device code does not exist, start to register server
		 This will only happen once as next time the device code will remain in the database
		*/

		//Do the call to Google
		$url = 'https://accounts.google.com/o/oauth2/device/code';
		$fields = array(
				'client_id'=>urlencode($client_id),
				'scope'=>urlencode($scope)
		);
		$fields_string="";
		foreach($fields as $key=>$value) {
			$fields_string .= (strlen($fields_string)==0? "":"&").$key.'='.$value;
		}

		//open connection
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch,CURLOPT_POST,count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$result = curl_exec($ch);
		curl_close($ch);

		if($result===false) //Stop if it fails
			return false;

		//Convert response to array and save it to the database
		$result = json_decode($result, true);

		return array("device_code"=>$result["device_code"], "user_code"=>$result["user_code"], "verification_url"=>$result["verification_url"]);
	}
	/**
	 * Will retrieve the initial access token to later to be used with the Google PHP API. The method is necessary
	 * as the Google API requires a browser and a human interaction.
	 * As described at https://developers.google.com/accounts/docs/OAuth2InstalledApp
	 *
	 * @param String $client_id As registered in Google API console
	 * @param String $client_secret As registered in Google API console
	 * @param String $device_code As registered in Google API console
	 * @return boolean|array False if the request fails and an array with the access token if successful
	 */
	private static function getAccessToken($client_id, $client_secret, $device_code){

		//Do the call to Google
		$url = 'https://accounts.google.com/o/oauth2/token';
		$fields = array(
				'client_id'=>urlencode($client_id),
				'client_secret'=>urlencode($client_secret),
				'code'=>urlencode($device_code),
				'grant_type'=>urlencode('http://oauth.net/grant_type/device/1.0')
		);
		$fields_string="";
		foreach($fields as $key=>$value) {
			$fields_string .= (strlen($fields_string)==0? "":"&").$key.'='.$value;
		}

		//open connection
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch,CURLOPT_POST,count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$result = curl_exec($ch);
		curl_close($ch);

		if($result===false) //Stop if it fails
			return false;

		//Convert response to array
		$result = json_decode($result, true);

		//Check if user has gone to the verification URL and tied to Google Account
		if (!isset($result["access_token"])){
			//debug(print_r($result,true));
			return false;
		} else {
			//Make sure that created is added. Needed by the Google PHP API
			if (!isset($result["created"])){
				$result["created"]=time();
			}
		}
		//Convert response to array
		return $result;
	}
}


?>
