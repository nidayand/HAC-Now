<?php
/**
 * Retrieves a specific global parameter value from the
 * KVP-table
 * Parameters:
 * 		key = the key to be requested
 */

include_once 'database/db.php';
include_once 'config/config.php';

$value = kvp_get($_GET["key"]);

echo json_encode($value);
?>