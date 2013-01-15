<?php

class Google_Helper {
	
	/**
	 * Will retrieve the initial device code that is needed for connecting the service to 
	 * a Google user profile. As described at https://developers.google.com/accounts/docs/OAuth2InstalledApp
	 * 
	 * @param String $client_id The client id as registered in Google API console
	 * @param String $scope The scope for the device registration. Depending on the service to be used
	 * @return boolean|array False if the request failed or an array with device_code, user_code and verification_url
	 */
	public static function getDeviceCode($client_id, $scope){
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
	public static function getAccessToken($client_id, $client_secret, $device_code){

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
