<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage Report
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Report extends Module {
	private static $cats = array('Random number', 'Random value');
	private static $format = '';
	private static $dates = array();
	private static $range_type = '';
	private $rbr = 'null';
	private $lang;

	public function construct() {
		$this->rbr = $this->init_module('Utils/RecordBrowser/Reports');
	}

	public function body() {
		$recs = Utils_RecordBrowserCommon::get_records('company',array(),array(),array('company_name'=>'ASC'));
		$i = 0;
		foreach ($recs as $k=>$v) {
			if ($i>=10) unset($recs[$k]);
			$i++;
		}
		$this->rbr->set_reference_records($recs);
		$this->rbr->set_reference_record_display_callback(array('Tests_ReportCommon','display_company'));
		$date_range = $this->rbr->display_date_picker();
		$this->rbr->set_categories(self::$cats);
		$this->rbr->set_summary('col', array('label'=>'Total'));
		$this->rbr->set_summary('row', array('label'=>'Total'));
		$this->rbr->set_format(array(	self::$cats[0]=>'numeric', 
										self::$cats[1]=>'currency'));
		$header = array('Company');
		$this->dates = $date_range['dates'];
		$this->range_type = $date_range['type'];
		switch ($date_range['type']) {
			case 'day': $this->format ='d M Y'; break;
			case 'week': $this->format ='W Y'; break;
			case 'month': $this->format ='M Y'; break;
			case 'year': $this->format ='Y'; break;
		} 
		foreach ($this->dates as $v)
			$header[] = date($this->format, $v);
		$this->rbr->set_table_header($header);
		$this->rbr->set_display_cell_callback(array($this, 'display_cells'));
		$this->rbr->set_pdf_title($this->t('Companies - Report, %s',array(date('Y-m-d H:i:s'))));
		$this->rbr->set_pdf_subject($this->rbr->pdf_subject_date_range());
		$this->rbr->set_pdf_filename($this->t('Companies_Report_%s',array(date('Y_m_d__H_i_s'))));
		$this->display_module($this->rbr);
	}

	public function display_cells($ref_rec){
		$result = array();
		$hash = array();
		$i = 0;
		foreach ($this->dates as $v) {
			srand($v+$ref_rec['id']);
			$f = rand()/100-50;
			$s = rand();
			if ($f<0) {
				$f = 0;
				$s = 0;
			}
			$result[$i] = array(	self::$cats[0]=>$f,
									self::$cats[1]=>$s);
			$i++;
		}
		return $result;
	}
	
	public function caption() {
		return 'Companies Report';
	}
}

?>