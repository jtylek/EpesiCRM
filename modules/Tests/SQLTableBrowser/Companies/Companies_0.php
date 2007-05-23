<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SQLTableBrowser_Companies extends Module {
	private $lang;

	public function body($arg) {
		$this->lang = $this->pack_module('Base/Lang');
		$gb = & $this->init_module('Utils/SQLTableBrowser','browse_sql');
		$gb->set_table_format(array(	array('label'=>$this->lang->t('Id'), 'width'=>20,'column'=>'id','order'=>1,'search'=>1),
										array('label'=>$this->lang->t('Name'), 'width'=>20,'column'=>'name','order'=>1),
										));
		$f=&$this->init_module('Libs/QuickForm');
		$f->addElement('text', 'name', 'Company Name');
		$gb->set_table_properties(array('table_name'=>'companies','view'=>1,'delete'=>1,'edit'=>1,'add'=>1,'id_row'=>'id','form'=>&$f));
		if ($_REQUEST['action']){
			$gb->set_module_variable('action',$_REQUEST['action']);
			$gb->set_module_variable('id',$_REQUEST['id']);
		}
		$this->display_module($gb);
	}

}

?>