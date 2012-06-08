<?php	
DB::Execute('UPDATE phonecall_field SET type=%s WHERE field=%s', array('select', 'Customer'));
?>
