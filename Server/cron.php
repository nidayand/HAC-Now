<?
ini_set('max_execution_time', 300);
/**
 * Used to call service objects. Normally a cron-job is calling the page to populate
 * the database with service information to be used by the UI component
 * Parameters (GET)
 * service = all(default) / [namespace]
 * 		E.g. ?service=temp_warning_svc
 *
 */
include_once('config/config.php');
include_once('includes/functions.php');
include_once('database/db.php');
include_once('includes/services.php');

//Check the scope
$svcs = isset($_GET["service"]) ? $_GET["service"] : "all";

//Some html for debug
echo "<html><body><pre>";

//Include necessary service files
$namespaces = includeSvcObjects();

//Call the load_data functions
if ($svcs == "all"){
	for($i=0;$i<sizeof($namespaces);$i++){
		callSvc($namespaces[$i]);
	}
} else {
	callSvc($svcs);
}

echo "</pre></body></html>";

?>