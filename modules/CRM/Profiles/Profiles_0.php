<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-profiles
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Profiles extends Module {
	private $lang;

	public function construct() {
		$this->lang = $this->init_module('Base/Lang');
	}

	public function body() {
		Base_ActionBarCommon::add('folder','Profiles','class="lbOn" rel="crm_profiles"');
		$qf = $this->init_module('Libs/QuickForm');
		$th = $this->init_module('Base/Theme');
		$th->assign('close','<a href="javascript:void(0)" rel="deactivate" class="lbAction">Close</a>');
		print('<div id="crm_profiles" class="leightbox">');
		$th->display();
		print('</div>');
	}

}

?>
