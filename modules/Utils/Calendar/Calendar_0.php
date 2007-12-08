<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Calendar extends Module {
	private $lang;
	private static $views = array('Agenda','Day','Week','Month','Year');
	private $settings = array('first_day_of_week'=>0,
				  'default_view'=>'Agenda',
				  'start_day'=>8,
				  'end_day'=>17);

	public function construct(array $settings) {
		$this->lang = $this->init_module('Base/Lang');
		$this->settings = array_merge($this->settings,$settings);
	}

	public function viewer($view) {
		$v = $this->init_module('Utils/Calendar/View/'.$view,array($this->settings));
		$this->display_module($v);
	}

	public function body($arg = null) {
		$tb = $this->init_module('Utils/TabbedBrowser');

		foreach(self::$views as $k=>$v) {
			$tb->set_tab($this->lang->t($v),array($this, 'viewer'), $v);
			if(strcasecmp($v,$this->settings['default_view'])==0)
				$def_tab = $k;
		}
		if(isset($def_tab)) $tb->set_default_tab($def_tab);

		$this->display_module($tb);
	}

	public function caption() {
		return 'Calendar';
	}
}
?>
