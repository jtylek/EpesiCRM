<?php
/**
 * Usage: Include a tray settings method in your module Common class. Sample is shown below.
 * 		General info:
 * 		- Each group is called 'tray' and each pile is called 'slot'. 
 * 		- One tray can have several slots each defined in one or several tray settings methods. 
 * 		- The '__title__' setting determines the tray that slots will be included in.
 * 		
 * 		Tray settings array can have following keys: __title__, __weight__, __slots__, __trans_callbacks__, __mobile__:
 * 		- __title__ setting determines the tray that slots will be included in.
 * 		Make sure the title is defined using _M('title') function.
 * 	
 * 		- __slots__ setting defines the slot settings: __name__, __filters__, __weight__
 * 			= __name__ setting determines the tray display title. 
 * 			Make sure the name is defined using _M('name') function
 * 			= __weight__ setting defines the order in which the slots are displayed
 * 			= __filters__ setting defines the default filter values that represent the records contained in each slot
 * 
 * 		- __weight__ setting defines the order in which the trays are displayed
 * 
 * 		- __trans_callbacks__ setting is the list of callback functions for each field that has custom filter defined
 * 
 * 		- __mobile__ setting defines the columns and sort order when displaying the records in the mobile version
 * 
 * public static function tray() {
 *		return array(
 *		'sample_tab'=> array(
 *			'__title__'=>_M('Tray Title'), 
 *			'__slots__'=>array(
 * 							array(
 * 							'__name__'=>_M('Pending Slot Title'),
 * 							'__filters__'=>array('project'=>'__INCHARGE__', 'status'=>1),
 * 							'__weight__'=>1),
 * 							array(
 * 							'__name__'=>_M('Overdue Slot Title')
 * 							'__filters__'=>array('project'=>'__INCHARGE__', 'status'=>'__OVERDUE__'),
 * 							'__weight__'=>2)), 							 
 * 			'__weight__'=>2,
 * 			'__trans_callbacks__'=>array('project'=>array('Custom_SampleCommon', 'project_filter_trans'), 'status'=>array('Custom_SampleCommon', 'status_filter_trans')),
 *			'__mobile__'=>array('cols'=>array('start_date'=>false, 'test_result'=>false, 'pdf_report'=>false))));
 *	}
 * 
 * 
 * Initiate the tray module in the body method of your module and call the set_filters method as per sample.
 * 
 * Sample:
 * 
 * public function body($mode) {
 *		$this->rb = $this->init_module(Utils_RecordBrowser::getName(), 'sample_tab', 'sample_tab');
 *		
 *		self::project_filter($this->rb); //defining custom filter for 'project' field
 *		self::status_filter($this->rb);  //defining custom filter for 'status' field
 *
 *		$this->tray = $this->init_module('Utils/Tray');
 *		$this->tray->set_filters($this->rb);		
 *
 *		$this->display_module($this->rb);
 *	}
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 1.0
 * @package epesi-tray
 * 
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tray extends Module {
	private $max_trays = '__NULL__';
	private $max_slots = '__NULL__';
	
	public function body() {
		if(!Base_AclCommon::check_permission('Dashboard')) return;
		Base_ActionBarCommon::add('settings',__('Settings'), $this->create_callback_href(array($this, 'push_settings'), array('Tray settings')), __('Click to edit tray settings'));

		$this->output();
	}

	public function applet($conf, & $opts) {
		$opts['go'] = true; // enable/disable full screen
		$opts['title'] = $conf['title'];
		$this->max_trays = $conf['max_trays'];
		$this->max_slots = $conf['max_slots'];

		$this->output(true);
	}

	private function output($applet = false) {
		Base_ThemeCommon::load_css($this->get_type(), 'tray');

		$tray_settings = Utils_TrayCommon::get_trays();

		$tray_def = array();
		$total_pending = 0;
		$displayed = 0;

		foreach ($tray_settings as $module=>$module_settings) {
			foreach ($module_settings as $tab=>$tab_settings) {
				if (!isset($tab_settings['__title__'])) continue;

				$tray = Utils_TrayCommon::get_tray($tab, $tab_settings);

				if (!isset($tray['__slots__']) || count($tray['__slots__'])==0) continue;

				$tray_id = $this->get_type().'__'.Utils_RecordBrowserCommon::get_field_id($tray['__title__']);

				$tray_def += array($tray_id =>array('__title__' => $tray['__title__'], '__weight__'=>isset($tray['__weight__'])?$tray['__weight__']:0));

				foreach ($tray['__slots__'] as $slot_id=>$slot_def) {
					$total_pending += $slot_def['__count__'];
					$displayed += $slot_def['__count__'];

					$tray_def[$tray_id]['__slots__'][$slot_id]['__weight__'] = isset($slot_def['__weight__'])? $slot_def['__weight__']: 0;

					$icon = $this->get_icon($slot_def['__count__']);
					$tray_count_width = ($slot_def['__count__']>99)? 'style="width:28px;"':'';

					$tip_text = __('Click to view %s items from %s<br><br>%d item(s)', array(_V($slot_def['__name__']),_V($tray['__title__']), $slot_def['__count__']));

					$tray_def[$tray_id]['__slots__'][$slot_id]['__html__'] = '<td><a '.$this->create_main_href($module, null, array($tab), null, array('tray_slot'=>$slot_id)).'><div class="Utils_Tray__slot">'.
					Utils_TooltipCommon::create('<img src="'.$icon.'">
					<div class="Utils_Tray__count" '.$tray_count_width.'>'.$slot_def['__count__'].'</div><div>'._V($slot_def['__name__']).'</div>',$tip_text).'</div></a></td>';				
				}
			}
		}

		Utils_TrayCommon::sort_trays($tray_def);

		$trays = array();
		$tray_slots_html = array();
		$current_tray = 0;

		$tray_cols = $applet? 2:$this->get_tray_cols();

		foreach ($tray_def as $tray_id=>$def) {
			$current_tray += 1;
			$current_row = max(array(round($current_tray/$tray_cols), 1));
			$current_col = $current_tray - $tray_cols*($current_row-1);

			if (isset($this->max_trays) && $this->max_trays != '__NULL__') {
				//allow only trays in applet mode as per setting
				if (count($trays) >= $this->max_trays) 	break;
			}

			if (self::get_tray_layout()=='checkered')
			$class = (($current_row+$current_col) % 2)?'Utils_Tray__group_even':'Utils_Tray__group_odd';
			else
			$class = 'Utils_Tray__group_even';

			$trays[] = array(
			'class' => $class,
			'col'=>$current_col,
			'title'=>_V($def['__title__']),
			'id'=>$tray_id);

			foreach ($def['__slots__'] as $slot) {
				$tray_slots_html[$tray_id][] = $slot['__html__'];

				if (isset($this->max_slots) && $this->max_slots != '__NULL__') {
					//allow slots in applet mode as per setting
					if (count($tray_slots_html[$tray_id]) >= $this->max_slots) continue 2;
				}
			}
		}

		eval_js(
		'function Utils_Tray__trays() {
			var trays = '.json_encode($tray_slots_html).';
			return trays;		
		}
		
		jq( document ).ready(function() {
			var resizeId;
			jq(window).resize(function(){
				clearTimeout(resizeId);
				resizeId = setTimeout(Utils_Tray__resize, 300);
			});	
			Utils_Tray__resize();	
		});');

		load_js($this->get_module_dir().'tray.js');

		$theme = $this->init_module(Base_Theme::module_name());
		$icon = Base_ThemeCommon::get_template_file($this->get_type(),'pile2.png');

		$theme->assign('main_page', !$applet);
		$theme->assign('caption', Utils_TrayCommon::caption());
		$theme->assign('icon', $icon);
		$theme->assign('trays', $trays);
		$theme->assign('tray_cols', $tray_cols);

		if ($total_pending!=$displayed) {
			print (__('Displaying %d of %d pending', array($displayed, $total_pending)));
		}

		$theme->display('tray');
	}

	public function set_filters($rb, $display_tray_select = true, $filter_defaults = array()) {
		if(!Base_AclCommon::check_permission('Dashboard')) return;
		if($this->is_back()) {
			$x = ModuleManager::get_instance('/Base_Box|0');
			if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
			return $x->push_main('Utils_Tray');
		}

		if (isset($_REQUEST['tray_slot'])) {
			Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
			$this->set_module_variable('tray_slot', $_REQUEST['tray_slot']);
		}

		$tray_func = $this->parent->get_type().'Common::tray';
		if (!is_callable($tray_func)) return;

		$settings = call_user_func($tray_func);

		if (!isset($settings[$rb->tab])) return;

		$slot_defs = Utils_TrayCommon::get_slots($rb->tab, $settings[$rb->tab]);
		Utils_TrayCommon::sort_trays($slot_defs);

		if ($display_tray_select) {
			$tray_slot_select_options = array('__NULL__'=>'---');
			foreach ($slot_defs as $slot_id=>$slot_def) {
				$tray_slot_select_options[$slot_id] = _V($slot_def['__name__']);
			}

			$form = $this->init_module(Libs_QuickForm::module_name());
			$form->addElement('select', 'tray_slot_select', __('Tray'), $tray_slot_select_options, array('style'=>'width: 130px','onchange'=>$form->get_submit_form_js()));
			if ($form->validate()) {
				$tray_slot = $form->exportValue('tray_slot_select');
				$this->set_module_variable('tray_slot',$tray_slot);
				
				$rb->unset_module_variable('def_filter');
			}
		}

		$tray_slot = $this->get_module_variable('tray_slot');
		
		if (isset($slot_defs[$tray_slot]['__filters__'])) {
			$filters_changed = Utils_TrayCommon::are_filters_changed($slot_defs[$tray_slot]['__filters__']);
		}
		else {
			$filters_changed = true;
		}

		if (!isset($_REQUEST['__location']) && ($tray_slot!='__NULL__') && isset($tray_slot) && !$filters_changed){
			$rb->set_additional_caption(_V($slot_defs[$tray_slot]['__name__']));
		}
		else {
			$this->unset_module_variable('tray_slot');
			$tray_slot='__NULL__';
		}

		if ($display_tray_select) {
			$form->setDefaults(array('tray_slot_select'=>$tray_slot));

			ob_start();
			$form->display_as_row();
			$html = ob_get_clean();
			print('<div style="position: absolute;right:120px;">'.$html.'</div>');
		}
		
		if (is_null($tray_slot) || $tray_slot=='__NULL__') {
			$rb->set_filters_defaults($filter_defaults);
			return;
		}
		
		$rb->disable_browse_mode_switch();

		$rb->set_filters($slot_defs[$tray_slot]['__filters__'], true, true);
	}

	public function get_tray_cols() {
		$tray_cols = Base_User_SettingsCommon::get('Utils/Tray','tray_cols');
		if (!isset(Utils_TrayCommon::$tray_cols[$tray_cols])) {
			$tray_cols = 3;
			Base_User_SettingsCommon::save('Utils/Tray','tray_cols', 3);
		}
		return $tray_cols;
	}

	public function get_tray_layout() {
		$tray_layout = Base_User_SettingsCommon::get('Utils/Tray','tray_layout');
		if (!isset(Utils_TrayCommon::$tray_layout[$tray_layout])) {
			$tray_layout = 'checkered';
			Base_User_SettingsCommon::save('Utils/Tray','tray_layout', 'checkered');
		}
		return $tray_layout;
	}

	private function get_icon($records_count) {
		$limits = array(10=>'pile3', 5=>'pile2', 1=>'pile1', 0=>'pile0');

		foreach ($limits as $limit=>$file) {
			if ($records_count >= $limit) break;
		}

		return Base_ThemeCommon::get_template_file($this->get_type(),$file.'.png');
	}

	public function push_settings($s) {
		$x = ModuleManager::get_instance('/Base_Box|0');
		if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
		$x->push_main('Base_User_Settings',null,array(_V($s)));
	}
	
	public function caption() {
		return __('Tray');
	}
}
?>