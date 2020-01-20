<?php
/**
 * @author  Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2013, Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-tests
 * @subpackage record-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_RecordBrowserInstall extends ModuleInstall{
	public function install(){
        Utils_CommonDataCommon::new_array('Tests/RecordBrowser/Test_Commondata', array('Test0','Test1', 'Test2', 'Test3', 'Test4'));
		Utils_CommonDataCommon::new_array('Tests/RecordBrowser/Test_Permissions', array('none','partial view','full view','partial edit','full edit','delete'));
		
		$tr = new Tests_RecordBrowser_Recordset();
		$tr->install();
		
		//$ra = new RBO_RecordsetAccessor('tests_record_set');
		//$records = array();
		//$in = -1;
		//records for RB tests (full permission)
		$record = $tr->new_record($this->prepare_data(5,false,false));
		$this->update_record($record,$this->prepare_data(5,true,false));
		$record = $tr->new_record($this->prepare_data(5,false,true));
		$this->update_record($record,$this->prepare_data(5,true,true));
		//records for permissions tests
		for ($j=0;$j<5;$j++){
			$record = $tr->new_record($this->prepare_data($j,false,true));
			$this->update_record($record,$this->prepare_data($j,true,true));
		}
		//setting permissions
		$this->prepare_permissions();
		return true;
	}

	public function uninstall() {
		$test_recordset = new Tests_RecordBrowser_Recordset();
		$test_recordset->uninstall();
		return true;
	}
	
	public function requires($v) {
		return array(	array('name'=>Utils_RecordBrowserInstall::module_name(),'version'=>0));
	}
	
	function create_sample_tasks(){
		
	}
	
	function prepare_data($permission = 0,$is_altered = false,$are_nonrequired_filled = false){
		$ra_task = new RBO_RecordsetAccessor('task');
		$tasks_number = 2;
		$tasks = array_keys($ra_task->get_records(array(),array(),array(),$tasks_number));
		while (count($tasks)<$tasks_number){
			$t = $ra_task->new_record(array('title'=>'Sample task '.(count($tasks)+1),'status'=>0,'priority'=>0,'permission'=>0,'employees'=>array(1)));
			$tasks[] = $t->id;
		}
		$data_required = array(
				'text_required' => 'Sample text required', 
				'long_text_required' => 'Permission:'.$permission.' Not required fields full:'.$are_nonrequired_filled.' Altered:false',
				'integer_required' => 123,
				'float_required' => 123.45,
				'checkbox' => 0,
				'date_required' => date('Y-m-d'),
				'timestamp_required' => date('Y-m-d H:i:s'),
				'time_required' => date('Y-m-d 12:23:45'),
				'currency_required' => Utils_CurrencyFieldCommon::format_default(100,1),
				'select_required' => $tasks[0],
				'select_commondata_required' => 2,
				'multiselect_required' => array($tasks[0]),
				'multiselect_commondata_required' => array(0,1,2),
				'permission' => $permission
			);
		$data_other = array(
				'text' => 'Sample text', 
				'long_text' => 'A sample of long text',
				'integer' => 1234,
				'float' => 123.4567,
				'checkbox' => 1,
				'date' => date('Y-m-d',strtotime('+2 days')),
				'timestamp' => date('Y-m-d H:i:s',strtotime('-3 days')),
				'time' => date('Y-m-d 12:24:46'),
				'currency' => Utils_CurrencyFieldCommon::format_default(200,1),
				'select' => $tasks[1],
				'select_commondata' => 1,
				'multiselect' => array($tasks[0]),
				'multiselect_commondata' => array(0,1,2)
			);
		$data_required_altered = array(
				'text_required' => 'Sample text required altered', 
				'long_text_required' => 'Permission:'.$permission.' Not required fields full:'.$are_nonrequired_filled.' Altered:true',
				'integer_required' => 111111,
				'float_required' => 11111.1111,
				'checkbox' => 1,
				'date_required' => date('Y-m-d',strtotime('+2 days')),
				'timestamp_required' => date('Y-m-d H:i:s',strtotime('-3 days')),
				'time_required' => date('Y-m-d 12:26:47'),
				'currency_required' => Utils_CurrencyFieldCommon::format_default(10,1),
				'select_required' => $tasks[0],
				'select_commondata_required' => 3,
				'multiselect_required' => array($tasks[0],$tasks[1]),
				'multiselect_commondata_required' => array(1,2,3),
				'permission' => $permission
			);
		$data_other_altered = array(
				'text' => 'Sample text altered', 
				'long_text' => 'A sample of long altered text',
				'integer' => 1234,
				'float' => 123.4567,
				'checkbox' => 0,
				'date' => date('Y-m-d'),
				'timestamp' => date('Y-m-d H:i:s'),
				'time' => date('Y-m-d 12:27:47'),
				'currency' => Utils_CurrencyFieldCommon::format_default(20,1),
				'select' => $tasks[0],
				'select_commondata' => 3,
				'multiselect' => array($tasks[0],$tasks[1]),
				'multiselect_commondata' => array(1,2,3)
			);
		if ($is_altered){
			$data1 = $data_required_altered; 
			$data2 = $data_other_altered;
		} else {
			$data1 = $data_required;
			$data2 = $data_other;
		}
		if ($are_nonrequired_filled) return $data1+$data2; else return $data1;
	}
	
	function update_record($object,$data){
		foreach ($data as $f=>$v)
			$object->$f = $v;
		$object->save();
	}
	
	function prepare_permissions(){
		$fields = array('autonumber','text_required','text','long_text_required','long_text','integer_required','integer','float_required','float','checkbox','calculated','currency_required','currency','date_required','date','timestamp_required','timestamp','time_required','time','select_required','select','select_commondata_required','select_commondata','multiselect_required','multiselect','multiselect_commondata_required','multiselect_commondata');
		//view
		Utils_RecordBrowserCommon::add_access('tests_record_set','view','ADMIN',array('permission'=>1),$fields);		
		Utils_RecordBrowserCommon::add_access('tests_record_set','view','ADMIN',array('>permission'=>1));
		//edit
		Utils_RecordBrowserCommon::add_access('tests_record_set','edit','ADMIN',array('permission'=>3),$fields);		
		Utils_RecordBrowserCommon::add_access('tests_record_set','edit','ADMIN',array('>permission'=>3));
		//delete
		Utils_RecordBrowserCommon::add_access('tests_record_set','delete','ADMIN',array('permission'=>5));		
		Utils_RecordBrowserCommon::add_access('tests_record_set','add','ADMIN');		
	}

} 
?>
