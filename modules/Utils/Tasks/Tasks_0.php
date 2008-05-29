<?php
/**
 * CRMHR class.
 *
 * This class is just my first module, test only.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.99
 * @package tcms-extra
 */

defined("_VALID_ACCESS") || die();

class Utils_Tasks extends Module {
	private $rb = null;

	public function body($id) {
		$mid = md5($id);
		$lang = $this->init_module('Base/Lang');
		$this->rb = $this->init_module('Utils/RecordBrowser','task','task');
		$me = CRM_ContactsCommon::get_my_record();
		$this->rb->set_custom_filter('status',array('type'=>'checkbox','label'=>$lang->t('Display closed tasks'),'trans'=>array('__NULL__'=>array('!status'=>array(2,3)),1=>array('status'=>array(0,1,2,3)))));
		$this->rb->set_custom_filter('longterm',array('type'=>'select','label'=>$lang->t('Display tasks marked as'),'args'=>array('__NULL__'=>$lang->t('Short term'),1=>$lang->t('Long term'),2=>$lang->t('Both')),'trans'=>array('__NULL__'=>array('!longterm'=>1),1=>array('longterm'=>1),2=>array('!longterm'=>array(2)))));
		$this->rb->set_crm_filter('employees');
		$this->rb->set_defaults(array('page_id'=>$mid, 'employees'=>array($me['id'])));
		$this->rb->set_default_order(array('deadline'=>'ASC', 'longterm'=>'ASC', 'title'=>'ASC'));
		if (is_numeric(Utils_RecordBrowser::$clone_result)) {
			$me = CRM_ContactsCommon::get_my_record();
			$task = Utils_TasksCommon::get_task(Utils_RecordBrowser::$clone_result);
			if (in_array($me['id'], $task['employees'])) Utils_TasksCommon::set_notified($me['id'], Utils_RecordBrowser::$clone_result);
		}
		$this->display_module($this->rb, array(array(), array('page_id'=>$mid)));
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

	public function task_attachment_addon($arg){
		$lang = $this->init_module('Base/Lang');
		$a = $this->init_module('Utils/Attachment',array('Task:'.$arg['id'],'CRM/Tasks/'.$arg['page_id']));
		$a->additional_header($lang->t('Task: %s',array($arg['title'])));
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

	public function applet($page_id=null,$short=true,$long=true,$closed=false) {
		$opts['go'] = true;
		$rb = $this->init_module('Utils/RecordBrowser','task','task');
		$me = CRM_ContactsCommon::get_my_record();
		$crits = array('employees'=>array($me['id']), 'page_id'=>md5($page_id));
		if (!$closed) $crits['!status'] = array(2,3);
		if ($short && !$long) $crits['!longterm'] = 1;
		if (!$short && $long) $crits['longterm'] = 1;
		$conds = array(
									array(	array('field'=>'title', 'width'=>20, 'cut'=>16, 'callback'=>array('Utils_TasksCommon','display_title_with_mark')),
											array('field'=>'deadline', 'width'=>1),
											array('field'=>'status', 'width'=>1)
										),
									$crits,
									array('status'=>'ASC','deadline'=>'ASC'),
									array('Utils_TasksCommon','applet_info_format'),
									15
				);
		$this->display_module($rb, $conds, 'mini_view');
	}

}
?>
