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
				$t = Utils_TooltipCommon::open_tag_attrs((($i['description'])?$i['description']:$i['label']));
			else
				$t = Utils_TooltipCommon::open_tag_attrs($i['label'].(($i['description'])?' - '.$i['description']:''),false);
			$i['open'] = '<a '.$i['action'].' '.$t.'>';
			$i['close'] = '</a>';
			$i['icon'] = Base_ThemeCommon::get_template_file('Base_ActionBar','icons/'.$i['icon'].'.png');
		}

				
		$launcher=array();
		if(Acl::is_user()) {
			$opts = Base_Menu_QuickAccessCommon::get_options();
			if(!empty($opts)) {
				$dash = ($mod=ModuleManager::get_instance('/Base_Box|0')) && $mod->get_main_module()->get_type()=='Base_Dashboard';
				$launchpad = array();
				foreach ($opts as $k=>$v) {
					if($dash && Base_User_SettingsCommon::get('Base_Menu_QuickAccess',$v['name'].'_d')) {
						$ii = array();
						$trimmed_label = substr(strrchr($v['label'],':'),1);
						$ii['label'] = $trimmed_label?$trimmed_label:$v['label'];
						$ii['description'] = $v['label'];
						$ii['link_id'] = 'actionbar_launchpad_'.$k;
						$ii['open'] = '<a '.$this->create_href($v['link']).' id="'.$ii['link_id'].'">';
						$ii['close'] = '</a>';
						$icon = Base_ThemeCommon::get_template_file($v['module'],'icon.png');
						if($icon===false)
							$icon = Base_ThemeCommon::get_template_file($this->get_type(),'default_icon.png');
						$ii['icon'] = $icon;
						$launcher[] = $ii;
					} elseif (Base_User_SettingsCommon::get('Base_Menu_QuickAccess',$v['name'].'_l')) {
						$ii = array();
						$trimmed_label = substr(strrchr($v['label'],':'),1);
						$ii['label'] = $trimmed_label?$trimmed_label:$v['label'];
						$ii['description'] = $v['label'];
						$ii['link_id'] = 'actionbar_launchpad_'.$k;
						$ii['open'] = '<a '.$this->create_href($v['link']).' id="'.$ii['link_id'].'">';
						$ii['close'] = '</a>';
						$icon = Base_ThemeCommon::get_template_file($v['module'],'icon.png');
						if($icon===false)
							$icon = Base_ThemeCommon::get_template_file($this->get_type(),'default_icon.png');
						$ii['icon'] = $icon;
						$launchpad[] = $ii;
					}
				}
				if(!empty($launchpad)) {
					$icon = Base_ThemeCommon::get_template_file($this->get_type(),'launcher.png');
					$th = & $this->pack_module('Base/Theme');
					$th->assign('display_icon',$display_icon);
					$th->assign('display_text',$display_text);
					$close_icon = Base_ThemeCommon::get_template_file('Base_ActionBar','icons/back.png');
					$close_link_id = 'actionbar_launchpad_close';
					$launchpad[] = array('label'=>'Close launchpad','link_id'=>$close_link_id,'open'=>'<a id="'.$close_link_id.'" href="javascript:void(0)">','close'=>'</a>','icon'=>$close_icon);
					$th->assign('icons',$launchpad);
					eval_js_once('actionbar_launchpad_deactivate = function(){leightbox_deactivate(\'actionbar_launchpad\');}');
					foreach($launchpad as $v)
						eval_js('Event.observe(\''.$v['link_id'].'\',\'click\', actionbar_launchpad_deactivate)');
					print('<div id="actionbar_launchpad" class="leightbox">');
					$th->display('launchpad');
					print('</div>');
					$launcher[] = array('label'=>'Launchpad','description'=>'Quick modules launcher','open'=>'<a class="lbOn" rel="actionbar_launchpad" href="javascript:void(0)">','close'=>'</a>','icon'=>$icon);
				}
			}
		}
		
		//display
		$th = & $this->pack_module('Base/Theme');
		$th->assign('display_icon',$display_icon);
		$th->assign('display_text',$display_text);
		$th->assign('icons',$icons);
		$th->assign('launcher',array_reverse($launcher));
		$th->display();
	}

}

?>