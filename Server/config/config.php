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
	$svcext_dir = "services/extensions/";
	$app_dir = "applications/";
	
	//Files
	$svcfileend = "_hacsvc.php";
	$svcextfileend = "_hacsvcext.php";
	
	$root = $_SERVER["DOCUMENT_ROOT"].substr($_SERVER["SCRIPT_NAME"],0,strpos($_SERVER["SCRIPT_NAME"],"/",1)+1)."Server/";
	$debugOn = false;

	//Global settings (configured via setup.php)
	$global_settings = array(
					array("key"=>"js_date", "value"=>"{ \"dayNames\" : [\"S&#246;n\", \"M&#229;n\", \"Tis\", \"Ons\", \"Tor\", \"Fre\", \"L&#246;r\", \"S&#246;ndag\", \"M&#229;ndag\", \"Tisdag\", \"Onsdag\", \"Torsdag\", \"Fredag\", \"L&#246;rdag\"], \"monthNames\" : [\"Jan\", \"Feb\", \"Mar\", \"Apr\", \"Maj\", \"Jun\", \"Jul\", \"Aug\", \"Sep\", \"Okt\", \"Nov\", \"Dec\", \"Januari\", \"Februari\", \"Mars\", \"April\", \"Maj\", \"Juni\", \"Juli\", \"Augusti\", \"September\", \"Oktober\", \"November\", \"December\"]}", "mandatory"=>1,"description"=>"Local names of days an months for display in the UI"),
					array("key"=>"data_load", "value"=>"10", "mandatory"=>1, "description"=>"Client refresh interval of data (in seconds)"),
					array("key"=>"data_update", "value"=>"300", "mandatory"=>1, "description"=>"Client request an update of the data (in seconds). To disable client data update polling, set to 0")
			);

?>