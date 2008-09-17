<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package crm-tasks
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Tasks extends Module {
	private $rb = null;

	public function body() {
		$lang = $this->init_module('Base/Lang');
		$this->rb = $this->init_module('Utils/RecordBrowser','task','task');
		$me = CRM_ContactsCommon::get_my_record();
		$this->rb->set_custom_filter('status',array('type'=>'checkbox','label'=>$lang->t('Display closed tasks'),'trans'=>array('__NULL__'=>array('!status'=>array(2,3)),1=>array('status'=>array(0,1,2,3)))));
		$this->rb->set_custom_filter('longterm',array('type'=>'select','label'=>$lang->t('Display tasks marked as'),'args'=>array('__NULL__'=>$lang->t('Both'),1=>$lang->t('Short term'),2=>$lang->t('Long term')),'trans'=>array('__NULL__'=>array('!longterm'=>2),1=>array('!longterm'=>1),2=>array('longterm'=>1))));
		$this->rb->set_crm_filter('employees');
		$this->rb->set_defaults(array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1));
		$this->rb->set_default_order(array('deadline'=>'ASC', 'longterm'=>'ASC', 'priority'=>'DESC', 'title'=>'ASC'));
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
									array('is_deadline'=>'DESC','deadline'=>'ASC','status'=>'ASC','priority'=>'DESC'),
									array('CRM_TasksCommon','applet_info_format'),
									15,
									$conf,
									& $opts
				);
		$opts['actions'][] = Utils_RecordBrowserCommon::applet_new_record_button('task',array('employees'=>array($me['id']),'status'=>0, 'permission'=>0, 'priority'=>1));
		$this->display_module($rb, $conds, 'mini_view');
	}



	public function task_attachment_addon($arg){
		$lang = $this->init_module('Base/Lang');
		$a = $this->init_module('Utils/Attachment',array('Task:'.$arg['id'],'CRM/Tasks/'.md5('crm_tasks')));
		$a->enable_watchdog('task',$arg['id']);
		$a->additional_header($lang->t('Task: %s',array($arg['title'])));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}
}

?>