<?php
/**
 *  To be used for a custom method call for a specific service
 *  The method will be called and the content will be updated
 */
include_once 'database/db.php';
include_once 'config/config.php';
include_once 'includes/functions.php';
include_once 'includes/services.php';

	//Mute debug
	define("DEBUG_MUTED", true);

	/* Input variables
	 * method : the method to be called
	 * service : the service that holds the method
	 * params : possible parameters that needs to be passed to the method
	 * 			The format of the params are in JSON as input and the 
	 * 			method will receive an array of the parameters
	 * 			(optional)
	 */
	$method = $_POST["method"];
	$svc = $_POST["service"];
	$params = null;
	
	//var_dump ($_POST["params"]);
	
	if (isset($_POST["params"]))
		$params = $_POST["params"];
	
	//Include the service
	include_once $svc_dir.$svc.".php";
	
	//Call the method in the service
	$methodResp = callMethod($svc, $method, $params);
	
	/* Call the load_data function to
	 * make sure that if the method effects the data, the
	 * content is updated
	 * Skip interval check in the request
	 */ 
	callSvc($svc, true);
	
	//Return the response from the method call
	echo json_encode($methodResp);
?>