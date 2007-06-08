<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

//TODO: sortowanie, tlumaczenie label

class Base_ActionBar extends Module {

	public function construct() {
		$this->force_process();
	}
	
	public function body($arg) {
		$icons = Base_ActionBarCommon::get_icons();
		$l = & $this->pack_module('Base/Lang');
		foreach($icons as &$i)
			$i['label'] = $l->t($i['label']);
		$th = & $this->pack_module('Base/Theme');
		$display_settings = Base_User_SettingsCommon::get_user_settings('Base/ActionBar','display');
		$th->assign('display_icon',($display_settings == 'both' || $display_settings == 'icons only'));
		$th->assign('display_text',($display_settings == 'both' || $display_settings == 'text only'));
		$th->assign('icons',$icons);
		$th->display();
	}

}

?>