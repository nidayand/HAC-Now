<?php 
header( 'Content-Type: application/javascript; charset: UTF-8' );

include_once '../config/config.php';
include_once '../includes/services.php';
include_once '../database/db.php';

function getScript($svc){
	$resp="";
	//Add javascript
	if (defined($svc."\javascript")){
		$resp = <<<EOT
hac.$svc = function $svc(){\n
EOT;
		$resp.=constant ($svc."\javascript");
		$resp.="\n}\n";
	}
	return $resp;
}

/* Include the service javascript objects including the
 javascript constant value specified in the service */

$svcs = includeSvcObjects();
for($i=0;$i<sizeof($svcs);$i++){
	echo getScript($svcs[$i]);
}

/* Adding additional javascript libraries
 that have been specified in the includes-folder (sub folder to js).
These are primarily classes/functions that are external libraries
Accepted files are PHP (.php) or Javascript (.js) files */

$response = array();
$res = scandir($root."js/includes/");
$isValid = function ($str, $sub){
	// if (strlen($str)<=strlen($sub))
	// return false;
	return (substr( $str, strlen( $str ) - strlen( $sub ) ) === $sub);
};
for ($i=0; $i<sizeof($res); $i++) {
	if (!($isValid($res[$i],".php") || $isValid($res[$i],".js")))
		continue;
	//Include file
	include_once $root."js/includes/".$res[$i];
}
?>