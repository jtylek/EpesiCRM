<?php
/**
 * Software Development - Bug Tracking
 *
 * @author Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-tests
 * @subpackage bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Bugtrack extends Module {
	private $rb;

	public function body() {
		$this->rb = $this->init_module('Utils/RecordBrowser','bugtrack','bugtrack');
		$this->display_module($this->rb);
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

public function bugtrack_attachment_addon($arg){
		$a = $this->init_module('Utils/Attachment',array('Tests/Bugtrack/'.$arg['id']));
		$a->set_view_func(array('Tests_BugtrackCommon','search_format'),array($arg['id']));
		//$a->additional_header('Bugtrack Project: '.$arg['Project Name']);
		$a->allow_protected($this->acl_check('view protected notes'),$this->acl_check('edit protected notes'));
		$a->allow_public($this->acl_check('view public notes'),$this->acl_check('edit public notes'));
		$this->display_module($a);
	}

public function company_bugtrack_addon($arg){
		$rb = $this->init_module('Utils/RecordBrowser','bugtrack');
		$proj = array(array('company_name'=>$arg['id']), array('company_name'=>false), array('Fav'=>'DESC'));
		$this->display_module($rb,$proj,'show_data');
	}

}

?>