<?php

/**
 *  Calendar
 *
 * Author: Kuba Slawinski
 * and Janusz Tylek
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Calendar extends Module {
	private $lang;
	
	public function viewer($view, $date = null) {
		$v = $this->init_module('Utils/Calendar/View/'.$view);
		$this->display_module($v, array($date));
	}

	public function body($arg = null) {
		$tb = $this->init_module('Utils/TabbedBrowser');
		$this->lang = $this->init_module('Base/Lang');

		$tb->set_tab($this->lang->t('Day'),array($this, 'viewer'), 'Day');
		$tb->set_tab($this->lang->t('Week'),array($this, 'viewer'), 'Week');
		$tb->set_tab($this->lang->t('Month'),array($this, 'viewer'), 'Month');
		$tb->set_tab($this->lang->t('Year'),array($this, 'viewer'), 'Year');
		$tb->set_tab($this->lang->t('Agenda'),array($this, 'viewer'), 'Agenda');

		$this->display_module($tb);

//		print($this->pack_module('CRM/Profiles',null,null,'xxx')->get());
	}

	public function caption() {
		return 'Calendar';
	}
}
?>
