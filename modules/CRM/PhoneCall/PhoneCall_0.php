<?php
/**
 * CRM Phone Call Class
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage phonecall
 */

defined("_VALID_ACCESS") || die();

class CRM_PhoneCall extends Module {
	private $rb = null;

	public function body() {
		$this->help('main','Phone Call Help');

		$this->rb = $this->init_module('Utils/RecordBrowser','phonecall','phonecall');
		$me = CRM_ContactsCommon::get_my_record();
		$this->rb->set_custom_filter('status',array('type'=>'checkbox','label'=>$this->t('Display closed records'),'trans'=>array('__NULL__'=>array('!status'=>array(2,3)),1=>array('status'=>array(0,1,2,3)))));
		$this->rb->set_crm_filter('employees');
		$this->rb->set_defaults(array('date_and_time'=>date('Y-m-d H:i:s'), 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>'1'));
		$this->rb->set_default_order(array('status'=>'ASC', 'date_and_time'=>'ASC', 'subject'=>'ASC'));
		$this->display_module($this->rb);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

	public function phonecall_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array('CRM/PhoneCall/'.$arg['id']));
		$a->enable_watchdog('phonecall',$arg['id']);
		$a->additional_header($this->t('Phone Call: %s',array($arg['subject'])));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

	public function applet($conf,$opts) {
		$opts['go'] = true;
		$rb = $this->init_module('Utils/RecordBrowser','phonecall','phonecall');
		$me = CRM_ContactsCommon::get_my_record();
		if ($me['id']==-1) {
			CRM_ContactsCommon::no_contact_message();
			return;
		}
		$conds = array(
									array(	array('field'=>'contact_name', 'width'=>20, 'cut'=>14),
											array('field'=>'phone_number', 'width'=>1, 'cut'=>15),
											array('field'=>'status', 'width'=>1)
										),
									array('employees'=>array($me['id']), '!status'=>array(2,3)),
									array('status'=>'ASC','date_and_time'=>'ASC','priority'=>'DESC'),
									array('CRM_PhoneCallCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('phonecall',array('date_and_time'=>date('Y-m-d H:i:s'), 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>'1'));
		$this->display_module($rb, $conds, 'mini_view');
	}

}
?>
