<?php
/**
 * ActionBar
 * 
 * This class provides action bar component.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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

		// Every ActionBar should start with a Back action - added globally
		// here rather than by each module, so it's never missing (e.g. a
		// top-level Browse screen reached straight from the menu never had
		// anywhere to go "back" to inside its own module) and never doubled
		// up. If a module already registered its own 'back' (RecordBrowser's
		// view->browse, Settings subpages, etc. - those are more specific
		// than a generic "previous screen" and take priority), skip this.
		// Base_BoxCommon::has_nav_history() is false with nothing to return
		// to (e.g. the very first screen after login), so no dead button.
		$has_back = false;
		foreach ($icons as $i) {
			if ($i['icon'] === 'back') { $has_back = true; break; }
		}
		if (!$has_back && Base_BoxCommon::has_nav_history()) {
			$icons[] = array(
				'icon' => 'back',
				'label' => __('Back'),
				'action' => Base_BoxCommon::nav_back_href(),
				'description' => null,
				'position' => 0,
			);
		}

		//sort
		usort($icons, $this->compare(...));

		//translate
		foreach($icons as &$i) {
			// Every button gets a tooltip now, not just the handful that pass an
			// explicit $description - falls back to the visible label text
			// (stripped of any markup, e.g. Roundcube's bolded account label)
			// so there's still a hint once the AdminLTE theme hides labels at
			// narrow widths (Base_ActionBar/theme_adminltedark/default.css's
			// max-width:991.98px rule).
			$description = $i['description'] ?: strip_tags($i['label']);
			$t = Utils_TooltipCommon::open_tag_attrs($description);
			$i['open'] = '<a '.$i['action'].' '.$t.'>';
			$i['close'] = '</a>';
			$i['helpID'] = 'ActionBar_'.$i['icon'];
			if (str_contains($i['icon'], '/') && file_exists($i['icon'])) {
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
				self::$launchpad = array();
				foreach ($opts as $k=>$v) {
					// Dashboard's own Quick Access entry is locked, both
					// directions, regardless of whatever's stored for it
					// (Base_Menu_QuickAccess/QuickAccessCommon_0.php's
					// user_settings() disables both its checkboxes in the
					// settings UI to match, but the real enforcement has to
					// live here - a disabled checkbox alone doesn't stop a
					// stale pre-existing DB value from before this lock
					// existed). '_d' (pinned inline in the ActionBar) is
					// pointless while already ON the Dashboard, so never
					// shown; '_l' (Launchpad) is the one guaranteed way back
					// to it now that the ActionBar's own per-item toggle for
					// it is gone, so always shown.
					// $v['module'] is the underscore class-name form (e.g.
					// 'Base_Dashboard', from check_for_links()'s $mod - itself
					// a Base_MenuCommon::get_menus() array key, which
					// ModuleManager::call_common_methods() keys by class name)
					// while module_name() returns the slash path form (e.g.
					// 'Base/Dashboard') - comparing them directly never
					// matched, silently defeating this "always shown" guarantee
					// for every user (confirmed live: is_dashboard was false
					// even for the literal Dashboard entry). Module alone isn't
					// enough to identify *this* entry though - Base_Dashboard::
					// menu() also contributes a second, unrelated leaf under the
					// same module ("My settings: Dashboard", routed via
					// __function__=>'open_config' to the applet-config screen,
					// not the dashboard itself) which shares 'module' with the
					// real one and, once matched here too, showed up as a
					// second bogus "Dashboard" Launchpad icon (reported live).
					// add_default_menu() only sets box_main_function when the
					// menu entry declared __function__, so its absence is what
					// actually singles out the plain "go to the Dashboard" link.
					$is_dashboard = ($v['module']==str_replace('/','_',Base_Dashboard::module_name())) && empty($v['link']['box_main_function']);
					if(!$is_dashboard && Base_ActionBarCommon::$quick_access_shortcuts
                            && Base_User_SettingsCommon::get(Base_Menu_QuickAccessCommon::module_name(),$v['name'].'_d')) {
						$ii = array();
						$trimmed_label = trim(substr(strrchr($v['label'],':'),1));
						$ii['label'] = $trimmed_label?$trimmed_label:$v['label'];
						$ii['description'] = $v['label'];
						$arr = $v['link'];
						if(isset($arr['__url__']))
							$ii['open'] = '<a href="'.$arr['__url__'].'" target="_blank">';
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
					if ($is_dashboard || Base_User_SettingsCommon::get(Base_Menu_QuickAccessCommon::module_name(),$v['name'].'_l')) {
						$ii = array();
						$trimmed_label = trim(substr(strrchr($v['label'],':'),1));
						$ii['label'] = $trimmed_label?$trimmed_label:$v['label'];
						$ii['description'] = $v['label'];
						$arr = $v['link'];
						if(isset($arr['__url__']))
							$ii['open'] = '<a href="'.$arr['__url__'].'" target="_blank" onClick="actionbar_launchpad_deactivate()">';
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

		// Alphabetical by the SAME trimmed label actually displayed (e.g.
		// "Bug tracker: Tickets" -> "Tickets") - not $v['label']'s full
		// breadcrumb-prefixed form, which is what get_options() itself
		// sorts by (grouping every item alphabetically by its PARENT menu
		// name first) and reads as scrambled once only the trimmed leaf
		// name is shown. Matches launchpad()'s own compare_launcher(),
		// which already sorts by this same trimmed label.
		usort($launcher, fn($a, $b) => strcmp($a['label'], $b['label']));

		//display
		$th = $this->pack_module(Base_Theme::module_name());
		$th->assign('icons',$icons);
		$th->assign('launcher',$launcher);
		$th->display();
	}
	
	public function launchpad() {
		if (self::$launchpad==null) return;

		$launcher = array();
		usort(self::$launchpad,$this->compare_launcher(...));
		if(!empty(self::$launchpad)) {
			$icon = Base_ThemeCommon::get_template_file($this->get_type(),'launcher.png');
			$th = $this->pack_module(Base_Theme::module_name());
			usort(self::$launchpad,$this->compare_launcher(...));
			$th->assign('icons',self::$launchpad);
			eval_js_once('actionbar_launchpad_deactivate = function(){leightbox_deactivate(\'actionbar_launchpad\');}');
			ob_start();
			$th->display('launchpad');
			$lp_out = ob_get_clean();
			$big = count(self::$launchpad)>10;
			Libs_LeightboxCommon::display('actionbar_launchpad',$lp_out,__('Launchpad'),$big);
			// AdminLTE(-dark): the Launchpad trigger now lives in the top
			// navbar itself (Base_Box/theme_adminltedark/default.tpl's
			// #top_bar .epesi-launchpad-trigger, shown on both desktop and
			// mobile - per request), so adding a second button for it here
			// would just be redundant - both would open this same
			// 'actionbar_launchpad' popup (already registered above
			// regardless of theme, since the navbar icon depends on it).
			// The legacy theme has no such navbar icon, so it still gets
			// this ActionBar button exactly as before.
			if (!Base_ThemeCommon::is_adminlte_family()) {
				$launcher[] = array('label'=>__('Launchpad'),'description'=>'Quick modules launcher','open'=>'<a '.Libs_LeightboxCommon::get_open_href('actionbar_launchpad').'>','close'=>'</a>','icon'=>$icon);
				$th = $this->pack_module(Base_Theme::module_name());
				$th->assign('icons',array());
				$th->assign('launcher',array_reverse($launcher));
				$th->display();
				// Both ids are printed once in Base_Box's own shell markup (not
				// re-rendered by ordinary AJAX navigation) - guarded rather than
				// assumed present, since this eval_js runs inside the same shared
				// per-request append_js blob as every other queued module's own
				// script (Epesi::get_output()), where an uncaught exception here
				// would abort every one of THOSE too, not just this call. Was a
				// bare $("...").style.display=... (throws on a null lookup), the
				// exact failure Base_Box/theme_adminlte/default.tpl's own comment
				// on these two ids already warned about without this file having
				// been updated to match.
				eval_js('if(document.getElementById("launchpad_button_section"))document.getElementById("launchpad_button_section").style.display="";');
				eval_js('if(document.getElementById("launchpad_button_section_spacing"))document.getElementById("launchpad_button_section_spacing").style.display="";');
			}
		}
	}

}

?>
