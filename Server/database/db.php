<?
	//namespace database;
	
	
	function connect($db){
		global $db_host, $db_user, $db_password;
		$dbh = new PDO('mysql:host='.$db_host.';dbname='.$db, $db_user, $db_password, array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") );
		return $dbh;
	}
	function disconnect($link){
		$link = null;
	}
	
	function dbSelect($sql, $params=array(), $db=null){
		global $db_defaultdb;
		
		if ($db==null)
			$db = $db_defaultdb;
		$dbh = connect($db);
			
		//mysql_select_db($db) or die('Could not select database');
		$stmt = $dbh->prepare($sql);
		if ($stmt->execute($params)){
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} else {
			return false;
		}
	}
	/*
		To be used if multiple SQLs are necessary in the same
		mysql session. E.g. when MySQL variables are being used
		Function takes an array of pairs:
			sql = sql to be executed
			params = array of parameters that should replace ? when executed
	*/
	function dbMultiSelect($sqlParamArray, $db=null){
		global $db_defaultdb;
		
		if ($db==null)
			$db = $db_defaultdb;
		$dbh = connect($db);

		for($i=0; $i<sizeof($sqlParamArray); $i++){
			$stmt = $dbh->prepare($sqlParamArray[$i]["sql"]);
			$call = $stmt->execute($sqlParamArray[$i]["params"]);
			if ($call && $i==(sizeof($sqlParamArray)-1)){
				return $stmt->fetchAll(PDO::FETCH_ASSOC);
			} else if ($i==(sizeof($sqlParamArray)-1)){
				return false;
			}			
		}
		return false; //If no array is sent to query
	}
	function dbInsert($sql,$params=array(), $db=null){
		global $db_defaultdb;
		
		if ($db==null)
			$db = $db_defaultdb;
		$dbh = connect($db);
			
		$stmt = $dbh->prepare($sql);
		if ($stmt->execute($params)){
			return true;
		} else {
			return false;
		}
	}
	/*
		Returns number of affected rows of update
	*/
	function dbUpdate($sql,$params=array(), $db=null){
		global $db_defaultdb;
		
		if ($db==null)
			$db = $db_defaultdb;
		$dbh = connect($db);
			
		$stmt = $dbh->prepare($sql);
		$stmt->execute($params);
		return $stmt->rowCount();
	}
	function dbDelete($sql,$params=array(), $db=null){
		return dbInsert($sql,$params,$db);
	}
	function kvp_set($key, $value, $type="data", $context=null){
		global $db_defaultdb;
		if ($context==null){
			//Check from PHP url params if http://10.0.1.13:8084/_server/cron_stub.php?svc=calendar_svc has been called
			if (isset($_GET["svc"]))
				$context = $_GET["svc"];
		}
		if ($context == null)
			$items = dbSelect('select value from `'.$db_defaultdb.'`.`kvp` where `context` is null and `key`=?', array($key));
		else
			$items = dbSelect('select value from `'.$db_defaultdb.'`.`kvp` where `context`=? and `key`=?', array($context, $key));
		if (dbIsNotEmpty($items))
			if ($context == null)
				dbUpdate("update `".$db_defaultdb."`.`kvp` set `value`=?, updated=CURRENT_TIMESTAMP where `context` is null and `key`=?", array($value,$key));
			else
				dbUpdate("update `".$db_defaultdb."`.`kvp` set `value`=?, updated=CURRENT_TIMESTAMP where `context`=? and `key`=?", array($value,$context,$key));
		else
			dbInsert("INSERT INTO `".$db_defaultdb."`.`kvp` (`context`,`key`, `value`, `type`) VALUES (?,?,?,?)", array($context, $key, $value, $type));		
	}
	function kvp_get($key, $context=null){
		global $db_defaultdb;
		if ($context==null){
			//Check from PHP url params if http://10.0.1.13:8084/_server/cron_stub.php?svc=calendar_svc has been called
			if (isset($_GET["svc"]))
				$context = $_GET["svc"];
		}
		if ($context != null)
			$items = dbSelect('select value from `'.$db_defaultdb.'`.`kvp` where `context`=? and `key`=?', array($context, $key));
		else
			$items = dbSelect('select value from `'.$db_defaultdb.'`.`kvp` where `context` is null and `key`=?', array($key));
		if (!dbIsNotEmpty($items))
			return false;
		else
			return dbGetColumnValueFromRow1($items, 'value');		
	}
	function dbIsNotEmpty($result){
		if (is_array($result) && sizeof($result)>0)
			return true;
		else
			return false;
	}	
	function dbGetColumnValueFromRow1($items, $col){
		if (is_array($items) && sizeof($items)>0){
			foreach ($items as $row) {
				return $row[$col];
				exit;
			}			
		} else
			return false;
	}
	/**
	*	Retrieves previously stored data for the specific service
	*	Example: getData("weather_svc");
	*/
	function getData($svc){
		global $db_defaultdb;
		$items = dbSelect("SELECT iddata, data FROM `".$db_defaultdb."`.`infobox_data` where context=?", array($svc));
		if (dbIsNotEmpty($items)){
			return json_decode(dbGetColumnValueFromRow1($items, 'data'), true);
		} else {
			return array();
		}
	}
	/**
	*	Deletes infobox data for a specific service
	*	Example: deleteData("weather_svc");
	*/
	function deleteData($svc){
		return setData($svc, "{}", 0); //Reset the values
	}
	
	/**
	 * Updates the data record for a specific service
	 * @param String $svc Service namespace
	 * @param JSON $ui_data Data to be saved
	 * @return number 0=No update needed, 1=Data is update, 2=Insert of table data
	 */
	function setData($svc, $ui_data, $state=1){
		global $db_defaultdb;
		/*
		 * Compare the data with the existing (if available) and if changed
		* the data is to be updated and the timestamp should also be updated. Else skip
		*/
		$items = dbSelect("SELECT iddata, data FROM `".$db_defaultdb."`.`infobox_data` where context=?", array($svc));
		if (dbIsNotEmpty($items)){
			//Check if update
			$table_data = dbGetColumnValueFromRow1($items, "data");
			if ($table_data !== $ui_data || $state === 0) {
				dbUpdate("update `".$db_defaultdb."`.`infobox_data` set data=?, state=".$state.", updated=CURRENT_TIMESTAMP where iddata=?", array($ui_data, dbGetColumnValueFromRow1($items, 'iddata')));
				return 1;
			} else {
				return 0;
			}
		
		} else {
			//Do insert
			dbInsert("INSERT INTO `".$db_defaultdb."`.`infobox_data` (`context`, `data`, `state`) VALUES (?,?,?)", array($svc, $ui_data, $state));
			return 2;
		}		
	}

?>