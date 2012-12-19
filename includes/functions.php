<?

/**
 * Debug information that is displayed. If debugOn parameter in config.php is set to false, the debug text
 * will also be written to a debug.log file.
 * If the constant DEBUG_MUTED is set as a global variable, no debug information will be outputed
 * @param String $text The text to be written
 * @param boolean $head Is the text a header
 */
function debug($text,$head=false) {
	global $debugOn;
	global $root;
	//Check if muted
	if (defined("DEBUG_MUTED") && DEBUG_MUTED){
		return;
	}
	if (!$head)
		echo "\t";
		
	if ($debugOn){
		$myFile = $root."/debug.log";
		$fh = fopen($myFile, 'a+') or die("can't open file");
		fwrite($fh, "[".date("Y-m-d H:i:s")."] ".$text."\n");
		fclose($fh);
	}
	echo $text."\n";
}

function selfPath() {
	$s = empty($_SERVER["HTTPS"]) ? ''
			: ($_SERVER["HTTPS"] == "on") ? "s"
					: "";
	
	$proto = "http".$s;
	$port = ($_SERVER["SERVER_PORT"] == "80") ? ""
			: (":".$_SERVER["SERVER_PORT"]);
	return $proto."://".$_SERVER['SERVER_NAME'].$port.substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/")+1);
	//return $protocol."://localhost".$port.substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/")+1);
}
?>