<?php
/**
 * Cron Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Cron extends Module {

	public function admin() {
		if ($this->is_back()) {
			$this->parent->reset();
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		
		print(__('Cron is used to periodically execute some job. Every module can define several methods with different intervals. All you need to do is to set up a system to run cron.php file every 1 minute.').'<br />');
		print(__('You can read more on our wiki: <a href="http://www.epesi.org/Cron">http://www.epesi.org/Cron</a>').'<br />'.'<br />');
		
 		$m = $this->init_module('Utils/GenericBrowser',null,'cron');
 		$m->set_table_columns(array(
							  array('name'=>'Description','width'=>65),
							  array('name'=>'Last Run','width'=>20),
							  array('name'=>'Running','width'=>15)));
		$ret = DB::Execute('SELECT description,last,running FROM cron ORDER BY last DESC');
		while($row = $ret->FetchRow()) {
			$m->add_row($row['description']?$row['description']:'???',$row['last']?Base_RegionalSettingsCommon::time2reg($row['last']):'---',$row['running']?'<span style="color:red">'.__('Yes').'</span>':'<span style="color:green">'.__('No').'</span>');
		}
 		$this->display_module($m);
	}

	public function body() {
	}

	public function caption() {
		return __('Cron');
	}

}

?>
