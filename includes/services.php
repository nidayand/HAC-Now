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
 * Calls a specific service
 * @param String $svc Namespace of the service
 */
function callSvc($svc){
	global $db_defaultdb;
	debug($svc, true);

	$load_data = callLoadData($svc);

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
 * @return boolean|array False if a retrieval failed or if service data should be removed. Otherwise it returns an array of the dataset
 */
function callLoadData($svc){
	global $db_defaultdb;
	/*
	 * Get the configuration parameters.
	* To be passed into the load_data function of the service
	*/
	$items = dbSelect("select `key`, `value` from `".$db_defaultdb."`.`kvp` where `type`=? and `context`=?", array("data",$svc));
	$setup = array();
	if (dbIsNotEmpty($items)){
		for ($i = 0; $i < count($items); $i++) {
			$setup[$items[$i]["key"]] = $items[$i]["value"];
		}
	}

	/*
	 * Call the load_data method
	*/
	$methodCall = $svc."\load_data";
	return $methodCall($setup);
}
?>