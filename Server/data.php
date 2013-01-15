<?php
include_once 'database/db.php';
include_once 'config/config.php';
include_once 'includes/functions.php';
include_once 'includes/services.php';

//Check the scope
$svcs = isset($_GET["service"]) ? $_GET["service"] : "all";
$loaddata = isset($_GET["loaddata"]) ? true : false;

//If loaddata is set, the first load data for the service (as in cron.php)
if ($loaddata){
	//Mute debug from dataload
	define("DEBUG_MUTED", true);
	
	//Include the service
	include_once $svc_dir.$svcs.".php";
	
	//Call the service
	callSvc($svcs);
}

if ($svcs == "all"){
	$sql="SELECT * FROM `".$db_defaultdb."`.`infobox_data`";
} else {
	$sql="SELECT * FROM `".$db_defaultdb."`.`infobox_data` where `context`='".$svcs."'";
}

$items = dbSelect($sql);

$response = array();
if (dbIsNotEmpty($items)){
	foreach($items as $row){
		/*
		 * Get the UI parameters from KVP table
		*/
		$uiitems = dbSelect("select `key`, `value` from `".$db_defaultdb."`.`kvp` where `type`=? and `context`=?", array("ui", $row['context']));
		$ui = array();
		if (dbIsNotEmpty($uiitems)){
			for ($i = 0; $i < count($uiitems); $i++) {
				$ui[$uiitems[$i]["key"]] = $uiitems[$i]["value"];
			}
		}
		array_push($response, 
			array('id'=> $row['iddata'],
				'service'=> $row['context'],
				'updated'=>$row['updated'],
				'state'=>$row['state'],
				'ui'=> $ui,
				'data'=>($row['state']==1 ? json_decode($row['data']):null))
		);
	}	
}


echo json_encode($response);
?>