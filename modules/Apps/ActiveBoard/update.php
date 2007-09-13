<?php
require_once('../../../include.php');
parse_str($_POST['data'], $x);
for($i=0; $i<3 && !isset($x['activeboard_applets_'.$i]); $i++);
		
foreach($x['activeboard_applets_'.$i] as $pos=>$id)
	DB::Execute('UPDATE apps_activeboard_applets SET pos=%d, col=%d WHERE id=%d',array($pos,$i,$id));
?>
