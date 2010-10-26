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
		$this->rb->set_filters_defaults(array('employees'=>$this->rb->crm_perspective_default()));
		$this->rb->set_defaults(array('date_and_time'=>date('Y-m-d H:i:s'), 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>'1'));
		$this->rb->set_default_order(array('status'=>'ASC', 'date_and_time'=>'ASC', 'subject'=>'ASC'));
		$this->display_module($this->rb);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

	public function phonecall_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array('phonecall/'.$arg['id']));
		$a->action_bar_attach_file(false);
		$a->enable_watchdog('phonecall',$arg['id']);
		$a->set_view_func(array('CRM_PhoneCallCommon','search_format'),array($arg['id']));
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
		$crits = array('employees'=>array($me['id']), '!status'=>array(2,3));
		if (!isset($conf['past']) || !$conf['past'])
			$crits['>=date_and_time'] = date('Y-m-d 00:00:00');
		if (!isset($conf['today']) || !$conf['today']) {
			$crits['(>=date_and_time'] = date('Y-m-d 00:00:00', strtotime('+1 day'));
			$crits['|<date_and_time'] = date('Y-m-d 00:00:00');
		}
		if ($conf['future']!=-1)
			$crits['<=date_and_time'] = date('Y-m-d 23:59:59', strtotime('+'.$conf['future'].' day'));
		$conds = array(
									array(	array('field'=>'contact_name', 'width'=>20, 'cut'=>14),
											array('field'=>'phone_number', 'width'=>1, 'cut'=>15),
											array('field'=>'status', 'width'=>1)
										),
									$crits,
									array('status'=>'ASC','date_and_time'=>'ASC','priority'=>'DESC'),
									array('CRM_PhoneCallCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		$date = $this->get_module_variable('applet_date',date('Y-m-d H:i:s'));
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('phonecall',array('date_and_time'=>$date, 'employees'=>array($me['id']), 'permission'=>'0', 'status'=>'0', 'priority'=>'1'));
		$this->display_module($rb, $conds, 'mini_view');
	}

	public function messanger_addon($arg) {
		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('id'=>$arg['employees']), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
		foreach($ret as $c_id=>$data)
			if(is_numeric($data['login'])) {
				$emp[$data['login']] = CRM_ContactsCommon::contact_format_no_company($data);
			}
		$mes = $this->init_module('Utils/Messenger',array('CRM_PhoneCall:'.$arg['id'],array('CRM_PhoneCallCommon','get_alarm'),array($arg['id']),strtotime($arg['date_and_time']),$emp));
//		$mes->set_inline_display();
		$this->display_module($mes);
	}
}
?>
