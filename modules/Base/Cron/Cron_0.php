<?php
/**
 * Cron Epesi
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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

        $theme = $this->init_module(Base_Theme::module_name());

        $new_token_href = $this->create_confirm_callback_href(__('Are you sure?'), array($this, 'new_token'));
        $theme->assign('new_token_href', $new_token_href);
        $theme->assign('wiki_url', 'http://www.epesi.org/Cron');
        $theme->assign('cron_url', Base_CronCommon::get_cron_url());

 		$m = $this->init_module(Utils_GenericBrowser::module_name(),null,'cron');
 		$m->set_table_columns(array(
							  array('name'=>'Description','width'=>65),
							  array('name'=>'Last Run','width'=>20),
							  array('name'=>'Running','width'=>15)));
		$ret = DB::Execute('SELECT description,last,running FROM cron ORDER BY last DESC');
		while($row = $ret->FetchRow()) {
			$m->add_row($row['description']?$row['description']:'???',$row['last']?Base_RegionalSettingsCommon::time2reg($row['last']):'---',$row['running']?'<span style="color:red">'.__('Yes').'</span>':'<span style="color:green">'.__('No').'</span>');
		}
 		$html = $this->get_html_of_module($m);
        $theme->assign('history', $html);
        $theme->display();
	}

    public function new_token()
    {
        Base_CronCommon::generate_token();
    }
	
	public function body() {
	}

	public function caption() {
		return __('Cron');
	}

}

?>
