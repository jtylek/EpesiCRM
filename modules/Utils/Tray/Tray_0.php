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
 * 		- __slots__ setting defines the slot settings: __name__, __filters__, __crits__, __weight__, __module__
 * 			= __name__ setting determines the tray display title. 
 * 			Make sure the name is defined using _M('name') function
 * 			= __weight__ setting defines the order in which the slots are displayed
 * 			= __filters__ setting defines the default filter values that represent the records contained in each slot
 * 			= __crits__ setting is crits or a callback that defines crits for the slot
 * 			= __module__ setting defines the module to jump to, default is the module where tray method was called from
 * 			= __ignore_limit__ - boolean - the slot is displayed irrelevant of slot limit set - can be used for important slots
 * 
 * 		- __weight__ setting defines the order in which the trays are displayed
 * 
 * 		- __ignore_limit__ - boolean - the box is displayed irrelevant of box limit set - can be used for important boxes
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
 * To initiate tray for a recordset use the Utils_TrayCommon::enable method. It makes sure the tray filters are set when navigating with tray slot click 
 * 
 * Sample (in the install() method of the module:
 * 
 * Utils_TrayCommon::enable('premium_projects'); * 
 * 
 * 
 * ****** Below is the DEPRECATED method of initiating Tray ******
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
 * @copyright Copyright &copy; 2019, Georgi Hristov
 * @license MIT
 * @version 2.0
 * @package epesi-tray
 * 
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tray extends Module {
	private $max_trays = '__NULL__';
	private $max_slots = '__NULL__';
	private $hide_empty_slots = 0;
	
	/**
	 * @var Utils_Tray_Box[]
	 */
	private $boxes = [];
	
	public function body() {
		if(!Base_AclCommon::check_permission('Dashboard')) return;
		Base_ActionBarCommon::add('settings',__('Settings'), $this->create_callback_href([$this, 'push_settings'], ['Tray settings']), __('Click to edit tray settings'));

		$this->output();
	}
	
	public function tray_tab_browse_details($form, & $external_filters, & $vals, & $filter_crits, $rb_obj) {
		if (!Acl::check_permission('Dashboard')) return;

		$this->navigate();
		
		$this->set_inline_display();

		/**
		 * @var Utils_Tray_Slot|boolean $slot
		 */
		$slot = $this->get_selected_slot($rb_obj);
		
		if (!$slot) return;

		if ($slot->filtersMatch() || $slot->getCrits()) {
			$rb_obj->set_additional_caption($slot->getName());			
			
			foreach ( $slot->getFilters() as $field => $value ) {
				$vals['filter__' . $field] = $value;
			}
			
			$form->setDefaults($vals);			
		}
		
		if (!$slot->filtersExist() && $slot->getCrits())
			$filter_crits = $slot->getCrits();
	}
	
	public function navigate() {
		$this->set_module_variable('navigated', false);
		
		if ($this->is_back())
			return Base_BoxCommon::pop_main();

		if (isset($_REQUEST['tray_slot'])) {
			Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
			$this->set_module_variable('tray_slot', $_REQUEST['tray_slot']);
			$this->set_module_variable('navigated', true);
		}
	}

	public function applet($conf, & $opts) {
		$opts['go'] = true; // enable/disable full screen
		$opts['title'] = $conf['title'];
		$this->max_trays = $conf['max_trays'];
		$this->max_slots = $conf['max_slots'];
		$this->hide_empty_slots = $conf['hide_empty_slots'];

		$this->output(true);
	}

	private function output($applet = false) {
		$this->init_boxes();

		$boxes = [];
		foreach ($this->boxes as $box) {
			if (is_numeric($this->max_trays)) {
				//allow only trays in applet mode as per setting
				if (count($boxes) >= $this->max_trays && !$box->getIgnoreLimit()) continue;
			}

			$slots = [];			
			foreach ( $box->setSlotsLimit($this->max_slots)->getSlots() as $slot ) {
				if (!$slot->isVisible($this->hide_empty_slots)) continue;
				
				$slots[] = $slot->getHtml();
			}
			
			if (!$slots) continue;
			
			$boxes[] = [
					'title' => _V($box->getTitle()),
					'slots' => $slots
			];
		}
	
		$theme = $this->init_module(Base_Theme::module_name());

		$theme->assign('main_page', !$applet);
		$theme->assign('caption', Utils_TrayCommon::caption());
		$theme->assign('icon', Base_ThemeCommon::get_template_file($this->get_type(),'pile2.png'));
		$theme->assign('boxes', $boxes);
		$theme->assign('box_cols', $applet? 2:$this->get_tray_cols());

		$theme->display('tray');
	}
	
	public function init_boxes() {
		$tray_settings = Utils_TrayCommon::get_trays();

		foreach ($tray_settings as $module=>$module_settings) {
			foreach ($module_settings as $tab=>$box_settings) {
				if (!isset($box_settings['__title__'])) continue;
				$box_id = $this->get_type() . '__' . Utils_RecordBrowserCommon::get_field_id($box_settings['__title__']);
				
				$slot_definitions = $box_settings['__slots__'];
				unset($box_settings['__slots__']);
				
				if (!isset($this->boxes[$box_id]))
					$this->boxes[$box_id] = new Utils_Tray_Box($box_settings);
					
				$this->boxes[$box_id]->setSlots($module, $tab, $slot_definitions, $box_settings['__trans_callbacks__']?? []);
			}
		}
		
		uasort($this->boxes, function (Utils_Tray_Box $a, Utils_Tray_Box $b) {
			return $a->getWeight() - $b->getWeight();
		});
	}
	
	public function get_tab_slots($tab) {
		if (!$this->boxes) $this->init_boxes();
		
		$ret = [];
		foreach ($this->boxes as $box) {
			foreach ($box->getSlots() as $slot) {
				if ($slot->getTab() == $tab) $ret[$slot->getId()] = $slot;
			}
		}
		
		return $ret;
	}

	/**
	 * @param Utils_RecordBrowser $rb_obj
	 * @param string $display_tray_select
	 * @param array $filter_defaults
	 * 
	 * Deprecated method, use Utils_TrayCommon::enable instead
	 */
	public function set_filters($rb_obj, $display_tray_select = true, $filter_defaults = array()) {
		if(!Acl::check_permission('Dashboard')) return;
		
		$this->navigate();

		$filter_defaults = $this->get_filter_values($rb_obj, $display_tray_select)?: $filter_defaults;
		
		$rb_obj->set_filters_defaults($filter_defaults);
	}
	
	public function get_selected_slot($rb_obj) {
		$this->init_boxes();
		
		$slots = $this->get_tab_slots($rb_obj->tab);
		if (! $slots) return false;
		
		$tray_slot_select_options = [
				'__NULL__' => '---'
		];
		foreach ( $slots as $slot_id => $slot ) {
			$tray_slot_select_options[$slot_id] = _V($slot->getName());
		}
		
		$form = $this->init_module(Libs_QuickForm::module_name());
		$form->addElement('select', 'tray_slot_select', __('Tray'), $tray_slot_select_options, array(
				'style' => 'width: 130px',
				'onchange' => $form->get_submit_form_js()
		));
		
		if ($form->validate()) {
			$tray_slot = $form->exportValue('tray_slot_select');
			$this->set_module_variable('tray_slot', $tray_slot);
			$this->set_module_variable('navigated', true);
			
			$rb_obj->unset_module_variable('def_filter');
		}
		
		$tray_slot = $this->get_module_variable('tray_slot');
		
		$form->setDefaults(array(
				'tray_slot_select' => $tray_slot
		));
		
		$ret = (! is_null($tray_slot) && ($tray_slot != '__NULL__') && isset($tray_slot)) ? $slots[$tray_slot]: false;
		
		if ($ret instanceof Utils_Tray_Slot && !$this->get_module_variable('navigated', false) && !$ret->filtersMatch()) {
			$this->set_module_variable('tray_slot', '__NULL__');
			$form->setDefaults([
					'tray_slot_select' => '__NULL__'
			]);
			
			$ret = false;
		}
		
		ob_start();
		$form->display_as_row();
		$html = ob_get_clean();
		print('<div style="position: absolute;right:120px;">' . $html . '</div>');
		
		return $ret;
	}
	
	public function get_tray_cols() {
		$tray_cols = Base_User_SettingsCommon::get($this->module_name(),'tray_cols');
		if (!isset(Utils_TrayCommon::$tray_cols[$tray_cols])) {
			$tray_cols = 3;
			Base_User_SettingsCommon::save($this->module_name(),'tray_cols', 3);
		}
		return $tray_cols;
	}

	public function get_tray_layout() {
		$tray_layout = Base_User_SettingsCommon::get($this->module_name(),'tray_layout');
		if (!isset(Utils_TrayCommon::$tray_layout[$tray_layout])) {
			$tray_layout = 'checkered';
			Base_User_SettingsCommon::save($this->module_name(),'tray_layout', 'checkered');
		}
		return $tray_layout;
	}

	private function get_icon($records_count) {
		$limits = [
				10 => 'pile3',
				5 => 'pile2',
				1 => 'pile1',
				0 => 'pile0'
		];

		foreach ($limits as $limit=>$file) {
			if ($records_count >= $limit) break;
		}

		return Base_ThemeCommon::get_template_file($this->get_type(), $file.'.png');
	}

	public function push_settings($s) {
		Base_BoxCommon::push_module('Base_User_Settings', null, [_V($s)]);
	}
	
	public function caption() {
		return __('Tray');
	}
}
?>