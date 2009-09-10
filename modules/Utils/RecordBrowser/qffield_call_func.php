<?php
function qffield_call_func($ff,&$form, $id, $label, $mode, $defaults, $args, &$thi, $callbacks) {
	if(is_string($ff))
		$ff($form, $id, $label, $mode, $defaults, $args, $thi, $callbacks);
	elseif(is_array($ff) && count($ff)==2 && is_string($ff[0]) && is_string($ff[1]))
		$ff[0]::$ff[1]($form, $id, $label, $mode, $defaults, $args, $thi, $callbacks);
	else
		trigger_error('Invalid QFfield callback: '.print_r($ff,true),E_USER_ERROR);
}
?>