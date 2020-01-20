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

class Tests_RecordBrowser extends Module{
	public function body(){
		$ra_task = new RBO_RecordsetAccessor('task');
		$tasks = array_keys($ra_task->get_records(array(),array(),array(),2));

		$defaults = array(
				'text_required' => 'Default text', 
				'long_text_required' => 'Default long test',
				'integer_required' => 129,
				'float_required' => 129.129,
				'checkbox' => 0,
				'date_required' => date('Y-m-d'),
				'timestamp_required' => date('Y-m-d H:i:s'),
				'time_required' => date('Y-m-d 12:29:45'),
				'currency_required' => Utils_CurrencyFieldCommon::format_default(129.129,1),
				'select_required' => $tasks[0],
				'select_commondata_required' => 2,
				'multiselect_required' => array($tasks[0],$tasks[1]),
				'multiselect_commondata_required' => array(0,1,2),
				'permission' => 5,
				'text' => 'Default text', 
				'long_text' => 'Default long test',
				'integer' => 129,
				'float' => 129.129,
				'date' => date('Y-m-d',strtotime('+2 days')),
				'timestamp' => date('Y-m-d H:i:s',strtotime('-3 days')),
				'time' => date('Y-m-d 12:29:46'),
				'currency' => Utils_CurrencyFieldCommon::format_default(257.257,1),
				'select' => $tasks[1],
				'select_commondata' => 1,
				'multiselect' => array($tasks[0],$tasks[1]),
				'multiselect_commondata' => array(0,1,2)
			);
			
		$rs = new Tests_RecordBrowser_Recordset();
        $this->rb = $rs->create_rb_module($this);
		$this->rb->set_defaults($defaults);
		$this->display_module($this->rb);
	}
}

?>
