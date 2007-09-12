<?php
/**
 * Lang class.
 * 
 * This class provides translations manipulation.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage lang
 */

/**
 * This class provides inline translation method.
 */
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
