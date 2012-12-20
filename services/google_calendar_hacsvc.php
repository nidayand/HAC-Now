<?php
/**
 * Retrieves calendar information from Google
 * 
 * @author se31139
 *
 */
namespace google_calendar_hacsvc {
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
				array("key"=>"today", "value"=>"idag", "mandatory"=>1,"description"=>"Today text"),
				array("key"=>"tomorrow", "value"=>"i morgon", "mandatory"=>1,"description"=>"Tomorrow text"),
				array("key"=>"curr_date", "value"=>"Dagens datum", "mandatory"=>1,"description"=>"Todays date text"),
				array("key"=>"curr_date_format", "value"=>"dddd, mmmm d", "mandatory"=>1,"description"=>"Dateformat of current date output")
		);
	}
	function setup_data(){
		return array(
				array("key"=>"client_id", "value"=>null, "mandatory"=>1,"description"=>"Go to https://code.google.com/apis/console/ and generate a 'Client ID for installed applications'. Sign up for the Calender API service"),
				array("key"=>"client_secret", "value"=>null, "mandatory"=>1,"description"=>"Same as for client_id. The client_secret is needed for creating a new token if the previous one has expired"),
				array("key"=>"scope", "value"=>"https://www.googleapis.com/auth/calendar.readonly", "mandatory"=>1,"description"=>"The scope of the request. Not necessary to change this value"),
				array("key"=>"user_code", "value"=>null, "mandatory"=>2,"description"=>"When client_id, client_secret and scope has been set and the cron.php has been called the first time, the user_code value will be set. Go to verficiation_url and use the user_code to authorize the server to use your account"),
				array("key"=>"verification_url", "value"=>null, "mandatory"=>2,"description"=>"See description for user_code"),
				array("key"=>"ignore_calendars", "value"=>null, "mandatory"=>1,"description"=>"Calendars to be ignored in the import. JSON format: [\"Svenska helgdagar\", \"Call log\", \"Week Numbers\"]"),
				array("key"=>"timezone", "value"=>"Europe/Stockholm", "mandatory"=>1,"description"=>"Local timezone"),
				array("key"=>"days", "value"=>"5", "mandatory"=>1,"description"=>"Days of data to retrive")
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
		//Settings
		$ignore_calendars = json_decode($setup_data["ignore_calendars"], true); //Names of calendars to skip
		$timezone = $setup_data["timezone"];
		$daysToRetrieve = $setup_data["days"];
			
		/* 
		 * Check if an device code exists. I.e. if the server has an ID 
		 * to use a Google Account 
		 * */
		$device_code=@$setup_data["device_code"];
		if ($device_code)
			debug ("Device code: ".$device_code);
		else 
			debug ("Missing device code registration. Retrieves data from Google for registration...");

		if (!$device_code && !getDeviceCode($setup_data)){
				//Failed to retrieve the information. Quitting
				return false;
		}	

		/*
		 * Check for access token
		 */
		$access_token = @$setup_data["access_token"];
		if ($access_token)
			debug ("Access token: ".$access_token);
		else 
			debug ("Missing Access token. Retrieves from Google...");
		
		if (!$access_token){
			$access_token = getAccessToken($setup_data);
			if (!$access_token)
				return false;
		}
		
		/*
		 * Get the calendar data
		 */
		debug("Retrieving the list of calendars");
		$url = "https://www.googleapis.com/calendar/v3/users/me/calendarList?access_token=";
			
		$callist = getGoogle($url.$access_token);
		$error = googleRequestError($callist);
			
		if ($error === 401){
			$access_token = refreshAccessToken($setup_data);
			$callist = getGoogle($url.$access_token);
			$error = googleRequestError($callist);
			if ($error !== false){
				debug ("Failed to retrieve calendar list... Quitting...");
				return false;
			}
		}
		$calendar = array();
		for ($i=0; $i<sizeof($callist["items"]); $i++){
			$summary = $callist["items"][$i]["summary"];
			if (in_array($summary, $ignore_calendars)){
				debug ("Skipping calendar: ".$summary);
				continue;
			}
			$id = $callist["items"][$i]["id"];
			$tz = $callist["items"][$i]["timeZone"];
			$tzdiff = getTimezoneDiffFromUTC($tz);
			array_push($calendar, array("summary"=>$summary, "id"=>$id, "timezone"=>$tz, "timezonediff"=>$tzdiff));
		}
				
		/* Define start time in Google format
		 Start time should be NOW but formatted in e.g. 2012-08-08T06:00:00+02:00
		*/
		$timzoneDiff = getTimezoneDiffFromUTC($timezone);
		$timeMin = subStr(date("c"), 0, 19).$timzoneDiff;
		debug ("Start time: ".$timeMin);	
		
		//Define stop time in Google format
		$timeMax = subStr(date("c", strtotime("+".$daysToRetrieve." day")), 0, 19).$timzoneDiff;
		debug ("End time: ".$timeMax);
		
		/* Get events from all calendars
		 Info: https://developers.google.com/google-apps/calendar/v3/reference/events/list
		*/
		debug("Retrieving events in the calendars");
		$events = array();
		for ($i=0; $i<sizeof($calendar); $i++){
			$id = $calendar[$i]["id"];
			$calendarName = $calendar[$i]["summary"];
			$url = "https://www.googleapis.com/calendar/v3/calendars/".$id."/events?access_token=".urlencode($access_token)."&maxResults=10&singleEvents=true&orderBy=startTime&timeMin=".urlencode($timeMin)."&timeMax=".urlencode($timeMax);
		
			$response = getGoogle($url);
			
			//debug ("Response: ".print_r($response, true));
			if (googleRequestError($response)!==false) //Failed to retrieve data
				return false;
		
			if (!isset($response["items"]))	//Check if there are any events within the timeframe
				continue;
				
			for ($j=0;$j<sizeof($response["items"]);$j++){
				//Skip multi-day occurances that have already started
				if (isset($response["items"][$j]["start"]["dateTime"])){
					$jS = strtotime($response["items"][$j]["start"]["dateTime"]);
					$jE = strtotime($response["items"][$j]["end"]["dateTime"]);
					$dayevent = 0;
				} else {
					$jS = strtotime($response["items"][$j]["start"]["date"]);
					$jE = strtotime($response["items"][$j]["end"]["date"]);
					$dayevent = 1;
				}
				if ($jS<time())
					continue;
				//Add to array
				array_push($events, array("calendarName"=>$calendarName,"title"=>$response["items"][$j]["summary"], "start"=>$jS,"end"=>$jE, "dayevent"=>$dayevent));
			}
		}	
		/* Sort the array */
		usort($events, "google_calendar_hacsvc\sortevents");

		return $events;
	}
	const javascript = <<<EOT
	this.formatTime = function (comma, dayevent, time){
		return (dayevent ? "" : (comma ? ", ": "")+dateFormat(time, "HH:MM"));
	}	
	this.content = function (widget, ui, data){
		var title = [];
		var subtitle ="";
		var priority = 0;
			
		var tomorrow = new Date();
		tomorrow = new Date(tomorrow.getFullYear(), tomorrow.getMonth(), tomorrow.getDate()+1, 0,0,0,0);
		var dayafterTomorrow = new Date();
		dayafterTomorrow = new Date(dayafterTomorrow.getFullYear(), dayafterTomorrow.getMonth(), dayafterTomorrow.getDate()+2, 0,0,0,0);

		var output = "";
		rows = false;
		for (var i=0; i<data.length; i++){
			var start = new Date(data[i].start*1000); //PHP returns in seconds
			var end = new Date(data[i].end*1000);
			var dayevent = data[i].dayevent == 1 ? true : false;
			var subject = data[i].title;
			var calendar = data[i].calendarName;
			if (start<tomorrow){
				var temptitle = "@ (@)<br />"+ui.today+"@";
				temptitle = js.stringChrParams(temptitle, "@", [subject,calendar, this.formatTime(true, dayevent, start)]);
				title.push(temptitle);
				priority = 2;
			} else if (start<dayafterTomorrow){
				var temptitle = "@ (@)<br />"+ui.tomorrow+"@";
				temptitle = js.stringChrParams(temptitle, "@", [subject,calendar, this.formatTime(true, dayevent, start)]);
				title.push(temptitle);
				priority = priority > 0 ? priority : 1;
			} else {
				html="";
				if (!rows){
					if (title.length==0){
						var temptitle = "@ (@)<br />@@";
						temptitle = js.stringChrParams(temptitle, "@", [subject,calendar,dateFormat(start, "dddd dd mmmm"), this.formatTime(true, dayevent, start)]);
						title.push(temptitle);
						priority = 0;		
					} else {
						rows = true;
						html = '<table width="100%" border="0">';
						html +='<tr><td valign="top">@</td><td valign="top">@</td><td valign="top">@</td><td valign="top">@</td><td valign="top">(@)</td></tr>';
						html = js.stringChrParams(html, "@", [dateFormat(start, "ddd"),dateFormat(start, "dd mmm"), this.formatTime(false, dayevent, start), subject, calendar]);						
					}
				} else {
					html='<tr><td valign="top">@</td><td valign="top">@</td><td valign="top">@</td><td valign="top">@</td><td valign="top">(@)</td></tr>';
					html = js.stringChrParams(html, "@", [dateFormat(start, "ddd"),dateFormat(start, "dd mmm"), this.formatTime(false, dayevent, start), subject, calendar]);
				}
				if (i == (data.length-1) && rows)
					html+='</table>';
				output+=html;
			}
		}
		
		//Add current date information in the footer
		var today = new Date();
		output+="<div align='center'>"+ui.curr_date+": <strong>"+dateFormat(today, ui.curr_date_format)+"</strong></div>";
		
		//Update the widget
		widget.infobox("option",{
			"priority" : priority,
			"content" : output,
			"headline" : title,
			"subheadline" : null,
			"contentpadding" : true
		});	
	}
EOT;
	
	/**
	 * Retrieves device code for registration of server to be using
	 * the Google account for the Calendar API
	 * @param Array $setup_data Configuration details
	 * @return boolean
	 */
	function getDeviceCode($setup_data){
		/* Device code does not exist, start to register server
		 This will only happen once as next time the device code will remain in the database
		*/
		$client_id = $setup_data["client_id"];
		$scope = $setup_data["scope"];
		
		//Do the call to Google
		$url = 'https://accounts.google.com/o/oauth2/device/code';
		$fields = array(
				'client_id'=>urlencode($client_id),
				'scope'=>urlencode($scope)
		);
		$fields_string="";
		foreach($fields as $key=>$value) { $fields_string .= (strlen($fields_string)==0? "":"&").$key.'='.$value; }
		
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
		
		//Store the information
		kvp_set("device_code",$result["device_code"]);
		kvp_set("user_code",$result["user_code"]);
		kvp_set("verification_url",$result["verification_url"]);

		return true;
	}
	
	/**
	 * Retrieves the necessary access token
	 * @param Array $setup_data Configuration
	 * @return boolean|mixed
	 */
	function getAccessToken($setup_data){
		$client_id = $setup_data["client_id"];
		$client_secret = $setup_data["client_secret"];
		$device_code = $setup_data["device_code"];
		
		//Do the call to Google
		$url = 'https://accounts.google.com/o/oauth2/token';
		$fields = array(
				'client_id'=>urlencode($client_id),
				'client_secret'=>urlencode($client_secret),
				'code'=>urlencode($device_code),
				'grant_type'=>urlencode('http://oauth.net/grant_type/device/1.0')
		);
		$fields_string="";
		foreach($fields as $key=>$value) { $fields_string .= (strlen($fields_string)==0? "":"&").$key.'='.$value; }
		
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
			debug(print_r($result,true));
			return false;
		} 
		
		//Save the access_token and the refresh_token
		$access_token=$result["access_token"];
		kvp_set("access_token",$result["access_token"]);
		kvp_set("refresh_token",$result["refresh_token"]);
		
		//Return the token
		return $access_token;
		
	}
	
	function postGoogle($url, $fields){
		$fields_string="";
		foreach($fields as $key=>$value) { $fields_string .= (strlen($fields_string)==0? "":"&").$key.'='.$value; }
		debug ("POST url: ".$url);
		debug ("POST fields: ".$fields_string);
		//open connection
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch,CURLOPT_POST,count($fields));
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$result = curl_exec($ch);
		$errorCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$errorCode = (int) $errorCode;
		debug ("Error code: ".$errorCode);
		if ($errorCode == 401 || $errorCode == 0 || $errorCode == 503){ //0 = failed to retrieve data
			return $errorCode;
		}
		return json_decode($result, true);
	}
	function googleRequestError($request){
		if (is_int($request))
			return $request;
		else
			return false;
	}
	function getGoogle($url){
		debug ("GET url: ".$url);
		$fields_string="";
	
		//open connection
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($ch,CURLOPT_HTTPGET,true);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$result = curl_exec($ch);
		$errorCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$errorCode = (int) $errorCode;
		debug ("Error code: ".$errorCode);
		if ($errorCode == 401 || $errorCode == 0 || $errorCode == 503){ //0 = failed to retrieve data
			return $errorCode;
		}
		curl_close($ch);
		return json_decode($result, true);
	}
	function refreshAccessToken($setup_data){
		$client_id = $setup_data["client_id"];
		$client_secret =$setup_data["client_secret"];
		$refresh_token = $setup_data["refresh_token"];
		$response = postGoogle('https://accounts.google.com/o/oauth2/token', array(
				'client_id'=>urlencode($client_id),
				'client_secret'=>urlencode($client_secret),
				'refresh_token'=>urlencode($refresh_token),
				'grant_type'=>urlencode('refresh_token')
		));

		if (googleRequestError($response) !== false){
			debug ("Failed to refresh token... Quitting");
			return false;
		}
		kvp_set("access_token",$response["access_token"]);
		debug ("Updated access token: ".$response["access_token"]);
		return $response["access_token"];
	}
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
	function sortevents($a, $b){
		return ($a["start"] - $b["start"]);
	}	
}
?>
