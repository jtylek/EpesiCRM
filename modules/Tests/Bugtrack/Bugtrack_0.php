<?php
/**
 * Software Development - Bug Tracking
 *
 * @author Janusz Tylek and Claude Code AI
 * @version 2.0
 * @license MIT
 * @package epesi-tests
 * @subpackage bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Bugtrack extends Module {
	private $rb;

	public function body() {
		$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'bugtrack','bugtrack');
		$this->display_module($this->rb);
		TestsCommon::source_card($this, 'modules/Tests/Bugtrack/', array(
			'Install' => 'BugtrackInstall.php',
			'Main' => 'Bugtrack_0.php',
			'Common' => 'BugtrackCommon_0.php',
		));
	}

	public function caption(){
		if (isset($this->rb)) return $this->rb->caption();
	}

public function company_bugtrack_addon($arg){
		$rb = $this->init_module(Utils_RecordBrowser::module_name(),'bugtrack');
		$proj = array(array('company_name'=>$arg['id']), array('company_name'=>false), array('Fav'=>'DESC'));
		$this->display_module($rb,$proj,'show_data');
	}

}

?>