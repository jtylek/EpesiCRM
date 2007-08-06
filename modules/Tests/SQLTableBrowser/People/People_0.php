<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_SQLTableBrowser_People extends Module {
	private $lang;

	public function body($arg) {
		$this->lang = & $this->init_module('Base/Lang');

		if ($_REQUEST['module']) {
			$m = &$this->pack_module($_REQUEST['module']);
			$this -> display_module($m);
			return;
		}
		$gb = & $this->init_module('Utils/SQLTableBrowser',null,'browse_sql');
		$gb->set_table_format(array(	array('label'=>$this->lang->t('Id'), 'width'=>20,'column'=>'id','order'=>1,'search'=>1),
										array('label'=>$this->lang->t('First name'), 'width'=>20,'column'=>'fname','order'=>1),
										array('label'=>$this->lang->t('Last name'), 'width'=>20,'column'=>'lname','order'=>1),
										array('label'=>$this->lang->t('Company'), 'width'=>20,'column'=>'company','order'=>1,'reference'=>array('companies','id','name'),'action'=>array('module'=>'Tests/SQLTableBrowser/Companies','action'=>'view','id'=>'company_id')),
										array('label'=>$this->lang->t('Company id'), 'width'=>20,'column'=>'company AS company_id','display'=>0),
										));
		$f=&$this->init_module('Libs/QuickForm');
		$f->addElement('text', 'fname', 'First Name');
		$f->addElement('text', 'lname', 'Last Name');
		$companies = array();
		$ret = DB::Execute('SELECT id, name FROM companies');
		while ($row=$ret->FetchRow()) $companies[$row['id']]=$row['name'];
		$f->addElement('select', 'company', 'Company', $companies);
		$gb->set_table_properties(array('table_name'=>'people','view'=>1,'delete'=>1,'edit'=>1,'add'=>1,'id_row'=>'id','form'=>&$f));
		$this->display_module($gb);
	}
}

?>