<?php

/**
 * Includes all service files
 * @return array : available services (namespaces)
 */
function includeSvcObjects(){
	global $svc_dir;
	global $root;

	$response = array();
	$res = scandir($root.$svc_dir);
	for ($i=0; $i<sizeof($res); $i++) {
		//Skip certain files
		if ((strpos($res[$i],".")==0) || (strpos($res[$i],".tmpl")!=0) || (!strpos($res[$i],"_hacsvc.php")))
			continue;
			
		//Include services
		include_once $root.$svc_dir.$res[$i];
			
		$svc = substr($res[$i],0,strpos($res[$i],"."));
		array_push($response, $svc);
	}
	return $response;
}

/**
 * Checks if all necessary mandatory parameters have been set
 * for the specific service
 * @param namespace $svc
 * @return boolean
 */
function validateSvc($svc){
	//Check if the service is enabled
	$enabled = kvp_get("_enabled", $svc);
	if (!$enabled || $enabled==="false"){
		debug("The service is not enabled");
		return false;
	}
	
	//Retrieve the parameters
	$setupCall = $svc."\setup_data";
	$setup = $setupCall();
	
	//Iterate through the list and check the mandatory parameters
	for($j=0; $j<sizeof($setup); $j++){
		if ($setup[$j]["mandatory"]==1 && (kvp_get($setup[$j]["key"], $svc) == false)){
			return false;
		}
	}
	return true;
}

/**
 * Calls a specific service
 * @param String $svc Namespace of the service
 * @param String $skipIntervalCheck Will override any interval restrictions defined in setup
 */
function callSvc($svc, $skipIntervalCheck=false){
	global $db_defaultdb;
	debug($svc, true);
	
	/*
	 * Check that all mandatory parameters have been set
	 * for the specific service
	 */
	if(!validateSvc($svc)){
		debug("Mandatory parameters have not been set for the service. Skipping data_load...");
		return false;
	}

	/*
	 * Call the load_data method in the service
	 */
	$load_data = callLoadData($svc, $skipIntervalCheck);

	/* If false, do not continue and if null reset row data
	 *
	*/
	if ($load_data === false) //Skipping update if returning false
		return false;

	if ($load_data == null){ //Delete entry
		debug("Setting state to 0 for service: ".$svc);
		deleteData($svc);
		return false;
	}


	/*
	 * Encode the response that is to be stored in the database for
	* the UI component to read
	*/
	$ui_data = json_encode($load_data);
	
	$response = setData($svc, $ui_data);
	switch ($response){
		case 0: debug ("No updates to data");
				break;
		case 1: debug ("Updated data into infobox_data table");
				break;
		case 2: debug ("Inserted data into infobox_data table");
				break;
	}

}
/**
 * Calls the load_data function of a specific namespace and returns the data
 * @param String $svc Namespace of service
 * @param String $skipIntervalCheck Will override any interval restrictions defined in setup
 * @return boolean|array False if a retrieval failed or if service data should be removed. Otherwise it returns an array of the dataset
 */
function callLoadData($svc, $skipIntervalCheck=false){
	global $db_defaultdb;
	/*
	 * Get the configuration parameters as well as infobox data (updated, data)
	* To be passed into the load_data function of the service
	*/
	$items = dbSelect("select `key`, `value` from `".$db_defaultdb."`.`kvp` where `type`=? and `context`=?", array("data",$svc));
	$setup = array();
	if (dbIsNotEmpty($items)){
		for ($i = 0; $i < count($items); $i++) {
			$setup[$items[$i]["key"]] = $items[$i]["value"];
		}
	}
	$items = dbSelect("select `updated`, `data` from `".$db_defaultdb."`.`infobox_data` where `context`=?", array($svc));
	if (dbIsNotEmpty($items)){
		for ($i = 0; $i < count($items); $i++) {
			$setup["infobox_updated"] = strtotime($items[$i]["updated"]); //Convert to PHP date
			$setup["infobox_data"] = $items[$i]["data"];
		}
	}
	
	/*
	 * Check if the service has a pull limit defined
	* in the settings if it should be checked
	*/
	$pullInterval = kvp_get("_pull_interval", $svc);
	$pullInterval = ($pullInterval==false? 0: $pullInterval);
	
	if (!$skipIntervalCheck && isset($setup["infobox_updated"]) && $pullInterval>0){
		$now = time();
		$last_updated = $setup["infobox_updated"];
		if (($now - $last_updated)<$pullInterval){
			debug("Last update is not higher than the current Pull Interval key (".($now - $last_updated)." secs < ".$pullInterval."). Will not pull for data...");
			return false;
		}
	}	
	/*
	 * Call the load_data method
	*/
	$methodCall = $svc."\load_data";
	$response = $methodCall($setup);
	
	//Update timestamp if a proper result
	if ($response !== false && $response !==null){
		updateTimestamp($svc);
	}
	return $response;
}

function updateTimestamp($svc){
	global $db_defaultdb;
	dbUpdate("update `".$db_defaultdb."`.`infobox_data` set updated=CURRENT_TIMESTAMP where `context`=?", array($svc));
}
?>