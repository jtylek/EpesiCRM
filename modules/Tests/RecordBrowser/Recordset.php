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

class Tests_RecordBrowser_Recordset extends RBO_Recordset {
	function table_name() {
        return 'tests_record_set';
    }
	function fields() {
		//text
        $text_r = new RBO_Field_Text('Text Required');
        $text_r->set_length(64)->set_required()->set_visible();
        $text = new RBO_Field_Text('Text');
        $text->set_length(64)->set_visible();
		
		//long text
		$longtext_r = new RBO_Field_LongText('Long Text Required');
        $longtext_r->set_required()->set_visible();
		$longtext = new RBO_Field_LongText('Long Text');
        $longtext->set_visible();

		//integer
		$integer_r = new RBO_Field_Integer('Integer Required');
		$integer_r->set_required()->set_visible();
		$integer = new RBO_Field_Integer('Integer');
		$integer->set_visible();
		
		//float
		$float_r = new RBO_Field_Float('Float Required');
		$float_r->set_visible()->set_required();
		$float = new RBO_Field_Float('Float');
		$float->set_visible();
		
		//checkbox
		$checkbox = new RBO_Field_Checkbox('Checkbox');
		$checkbox->set_visible();
		
		//calculated
		$calculated = new RBO_Field_Calculated('Calculated');
		$calculated->set_visible();
		$callback = array('Tests_RecordBrowserCommon','display_calculated');
		$calculated->set_display_callback($callback);
		
		//date
		$date_r = new RBO_Field_Date('Date Required');
		$date_r->set_visible()->set_required();
		$date = new RBO_Field_Date('Date');
		$date->set_visible();
		
		//timestamp
		$timestamp_r = new RBO_Field_Timestamp('Timestamp Required');
		$timestamp_r->set_visible()->set_required();
		$timestamp = new RBO_Field_Timestamp('Timestamp');
		$timestamp->set_visible();

		//time - to add when it's available in RBO
		$time_r = new RBO_Field_Time('Time Required');
		$time_r->set_visible()->set_required();
		$time = new RBO_Field_Time('Time');
		$time->set_visible();
		
		//currency
		$currency_r = new RBO_Field_Currency('Currency Required');
		$currency_r->set_visible()->set_required();
		$currency = new RBO_Field_Currency('Currency');
		$currency->set_visible();
		
		//select recordset
		$select_r = new RBO_Field_Select('Select Required','task',array('Title'));
		$select_r->set_visible()->set_required();
		$select = new RBO_Field_Select('Select','task',array('Title'));
		$select->set_visible();
		
		//select commondata
		$select_commondata_r = new RBO_Field_CommonData('Select Commondata Required','Tests/RecordBrowser/Test_Commondata');
		$select_commondata_r->set_visible()->set_required();
		$select_commondata = new RBO_Field_CommonData('Select Commondata','Tests/RecordBrowser/Test_Commondata');
		$select_commondata->set_visible();

		//multiselect recordset
		$multiselect_r = new RBO_Field_Multiselect('Multiselect Required','task',array('Title'));
		$multiselect_r->set_visible()->set_required();
		$multiselect = new RBO_Field_Multiselect('Multiselect','task',array('Title'));
		$multiselect->set_visible();
		
		//multiselect commondata
		$multiselect_commondata_r = new RBO_Field_CommonData('Multiselect Commondata Required','Tests/RecordBrowser/Test_Commondata');
		$multiselect_commondata_r->set_visible()->set_required()->set_multiple();
		$multiselect_commondata = new RBO_Field_CommonData('Multiselect Commondata','Tests/RecordBrowser/Test_Commondata');
		$multiselect_commondata->set_visible()->set_multiple();

		//autonumber
		$autonumber = new RBO_Field_Autonumber('Autonumber');
		
		//page splits
		$pagesplit1 = new RBO_Field_PageSplit('Date&Time');
		$pagesplit2 = new RBO_Field_PageSplit('Selects');
		$pagesplit3 = new RBO_Field_PageSplit('Special');
		
		//permissions
		$permission = new RBO_Field_CommonData('Permission','Tests/RecordBrowser/Test_Permissions');
		$permission->set_visible();
		
		$special = new RBO_Field_Text('Special');
		$special->set_length(100)->set_visible();
		
        return array($autonumber,$text_r,$text,$longtext_r,$longtext,$integer_r,$integer,$float_r,$float,$checkbox,$calculated,$currency_r,$currency,$pagesplit1,$date_r,$date,$timestamp_r,$timestamp,$time_r,$time,$pagesplit2,$select_r,$select,$select_commondata_r,$select_commondata,$multiselect_r,$multiselect,$multiselect_commondata_r,$multiselect_commondata,$pagesplit3,$permission,$special);
    }
	
}
?>