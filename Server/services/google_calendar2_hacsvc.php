<?php
/**
 * Retrieves calendar information from Google
 *
 * @author se31139
 *
 */
namespace google_calendar2_hacsvc {
	use debug;
	require_once $root.'/includes/google_client/Google_Client.php';
	require_once $root.'/includes/google_helper.php';
	require_once $root.'/includes/google_client/contrib/Google_CalendarService.php';

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
				array("key"=>"ignore_calendars", "value"=>null, "mandatory"=>1,"description"=>"Calendars to be ignored in the import. JSON format: [\"Svenska helgdagar\", \"Call log\", \"Week Numbers\"]"),
				array("key"=>"timezone", "value"=>"Europe/Stockholm", "mandatory"=>1,"description"=>"Local timezone"),
				array("key"=>"days", "value"=>"5", "mandatory"=>1,"description"=>"Days of data to retrive"),
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

		//Settings
		$ignore_calendars = json_decode($setup_data["ignore_calendars"], true); //Names of calendars to skip
		$timezone = $setup_data["timezone"];
		$daysToRetrieve = $setup_data["days"];

		/*
		 * Check if an device code exists. I.e. if the server has an ID
		* to use a Google Account
		* */
		$setup_data_new = \Google_Helper::verifyDeviceCode($setup_data);
		if ($setup_data_new && array_diff($setup_data, $setup_data_new)){
			//Store the information
			kvp_set("device_code",$setup_data_new["device_code"]);
			kvp_set("user_code",$setup_data_new["user_code"]);
			kvp_set("verification_url",$setup_data_new["verification_url"]);
				
			$setup_data = $setup_data_new;
		} else {
			debug("Failed to retrieve device_code..");
			return false;
		}

		/*
		 * Check for access token
		*/
		$setup_data_new = \Google_Helper::verifyAccessToken($setup_data);
		if ($setup_data_new && array_diff($setup_data, $setup_data_new)){
			//Store the information
			kvp_set("access_token",$setup_data_new["access_token"]);

			$setup_data = $setup_data_new;
		} else {
			debug("Failed to retrieve access_token..");
			return false;
		}

		/*
		 * Authenticated. Do the calendar stuff
		 */
		$client = new \Google_Client();
		$client->setApplicationName('HAC Calendar');
		$client->setClientId($setup_data["client_id"]);
		$client->setClientSecret($setup_data["client_secret"]);
		$client->setScopes(array($setup_data["scope"]));
		$client->setAccessToken($setup_data["access_token"]);
		
		//Add calendar
		$cal = new \Google_CalendarService($client);

		//Get the list of calendars
		$callist = $cal->calendarList->listCalendarList();

		/*
		 * Create a list of the calendars, skipping the
		* ignore_calendars as set by the user
		*/
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
		$filter = array("maxResults"=>10, "singleEvents"=>true, "orderBy"=>"startTime", "timeMin"=>$timeMin, "timeMax"=>$timeMax);
		for ($i=0; $i<sizeof($calendar); $i++){
			$id = $calendar[$i]["id"];
			$calendarName = $calendar[$i]["summary"];
				
			$response = $cal->events->listEvents($id, $filter);

			//var_dump($response);

			if (!isset($response["items"]))	//Check if there are any events within the timeframe
				continue;

			debug("Recieved events");
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
		usort($events, "google_calendar2_hacsvc\sortevents");

		// We're not done yet. Remember to update the cached access token.
		kvp_set("access_token", $client->getAccessToken());
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
