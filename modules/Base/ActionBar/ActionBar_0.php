7<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage actionbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBar extends Module {
	private static $launchpad;

	/**
	 * Compares two action bar entries to determine order.
	 * For internal use only.
	 *
	 * @param mixed action bar entry
	 * @param mixed action bar entry
	 * @return int comparison result
	 */
	public function compare($a, $b) {
		if (!isset(Base_ActionBarCommon::$available_icons[$a['icon']])) return 1;
		if (!isset(Base_ActionBarCommon::$available_icons[$b['icon']])) return -1;
		if (!isset($a['position'])) $a['position'] = 0;
		if (!isset($b['position'])) $b['position'] = 0;
		$ret = $a['position'] - $b['position'];
		if($ret==0) $ret = Base_ActionBarCommon::$available_icons[$a['icon']]-Base_ActionBarCommon::$available_icons[$b['icon']];
		if($ret==0) $ret = strcmp(strip_tags($a['label']),strip_tags($b['label']));
		return $ret;
	}

	public function compare_launcher($a, $b) {
		return strcmp($a['label'],$b['label']);
	}

	/**
	 * Displays action bar.
	 */
	public function body() {
		$this->help('ActionBar basics','main');
		
		$icons = Base_ActionBarCommon::get();
		if(Base_AclCommon::is_user())
			$display_settings = Base_User_SettingsCommon::get('Base/ActionBar','display');
		else
			$display_settings = 'both';
		$display_icon = ($display_settings == 'both' || $display_settings == 'icons only');
		$display_text = ($display_settings == 'both' || $display_settings == 'text only');

		//sort
		usort($icons, array($this,'compare'));

		//translate
		foreach($icons as &$i) {
			$description = $i['description'];
			if($display_text)
				if($i['description'])
					$t = Utils_TooltipCommon::open_tag_attrs($description);
				else
					$t = '';
			else
				$t = Utils_TooltipCommon::open_tag_attrs($i['label'].(($i['description'])?' - '.$description:''),false);
			$i['open'] = '<a '.$i['action'].' '.$t.'>';
			$i['close'] = '</a>';
			if (strpos($i['icon'], '/')!==false && file_exists($i['icon'])) {
				$i['icon_url'] = $i['icon'];
				unset($i['icon']);
			}
			//if (isset(Base_ActionBarCommon::$available_icons[$i['icon']]))
			//	$i['icon'] = Base_ThemeCommon::get_template_file('Base_ActionBar','icons/'.$i['icon'].'.png');
		}


		$launcher=array();
		if(Base_AclCommon::is_user()) {
			$opts = Base_Menu_QuickAccessCommon::get_options();
			if(!empty($opts)) {
				$dash = ($mod=ModuleManager::get_instance('/Base_Box|0')) && ($main=$mod->get_main_module()) && $main->get_type()=='Base_Dashboard';
				self::$launchpad = array();
				foreach ($opts as $k=>$v) {
					if($dash && Base_User_SettingsCommon::get('Base_Menu_QuickAccess',$v['name'].'_d')) {
						$ii = array();
						$trimmed_label = trim(substr(strrchr($v['label'],':'),1));
						$ii['label'] = $trimmed_label?$trimmed_label:$v['label'];
						$ii['description'] = $v['label'];
						$arr = $v['link'];
						if(isset($arr['__url__']))
							$ii['open'] = '<a href="'.$arr['__url__'].'">';
						else
							$ii['open'] = '<a '.Base_MenuCommon::create_href($this,$arr).'>';
						$ii['close'] = '</a>';
						if(isset($v['link']['__icon__']))
							$icon = Base_ThemeCommon::get_template_file($v['module'],$v['link']['__icon__']);
						else
							$icon = Base_ThemeCommon::get_template_file($v['module'],'icon.png');
						if (!$icon) $icon = Base_ThemeCommon::get_template_file($this->get_type(),'default_icon.png');
						$ii['icon'] = $icon;
						$launcher[] = $ii;
					}
					if (Base_User_SettingsCommon::get('Base_Menu_QuickAccess',$v['name'].'_l')) {
						$ii = array();
						$trimmed_label = trim(substr(strrchr($v['label'],':'),1));
						$ii['label'] = $trimmed_label?$trimmed_label:$v['label'];
						$ii['description'] = $v['label'];
						$arr = $v['link'];
						if(isset($arr['__url__']))
							$ii['open'] = '<a href="'.$arr['__url__'].'" onClick="actionbar_launchpad_deactivate()">';
						else {
							$ii['open'] = '<a onClick="actionbar_launchpad_deactivate();'.Base_MenuCommon::create_href_js($this,$arr).'" href="javascript:void(0)">';
						}
						$ii['close'] = '</a>';

						if(isset($v['link']['__icon__']))
							$icon = Base_ThemeCommon::get_template_file($v['module'],$v['link']['__icon__']);
						else
							$icon = Base_ThemeCommon::get_template_file($v['module'],'icon.png');
						if (!$icon) $icon = Base_ThemeCommon::get_template_file($this->get_type(),'default_icon.png');

						$ii['icon'] = $icon;
						self::$launchpad[] = $ii;
					}
				}
			}
		}

		//display
		$th = $this->pack_module('Base/Theme');
		$th->assign('display_icon',$display_icon);
		$th->assign('display_text',$display_text);
		$th->assign('icons',$icons);
		$th->assign('launcher',array_reverse($launcher));
		$th->display();
	}
	
	public function launchpad() {
		if (self::$launchpad==null) return;
		if(Base_AclCommon::is_user())
			$display_settings = Base_User_SettingsCommon::get('Base/ActionBar','display');
		else
			$display_settings = 'both';
		$display_icon = ($display_settings == 'both' || $display_settings == 'icons only');
		$display_text = ($display_settings == 'both' || $display_settings == 'text only');

		$launcher = array();
		usort(self::$launchpad,array($this,'compare_launcher'));
		if(!empty(self::$launchpad)) {
			$icon = Base_ThemeCommon::get_template_file($this->get_type(),'launcher.png');
			$th = $this->pack_module('Base/Theme');
			$th->assign('display_icon',$display_icon);
			$th->assign('display_text',$display_text);
			usort(self::$launchpad,array($this,'compare_launcher'));
			$th->assign('icons',self::$launchpad);
			eval_js_once('actionbar_launchpad_deactivate = function(){leightbox_deactivate(\'actionbar_launchpad\');}');
			ob_start();
			$th->display('launchpad');
			$lp_out = ob_get_clean();
			$big = count(self::$launchpad)>10;
			Libs_LeightboxCommon::display('actionbar_launchpad',$lp_out,__('Launchpad'),$big);
			$launcher[] = array('label'=>__('Launchpad'),'description'=>'Quick modules launcher','open'=>'<a '.Libs_LeightboxCommon::get_open_href('actionbar_launchpad').'>','close'=>'</a>','icon'=>$icon);
			$th = $this->pack_module('Base/Theme');
			$th->assign('display_icon',$display_icon);
			$th->assign('display_text',$display_text);
			$th->assign('icons',array());
			$th->assign('launcher',array_reverse($launcher));
			$th->display();
			eval_js('$("launchpad_button_section").style.display="";');
			eval_js('$("launchpad_button_section_spacing").style.display="";');
		}
	}

}

?>
