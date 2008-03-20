<?php
/**
 * CRMHR class.
 *
 * This class is just my first module, test only.
 *
 * @author Kuba Sławiński <ruud@o2.pl>, Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-extra
 */

defined("_VALID_ACCESS") || die();

class CRM_PhoneCall extends Module {
	private $rb = null;

	public function body() {
		$lang = $this->init_module('Base/Lang');
		$this->rb = $this->init_module('Utils/RecordBrowser','phonecall','phonecall');
		$me = CRM_ContactsCommon::get_my_record();
		$this->rb->set_custom_filter('status',array('type'=>'checkbox','label'=>$lang->t('Display closed records'),'trans'=>array('__NULL__'=>array('!status'=>2),1=>array('status'=>array(0,1,2)))));
		$this->rb->set_crm_filter('employees');
		$this->rb->set_defaults(array('date_and_time'=>date('Y-m-d H:i:s'), 'employees'=>array($me['id'])));
		$this->display_module($this->rb);
	}

	public function applet($conf,$opts) {
		$opts['go'] = true;
		$rb = $this->init_module('Utils/RecordBrowser','phonecall','phonecall');
		$me = CRM_ContactsCommon::get_my_record();
		$conds = array(
									array(	array('field'=>'contact_name', 'width'=>20, 'cut'=>14),
											array('field'=>'phone_number', 'width'=>1),
											array('field'=>'status', 'width'=>1)
										),
									array('employees'=>array($me['id']), '!status'=>2),
									array('date_and_time'=>'ASC'),
									array('CRM_PhoneCallCommon','applet_info_format')
				);
		$this->display_module($rb, $conds, 'mini_view');
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}
?>
