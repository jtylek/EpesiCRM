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
 * @subpackage actionbar
 * @license SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBar extends Module {

	/**
	 * Compares two action bar entries to determine order.
	 * For internal use only.
	 * 
	 * @param mixed action bar entry
	 * @param mixed action bar entry
	 * @return int comparison result
	 */
	public function compare($a, $b) {
		$ret = Base_ActionBarCommon::$available_icons[$a['icon']]-Base_ActionBarCommon::$available_icons[$b['icon']];
		if($ret==0) $ret = strcmp($a['label'],$b['label']);
		return $ret;
	}

	/**
	 * Displays action bar.
	 */
	public function body() {
		$icons = Base_ActionBarCommon::get();
		$l = & $this->init_module('Base/Lang');
		$tip = & $this->init_module('Utils/Tooltip');

		if(Acl::is_user())
			$display_settings = Base_User_SettingsCommon::get('Base/ActionBar','display');
		else
			$display_settings = 'both';
		$display_icon = ($display_settings == 'both' || $display_settings == 'icons only');
		$display_text = ($display_settings == 'both' || $display_settings == 'text only');
		
		//sort
		usort($icons, array($this,'compare'));

		//translate
		foreach($icons as &$i) {
			$i['label'] = $l->ht($i['label']);
			$i['description'] = $l->ht($i['description']);
			if($display_text)
				$t = $tip->open_tag_attrs((($i['description'])?$i['description']:$i['label']));
			else
				$t = $tip->open_tag_attrs($i['label'].(($i['description'])?' - '.$i['description']:''),false);
			$i['open'] = '<a '.$i['action'].' '.$t.'>';
			$i['close'] = '</a>';
			$i['icon'] = Base_ThemeCommon::get_template_file('Base_ActionBar','icons/'.$i['icon'].'.png');
		}

				
		$launcher=array();
		if(Acl::is_user()) {
			$opts = Base_Menu_QuickAccessCommon::get_options();
			foreach ($opts as $v)
				if (Base_User_SettingsCommon::get('Base_ActionBar',$v['name'])) {
					$menu_entry = null;
					parse_str($v['link'],$menu_entry);
					$ii = array();
					$ii['label'] = substr(strrchr($v['label'],':'),1);
					$ii['description'] = '';
					$ii['open'] = '<a '.$this->create_href($menu_entry).'>';
					$ii['close'] = '</a>';
					$icon = Base_ThemeCommon::get_template_file($v['module'],'icon.png');
					if($icon===false)
						$icon = Base_ThemeCommon::get_template_file($this->get_type(),'default_icon.png');
					$ii['icon'] = $icon;
					$launcher[] = $ii;
				} 
		}
		
		//display
		$th = & $this->pack_module('Base/Theme');
		$th->assign('display_icon',$display_icon);
		$th->assign('display_text',$display_text);
		$th->assign('icons',$icons);
		$th->assign('launcher',$launcher);
		$th->display();
	}

}

?>