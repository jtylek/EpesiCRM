<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @package epesi-base-extra
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBar extends Module {

	public function sort($a, $b) {
		$ret = Base_ActionBarCommon::$available_icons[$a['icon']]-Base_ActionBarCommon::$available_icons[$b['icon']];
		if($ret==0) $ret = strcmp($a['label'],$b['label']);
		return $ret;
	}

	public function body($arg) {
		$icons = Base_ActionBarCommon::get_icons();
		$l = & $this->pack_module('Base/Lang');

		//translate
		foreach($icons as &$i)
			$i['label'] = $l->t($i['label']);
		
		//sort
		usort($icons, array($this,'sort'));
		
		//remove duplicates
		$ic = count($icons);
		for($k=1, $j=0; $k<$ic; $k++) {
			if($icons[$k]!=$icons[$j])
				$j++;
			else
				unset($icons[$k]);
		}
		
		//display
		$th = & $this->pack_module('Base/Theme');
		$display_settings = Base_User_SettingsCommon::get_user_settings('Base/ActionBar','display');
		$th->assign('display_icon',($display_settings == 'both' || $display_settings == 'icons only'));
		$th->assign('display_text',($display_settings == 'both' || $display_settings == 'text only'));
		$th->assign('icons',$icons);
		$th->display();
	}

}

?>