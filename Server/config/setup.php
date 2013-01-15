<?php
header('Content-type: text/html; charset=UTF-8');
include_once 'config.php';
include_once '../database/db.php';
include_once '../includes/services.php';

//Include all files
$allSvc = includeSvcObjects();

/*
 Check if information has been posted
*/
$msg="";
if (sizeof($_POST)>0){
	$svc = $_POST["svc"];
	$type = $_POST["type"];
	//Check if type = "data" && svc != null
	if ($svc!="null" && $type=="data" && !isset($_POST["_enabled"]))
		kvp_set("_enabled", "false", $type, $svc);
	
	foreach ($_POST as $key => $value) {
		if ($key=="svc" || $key=="type")
			continue;

		$value = str_replace(array("\r\n", "\r", "\n"), ' ', $value);
		if ($svc==='null')
			kvp_set($key, trim($value), $type);
		else
			kvp_set($key, trim($value), $type, $svc);
	}
	$msg ="Updated service: ".$svc;
}
?><!doctype html>
<html lang="us">
<head>
	<meta charset="utf-8">
	<title>Settings</title>
	<link href="css/cupertino/jquery-ui-1.9.2.custom.css" rel="stylesheet">
	<script src="js/jquery-1.8.3.js"></script>
	<script src="js/jquery-ui-1.9.2.custom.js"></script>
	<script>
	$(function() {
		
		$( "#accordion" ).accordion({
            heightStyle: "content",
            collapsible: true,
            active: false
        });
		

		
		var availableTags = [
			"ActionScript",
			"AppleScript",
			"Asp",
			"BASIC",
			"C",
			"C++",
			"Clojure",
			"COBOL",
			"ColdFusion",
			"Erlang",
			"Fortran",
			"Groovy",
			"Haskell",
			"Java",
			"JavaScript",
			"Lisp",
			"Perl",
			"PHP",
			"Python",
			"Ruby",
			"Scala",
			"Scheme"
		];
		$( "#autocomplete" ).autocomplete({
			source: availableTags
		});
		

		
		$( "#button" ).button();
		$( "#radioset" ).buttonset();
		

		
		$( "#tabs" ).tabs();
		

		
		$( "#dialog" ).dialog({
			autoOpen: false,
			width: 400,
			buttons: [
				{
					text: "Ok",
					click: function() {
						$( this ).dialog( "close" );
					}
				},
				{
					text: "Cancel",
					click: function() {
						$( this ).dialog( "close" );
					}
				}
			]
		});

		// Link to open the dialog
		$( "#dialog-link" ).click(function( event ) {
			$( "#dialog" ).dialog( "open" );
			event.preventDefault();
		});
		

		
		$( "#datepicker" ).datepicker({
			inline: true
		});
		

		
		$( "#slider" ).slider({
			range: true,
			values: [ 17, 67 ]
		});
		

		
		$( "#progressbar" ).progressbar({
			value: 20
		});
		

		// Hover states on the static widgets
		$( "#dialog-link, #icons li" ).hover(
			function() {
				$( this ).addClass( "ui-state-hover" );
			},
			function() {
				$( this ).removeClass( "ui-state-hover" );
			}
		);
	});
	</script>
	<style>
	body{
		font: 62.5% "Trebuchet MS", sans-serif;
		margin: 50px;
	}
	.demoHeaders {
		margin-top: 2em;
	}
	#dialog-link {
		padding: .4em 1em .4em 20px;
		text-decoration: none;
		position: relative;
	}
	#dialog-link span.ui-icon {
		margin: 0 5px 0 0;
		position: absolute;
		left: .2em;
		top: 50%;
		margin-top: -8px;
	}
	#icons {
		margin: 0;
		padding: 0;
	}
	#icons li {
		margin: 2px;
		position: relative;
		padding: 4px 0;
		cursor: pointer;
		float: left;
		list-style: none;
	}
	#icons span.ui-icon {
		float: left;
		margin: 0 4px;
	}
	</style>
</head>
<body>

<h1>Services Settings</h1>

<div class="ui-widget">
	<p>Use this page to save the settings for the services components in your "services/" library</p>
</div>

<? if ($msg!="") {?>
<div class="ui-widget">
<div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;">
<p><span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span>
<strong>Updated!</strong> <?=$msg?></p>
</div>
</div>
<?} 
?>

<h1>YOUR SERVICES:</h1>

<div id="accordion">
<?php 
	//Global setup
