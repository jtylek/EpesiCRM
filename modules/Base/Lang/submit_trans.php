<?php
require_once('include.php');
class myFunctions extends saja {
	public function translate($parent,$oryg,$trans) {
		ModuleManager :: include_common('Base_Acl', 0);
		ModuleManager :: include_common('Base_Lang', 0);
		ModuleManager :: include_common('Base_MaintenanceMode', 0);

		if(!Acl::check('Administration','Modules') || !Base_MaintenanceModeCommon::get_mode()) return;
		
		require_once('LangCommon_0.php');
		Base_LangCommon::load();
		if(Base_AclCommon::i_am_user())
		global $translations;
		$translations[$parent][$oryg]=$trans;
		Base_LangCommon::save();
	}
}
?>
