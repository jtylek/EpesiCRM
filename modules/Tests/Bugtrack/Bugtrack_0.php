<?php
/**
 * Software Development - Bug Tracking
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-tests
 * @subpackage bugtrack
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Bugtrack extends Module {
	private $rb;

	public function body() {
		$this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'bugtrack','bugtrack');
		$this->display_module($this->rb);
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