echo "<h3>global_settings</h3>";
$setup = $global_settings;
if (sizeof($setup)>0){
	?>
			<form method="POST"><input type="hidden" name="svc" value="null"/><input type="hidden" name="type" value="data"/><table>
			<?
				for($j=0; $j<sizeof($setup); $j++){
					$value = kvp_get($setup[$j]["key"]) !== false ? kvp_get($setup[$j]["key"]) : $setup[$j]["value"];
				?><tr>
					<td><font color="<?=($setup[$j]["mandatory"]==1 ? "red" : "") ?>"><?=$setup[$j]["key"]?></font></td><td><textarea name="<?=($setup[$j]["mandatory"]==2 ? "" : $setup[$j]["key"]) ?>" cols="50" <?=$setup[$j]["mandatory"]==2? "readonly=\"readonly\"" : "" ?> style="<?=kvp_get($setup[$j]["key"]) !== false ?"":"background-color: green"?>"><?=$value?></textarea></td><td><?=$setup[$j]["description"]?></td>
					</tr>
				<?
				}
			?>
			<tr><td colspan="3"><input type="reset"><input type="submit"></table></form>
		<?
	}
		else echo "No parameters to setup for this service";	

	//Services
	for($i=0;$i<sizeof($allSvc);$i++){
		$setupCall = $allSvc[$i]."\setup_data";
		$setup = $setupCall();
		$setupCallui = $allSvc[$i]."\setup_ui";
		$setupui = @$setupCallui();
		
		echo "<h3>".$allSvc[$i]."</h3><div>";
		if ($setupui && sizeof($setupui)>0){
			?>
				<h4>UI settings</h4>
				<form method="POST"><input type="hidden" name="type" value="ui"/><input type="hidden" name="svc" value="<?=$allSvc[$i]?>"/><table>
				<?
					for($j=0; $j<sizeof($setupui); $j++){
						$value = kvp_get($setupui[$j]["key"], $allSvc[$i]) !== false ? kvp_get($setupui[$j]["key"], $allSvc[$i]) : $setupui[$j]["value"];
					?><tr>
						<td><font color="<?=($setupui[$j]["mandatory"]==1 ? "red" : "") ?>"><?=$setupui[$j]["key"]?></font></td><td><textarea name="<?=($setupui[$j]["mandatory"]==2 ? "" : $setupui[$j]["key"]) ?>" cols="50" <?=$setupui[$j]["mandatory"]==2? "readonly=\"readonly\"" : "" ?> style="<?=kvp_get($setupui[$j]["key"], $allSvc[$i]) !== false ?"":"background-color: green"?>"><?=$value?></textarea></td><td><?=$setupui[$j]["description"]?></td>
						</tr>
					<?
					}
				?>
				<tr><td colspan="3"><input type="reset"><input type="submit"></table></form>
			<?
				
		}
			else echo "No UI parameters to setup for this service";
		if (sizeof($setup)>0){
			$enabled = kvp_get("_enabled", $allSvc[$i]);
			$pullInterval = kvp_get("_pull_interval", $allSvc[$i]);
			?>
				<h4>Data settings</h4>
				<form method="POST"><input type="hidden" name="type" value="data"/><input type="hidden" name="svc" value="<?=$allSvc[$i]?>"/>
				<table>
					<tr><td>Service enabled</td><td> <input type="checkbox" name="_enabled" value="true" <?=($enabled==="true" ? "checked" : "") ?>></td><td>Enable/disable the service for calling the data_load function of the service</td></tr>
					<tr><td>Pull interval</td><td> <input type="text" name="_pull_interval" value="<?=$pullInterval==false ? 0: $pullInterval?>"></td><td>The minimum interval in seconds where another request of data can be perfomed. Used to limit unnecessary loading of data from the called external service.<br/>Optional parameter. Set to 0 to disable</td></tr>
				<?
					for($j=0; $j<sizeof($setup); $j++){
						$value = kvp_get($setup[$j]["key"], $allSvc[$i]) !== false ? kvp_get($setup[$j]["key"], $allSvc[$i]) : $setup[$j]["value"];
					?><tr>
						<td><font color="<?=($setup[$j]["mandatory"]==1 ? "red" : "") ?>"><?=$setup[$j]["key"]?></font></td><td><textarea name="<?=($setup[$j]["mandatory"]==2 ? "" : $setup[$j]["key"]) ?>" cols="50" <?=$setup[$j]["mandatory"]==2? "readonly=\"readonly\"" : "" ?> style="<?=kvp_get($setup[$j]["key"], $allSvc[$i]) !== false ?"":"background-color: green"?>"><?=$value?></textarea></td><td><?=$setup[$j]["description"]?></td>
						</tr>
					<?
					}
				?>
				<tr><td colspan="3"><input type="reset"><input type="submit"></table></form>
			<?
		}
			else echo "No data parameters to setup for this service";
		echo "</div>";
	}?>
</div>

</body>
</html>
