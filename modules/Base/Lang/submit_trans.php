<?php
class myFunctions extends Epesi {
	public function translate($cl_id,$parent,$oryg,$trans) {
		$this->init($cl_id);
		
		if(!Acl::check('Administration','Modules') || !Base_MaintenanceModeCommon::get_mode()) return;

		Base_LangCommon::load();
		if(Base_AclCommon::i_am_user())
		global $translations;
		$translations[$parent][$oryg]=$trans;
		Base_LangCommon::save();
	}
}
?>
