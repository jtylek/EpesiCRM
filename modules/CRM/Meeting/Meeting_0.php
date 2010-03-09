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
		$this->rb->set_crm_filter('employees');
		$this->rb->set_defaults(array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1, 'date'=>date('Y-m-d'), 'time'=>date('H:i:s'), 'duration'=>3600));
		$this->display_module($this->rb);
	}

	public function applet($conf,$opts) {
		$opts['go'] = true;
		$opts['title'] = Base_LangCommon::ts('CRM/Tasks','Tasks').
						($conf['related']==0?Base_LangCommon::ts('CRM/Tasks',' - Todo'):'').
						($conf['related']==1?Base_LangCommon::ts('CRM/Tasks',' - Related'):'').
						($conf['term']=='s'?Base_LangCommon::ts('CRM/Tasks',' - short term'):($conf['term']=='l'?Base_LangCommon::ts('CRM/Tasks',' - long term'):''));
		$me = CRM_ContactsCommon::get_my_record();
		if ($me['id']==-1) {
			CRM_ContactsCommon::no_contact_message();
			return;
		}
		$short = ($conf['term']=='s' || $conf['term']=='b');
		$long = ($conf['term']=='l' || $conf['term']=='b');
		$closed = (isset($conf['closed']) && $conf['closed']);
		$related = $conf['related'];
		$rb = $this->init_module('Utils/RecordBrowser','task','task');
		$crits = array();
		if (!$closed) $crits['!status'] = array(2,3);
		if ($short && !$long) $crits['!longterm'] = 1;
		if (!$short && $long) $crits['longterm'] = 1;
		if ($related==0) $crits['employees'] = array($me['id']);
		if ($related==1) $crits['customers'] = array($me['id']);
		if ($related==2) {
			$crits['(employees'] = array($me['id']);
			$crits['|customers'] = array($me['id']);
		}
		$conds = array(
									array(	array('field'=>'title', 'width'=>20, 'cut'=>16, 'callback'=>array('CRM_TasksCommon','display_title_with_mark')),
											array('field'=>'deadline', 'width'=>1),
											array('field'=>'status', 'width'=>1)
										),
									$crits,
									array('deadline'=>'ASC','status'=>'ASC','priority'=>'DESC'),
									array('CRM_TasksCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('task',array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1));
		$this->display_module($rb, $conds, 'mini_view');
	}

	public function meeting_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array('CRM/Calendar/Event/'.$arg['id']));
		$a->enable_watchdog('crm_meeting',$arg['id']);
		$a->additional_header('Meeting: '.$arg['title']);
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}

?>