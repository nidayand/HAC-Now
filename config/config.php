<?
	ini_set('display_errors', 1);
	ini_set('log_errors', 1);
	error_reporting(E_ALL);
	
	//Compress if possible
	if(!@ob_start("ob_gzhandler")) ob_start();
	
	//Database
	$db_host = "10.0.1.13";
	$db_user = "root";
	$db_password = "passw0rd";
	$db_defaultdb = "ha_gui_dev";
	
	//Folders
	$svc_dir = "services/";
	$app_dir = "applications/";
	
	$root = $_SERVER["DOCUMENT_ROOT"].substr($_SERVER["SCRIPT_NAME"],0,strpos($_SERVER["SCRIPT_NAME"],"/",1)+1);
	$debugOn = false;

?>