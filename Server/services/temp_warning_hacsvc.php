<?php
/**
 * Checks room temperatures to a predefined limit and alerts if lower than the limit.
 * Requires the data to be available in the same MySQL server.
 *
 * Setup:
 * Run Server/config.setup in a browser and configure params for the service as well
 * as set the update interval and enable the service.
 *
 * @author nidayand
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

		//Get old data
		$old_data = getData();
			
		//Iternate through the list
		for($i=0; $i<sizeof($config); $i++){
			$obj = $config[$i];

			$sql = "select `".$obj["column"]."` from `".$obj["database"]."`.`".$obj["table"]."` where `".$obj["id_column"]."` in (select max(`".$obj["id_column"]."`) from `".$obj["database"]."`.`".$obj["table"]."`) and `".$obj["column"]."`<".$limit;

			$item = dbSelect($sql);
			if (dbIsNotEmpty($item)){
				foreach ($item as $row) {
					//Check for previous values to check the trend
					$temp = $row[($obj["column"])];
					$previous_temperature = $row[($obj["column"])];
					$previous2_temperature = $row[($obj["column"])];
					$trend = 0;

					if (isset($old_data["entries"][0]["name"])){
						for ($j=0; $j< count($old_data["entries"]); $j++){
							if ($old_data["entries"][$j]["name"] === $obj["name"]){
								$previous_temperature = $old_data["entries"][$j]["temperature"];
								$previous2_temperature = $old_data["entries"][$j]["previous_temperature"];
								break;
							}
						}
					}
					if (floatval($temp)>=floatval($previous_temperature) && floatval($temp)>floatval($previous2_temperature)){
						$trend = 1;
					} else if (floatval($temp)==floatval($previous_temperature) && floatval($temp)==floatval($previous2_temperature)){
						$trend = 0;
					} else {
						$trend = -1;
					}
					array_push($json, array("name"=>$obj["name"], "temperature"=>$temp,"previous_temperature"=>$previous_temperature ,"trend"=>$trend));
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
		//Images
		var imageRight ="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA6lJREFUeNrsml9IFEEcx2f2/qRdemmEVipHmJaYVxFCFEkZSSSdIEEvxS39eegPCEIQvUUvRYEQRQ/Fbb0EQXAU0X/SeojABw3/lGn+N6NQr9TbvdudaW4zitC7G2n34Of+YDhumd2d72dmf/P7zQxClllmmWWWWWaZZQvUcCpfTj/f9rKfmpm/QbziUOuCAUCHbwTY6/3/XJXwqiMieAB06Mos4v+CkHdKBAuADlxkwx63JKgl4YLTpkAQzO9+pQZRGcUvip/2nwvABEDCSRbZT3vPBCCOgCCiTGBShUHoqQuA8gE6g+7jTBT1c9wh4cLrIhgAuqQukR9C0S0RDABd0ocD/BCK74pgAOiSOmv4Iay7L4IBoEvqqOKHUPJMBANAl9RWwQ+h9LUIBoAu6V05P4SyZhEMAF1Saxk/BG+baDoAOnDBh3BaA8I2j6FEtAmEJhsRivTFh7CxRzQNAP14zIcEZ9DUoTH1BiF1ND6ETSOiOQDaK3tZFO0x99tgSZLckajSBrx5jGtRxc7djma3GyldntQ4iHCiGrHVJWMBIDKNaDScEv1YoP/9mdwAcHk0pDW6Yh7J3FGAKcJONdEHHTQcwK+M1l6HKDbVCeL0CKI0rsuShC0K96LqvKfB6INlPkpwA3uEx9hhT5DgUhL1viRsk82bBo008io9tgDCEQgx8dvD5gdChsQ8TYu5xdsqpmGEwtpLF7/4HVMwkiHtxZIAawmf+J2TMNJh7XkGf8/v+gFjQUR9mskt3r77O4wlMfWxm3vY26tCMBZF1UdL+Xt+zwSMZXH1YbaXxZItXOL3jhu2T2g3GwClsYwtae6So3rM0E1S0wEgIiQvft83w3eIU7A3yJKoWFITv5giPiUAHL6vrSyJklhBcxSJ1YF7QOK3Re7lzDYTSM7aL/CPyPyBkOtFdOaQFEZBZ+1oK7LMXEvd9vjITTdzQfmsCYtmrihsihjEKw+HwAOgQ1ezmPiCOebJAZx3YhwsADp4ycHEFyWoNYzz6ydAToOIKOmIyGr8ouTQ/vOZMAFQ2YZoWE1clOW072yG0c1JQSgsxw4CqknWzqaf6glefXkKlhPsPpnLfmwc1MZw4bUwIABH7SzmzeL7BEkIrwnIcOKAroPMF5BMvjaQSVx8JwImFKbv97MRoLk4IYTx2mAUBAAdQme1wEZCGt9dmoJLnmggAOgQ2itZO4iD7yai4tImAgKArqdta0wUZ1xCCF7/1sroLLNs/vZTgAEASSLW5kqeaesAAAAASUVORK5CYII=";
		var imageUp = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA49JREFUeNrsmm1IFEEYx2ePCytJoz5I4FvaO9ZVFiFIYVERBB0IEohxI1EUFeUHrSwqetUPBhISRd1iCBEIGoHkh6KQCsTqKnqxzOsMog9FV1jZ7e40z1mH6d7sqtfe3u78ZRh2Z3Zmnt8+zzNz5yHExcXFxcVlWwnxmtj3vslFK/efyxZXeqnPNgAeBhq9tPIMuy0uzdyCLQ+g891lNeMjEJZnlWPLArjvv+AVohsfFqEQCrK3YcsB6HjboGn8UAiFOTuxZQDc6alnuX00iaty9+CEB3Drdd1YjI9AWD27AicsgPbuWt1uzwqHdXMqccIBaHt1ejxvfoQnbJh7ACcMgBsvjjONn+ScjFKTpiGn4AxfS0RCwYHP6If0nQlh4/zD2PQAWp8f0TA+GU2fmKba9unnRwqhnwlh04Jj2LQAmp9Va8b8jORM5HRMUG2TlBD60B/QzAnFeSex6QBce1LpRYLANN5B/9Kn5DDHCXx7oyMzErFkUS02DYCmx/uo2wuaCc8hOFBWyixmn95gt979QSxdfBbHHYDYtUvzzQ9V7tR5zPaeLy9HsUcS0ZN/blwQHON5+GLndm9ICXlC8i+kp9AYFxUiI1aBPnrHg7lhDXEB0PCg3EsX64HEpa9I4tZl57FMjWQV6AN99Y8b8sBaDAVQf6/MJYXffAjpKWDQjhWXwq6q5QEg6AvP6B8/5IE1jcUW51geoq7nHkWSESsKr0bi9K+RWtpd0IjrOjbDtqc3v8CafMYAoNR1GS8gsWpl8z9JStYJAATgau4W01wXsyN1bEJAVqQWHbE5wviwBygyswwXjAFjac0HazIMQHVRq28wW0eNSfFQ0XXV7UkrCaoJxtKaD9Zk6C5wdE3b4KIo/aEF7kFbtOf0JMFYzvffD0L7bxa5CCHuwZgXWs6sv+1jf144SFjtxXmnhFjOZ5rvBCOfGZ5WMQGULKwxdE1OowHQZIXMJMMBSByAzQHIimxzDyC29wCeAzgAHgLcA/g2aOtt0E+r7CjNfqPX44iDB+yFMFAr0GZ5ACfWtrcqiuymydAvh40OFz/cgzbExWWo4vI7wUd9V1JplUEnT4JrgtAArfqWZJQFLQ+gKyCm0GlnqreS3vxMz1drnwOInCYgQfU0RBCBX05YG4BCFNh5JLPsSnEAIJvqLByPELA3AO4B3AN0/muZhwAPAYt6gGJ7DzDZd2JcXPbWbwEGAMNaXvpz9kg8AAAAAElFTkSuQmCC";
		var imageDown ="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEAAAABACAYAAACqaXHeAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAABH5JREFUeNrsWl9oW1UY/+5NYlc0SZM2m27NmnWZ2ZyjY3uYYAVFig9TVkGH+IflPohvMhBEHwTBB0UQ+io+5A7/IEMwonuQIgpWcA8bK3ObcVnXLt10S5s0iZKmNznH77ZlsDY5997m3pM2PT+45M85537f9/t+59x7/gAICAgICAgIbFZIrTBaevfVYyBLI/g1svzXJBB60vvhF9+1PQHFt146BpKUrFtI6bDvk6+5kiBzT79WG4GFKtS99DLOcPM2SCvVCKM40vYELGZ6HaEVCtjcBAgFVGpCAWIMEArYzARsdgVQoQChgPYioPTeiQH8GF7+mfR+cGqcSUCVcLXn6HS4+PbLCZzaxldMaVXfx18pjdrc7o1S1j23TaclO+05Nh0uvvliAvtzHOY1uOfC/xbLGoBIEvOy254jCii8PjyAmbjA7uxU9X+WXJWZm+E9TAXsyFyV6thbnfnV9g6ivXEuYwCtaMMmqsXnXjsKXZ+fUVYqwArwHgm0FzdRVfeJDwFQ1szWjM+9MARd34zeJcHKEIhtEyjzODiItSlgvppEyb1vloT80SchcOYXZUkB5hphmwTaMR98o3VGp54C+aHBBFAw7yCOCYGfflOu9cWYY8DuqZSUf/px4z5/bxRqYHRM4UqAjtzgEcsk5G4VmPWD2/2q1eCDY2fX/Bhsell89vAhJIGadng2V2aWdwc7LXgvqd3nzivN+G/LvsDs/gOmSciW2a/CoU63+eAvXVSa9d22jZGZ/pip7nDHwORWoKZk3zORUuzw29adoWxvVH8jY5KQ9bgbvgvIlEJIM5wsqaHptGKXz7Zvjf0T3sMkoYLBF9yuumX+ag06KFMB6oOZq4qd/jqyN3jLiARZgpLLdVcJeua9NQyesIPfbnPwjhGgI7PzYcPuYAFq+MZfihN+Oro7PIUk0CZJQAfVPoeCd5wAHRN9sWaUoPZPpRQn/eNyPiAdiVlWgp756KSzwXMjQEcqsteKEtTY5J8KD7+4nhC5smuvoRL0zO+7zid47gTo+KN/H0sJ6qMTVxSe/rTkkNR4fRLUAc7Bt4wAHRd2PzJAl5e30YnkwWuXx0GAP1pzTvCdV/wgS2E037G8UlIBQjPej74stD0BxZPHAyDLO+sWEnLDN3I637YEFN54vgszv4NZidCb/k+/nWs7AgonnvVh8A+Zqkzo3/5TPxTbhoC54894MfitlhoReqfr9I+lDU9A/rmn7sc+37OmxoTMBL7/+b8NS0B+aLATMx9s6iaE5gKjY+UNR0Bu8MgWDN5vy80ILQTHzs5vGAJyhw/dh8E/0LCCWwY5GgLJu7QHQEtlIOksAOvwBKH/Bs+dX1j3BMzuP+DB4BvvbmzxgOeJKIBnxcKoVgPt1/TSnn9jEsrdly5q65aAmf6YCwe8DlYd92O7QO7x1o9vpgTV368bDYyVnomUbedtbTskle2NylSrYVprzIV9ydfZ8KDUYlnFMMEutEVD02mybgi4HY7C4oo2BeMjYAs1g/HOvM1tmbSYzQkINIf/BRgAIuPUaiDO0cAAAAAASUVORK5CYII=";
		
		var html = '<table width="100%" border="0"><tbody>';
		for (var i=0; i<data.entries.length; i++){
			var rowStr = '<tr><td>@</td><td>@&deg;</td><td><img src="@" style="width:18px"/></td></tr>';
			var image = "";
			switch(data.entries[i].trend){
				case 1: image = imageUp;
						break;
				case 0: image = imageRight;
						break;
				case -1: image = imageDown;
						break;
			}
			html += js.stringChrParams(rowStr, "@", [data.entries[i].name, (Math.round(data.entries[i].temperature*10)/10).toFixed(1), image]);
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
