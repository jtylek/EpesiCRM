<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');
DB::Execute('DELETE FROM modules WHERE name=%s', array('CRM_Acl'));

?>