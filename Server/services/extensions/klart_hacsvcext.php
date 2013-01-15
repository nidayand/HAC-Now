<?php
namespace klart_hacsvc\ext {
	use debug;
	function load_data($setup_data){
		//Call the super class
		$master = \klart_hacsvc\load_data($setup_data);
		
		//Get data from 1wire
		$items = dbSelect('select * from (select round(`humid temp box 1 -temp`.Temperature,1) as temp FROM 1wire.`humid temp box 1 -temp` order by `index` desc limit 1) as a1,(SELECT round(RH,0) as rh FROM 1wire.`humid temp box 1` order by `index` desc limit 1) as a2');
		$temp = dbGetColumnValueFromRow1($items,'temp');
		$rh = dbGetColumnValueFromRow1($items,'rh');
		debug ("Retrieved 1-wire data");

		$master["current"]["temp"] = $temp;
		$master["current"]["humidity"] = $rh;
		
		return $master;
	}
}
?>