<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage meetings
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Meeting extends Module {
	private $rb = null;

	public function body() {
		$this->rb = $this->init_module('Utils/RecordBrowser','crm_meeting','crm_meeting');
		$me = CRM_ContactsCommon::get_my_record();
		CRM_CommonCommon::status_filter($this->rb);
		$this->rb->set_filters_defaults(array('employees'=>$this->rb->crm_perspective_default(), 'date__to'=>date('Y-m-d')));
		$this->rb->set_defaults(array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1, 'date'=>date('Y-m-d'), 'time'=>date('H:i:s'), 'duration'=>3600));
		$this->rb->set_default_order(array('date'=>'DESC', 'time'=>'DESC', 'status'=>'DESC'));
		$this->display_module($this->rb);
	}

	public function applet($conf, & $opts) {
		$opts['go'] = true;
		$opts['title'] = __('Meetings');
		$me = CRM_ContactsCommon::get_my_record();
		if ($me['id']==-1) {
			CRM_ContactsCommon::no_contact_message();
			return;
		}
		$closed = (isset($conf['closed']) && $conf['closed']);
		$related = $conf['related'];
		$rb = $this->init_module('Utils/RecordBrowser','crm_meeting','crm_meeting');
		$crits = array();
		if (!$closed) $crits['!status'] = array(2,3);
		if ($related==0) $crits['employees'] = array($me['id']);
		if ($related==1) $crits['customers'] = array($me['id']);
		if ($related==2) {
			$crits['(employees'] = array($me['id']);
			$crits['|customers'] = array($me['id']);
		}
		$conds = array(
									array(	array('field'=>'title', 'width'=>14, 'callback'=>array('CRM_MeetingCommon','display_title_with_mark')),
											array('field'=>'status', 'width'=>4)
										),
									$crits,
									array('date'=>'ASC','time'=>'ASC','status'=>'ASC','priority'=>'DESC'),
									array('CRM_MeetingCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('crm_meeting',array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1, 'date'=>$this->get_module_variable('date',date('Y-m-d')), 'time'=>$this->get_module_variable('time',date('H:i:s')), 'duration'=>3600));
		$this->display_module($rb, $conds, 'mini_view');
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

	public function messanger_addon($arg) {
		$emp = array();
		$ret = CRM_ContactsCommon::get_contacts(array('id'=>$arg['employees']), array(), array('last_name'=>'ASC', 'first_name'=>'ASC'));
		foreach($ret as $c_id=>$data)
			if(is_numeric($data['login'])) {
				$emp[$data['login']] = CRM_ContactsCommon::contact_format_no_company($data);
			}
		$mes = $this->init_module('Utils/Messenger',array('CRM_Calendar_Event:'.$arg['id'],array('CRM_MeetingCommon','get_alarm'),array($arg['id']),strtotime($arg['date'].' '.date('H:i:s',strtotime($arg['time']))),$emp,'CRM_Meeting'));
//		$mes->set_inline_display();
		$this->display_module($mes);
	}

}

?>