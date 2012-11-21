<?php
DB::Execute('DELETE FROM modules WHERE name=%s', array('CRM_Acl'));

?>