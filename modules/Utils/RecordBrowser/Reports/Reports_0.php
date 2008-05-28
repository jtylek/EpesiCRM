<?php
/**
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Arkadiusz Bisaga <abisaga@telaxus.com>
 * @license SPL
 * @version 0.1
 * @package utils-recordbrowser-reports
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Reports extends Module {
	private $ref_record = null;
	private $ref_record_crits = array();
	private $ref_record_display_callback = null;
	private $gb_captions = null;
	private $calc_field_callback = null;
	private $data_record = array();
	private $data_record_relation = array();
	private $display_cell_callback = array();
	private $categories = array();
	private $format = null;

	public function set_reference_record($rr) {
		$this->ref_record = $rr;
	}

	public function set_data_records($dr) {
		if (!is_array($dr)) $dr = array($dr);
		foreach ($dr as $v) Utils_RecordBrowserCommon::check_table_name($v);
		$this->data_record = $dr;
	}

	public function set_reference_record_crits($rrc) {
		$this->ref_record_crits = $rrc;
	}

	public function set_data_record_relation($dr, $drr) {
		static $hash = array();
		if (!isset($hash[$dr])) {
			$hash[$dr] = array();
			Utils_RecordBrowserCommon::check_table_name($dr);
			$ret = DB::Execute('SELECT field FROM '.$dr.'_field');
			while ($row = $ret->FetchRow())
				$hash[$dr][strtolower(str_replace(' ','_',$row['field']))] = $row['field'];
		}
		$drr_clean = array();
		foreach($drr as $k=>$v) {
			$drr_clean[$hash[$dr][$k]] = $v;
		}
		$this->data_record_relation[$dr] = $drr_clean;
	}

	public function set_reference_record_display_callback($rrdc) {
		$this->ref_record_display_callback = $rrdc;
	}
	
	public function set_table_header($arg) {
		$cap = array();
		foreach($arg as $v) $cap[] = array('name'=>$v, 'wrapmode'=>'nowrap'); 
		$this->gb_captions = $cap;
		if (!empty($this->categories))
			$this->add_categories_to_header();
	}
	
	public function add_categories_to_header() {
		$x = array_shift($this->gb_captions);
		array_unshift($this->gb_captions, array('name'=>'&nbsp;', 'wrapmode'=>'nowrap'));
		array_unshift($this->gb_captions, $x);
	}

	public function set_display_cell_callback($arg) {
		$this->display_cell_callback = $arg;
	}

	public function set_categories($c) {
		$this->categories = $c;
		if (!empty($this->gb_captions))
			$this->add_categories_to_header();
	}

	public function set_format($arg) {
		$this->format = $arg;	
	}

	public function format_cell($format, $str) {
		$ret = $str;
		if ($format=='currency') $ret = '$&nbsp;'.number_format($str,2,'.',',');
		return array('value'=>$ret, 'style'=>'text-align: right;'.($str=='0'?'color: #AAAAAA;':''));
	}

	public function body() {
		$gb = $this->init_module('Utils_GenericBrowser',null,$this->ref_record.'_report');
		$gb->set_table_columns($this->gb_captions);
		array_shift($this->gb_captions);
		if (!empty($this->categories)) array_shift($this->gb_captions);
		$records = Utils_RecordBrowserCommon::get_records($this->ref_record, $this->ref_record_crits);
		foreach($records as $k=>$r) {
			foreach ($this->data_record as $dv) {
				$vals = array();
				$data_recs = array();
				foreach ($this->data_record_relation[$dv] as $k2=>$v2) $vals = array($k2, $r['id']);
				$ret = DB::Execute('SELECT '.$dv.'_id FROM '.$dv.'_data WHERE field=%s AND value=%s', $vals);
				while ($row = $ret->FetchRow())
					$data_recs[] = $row[$dv.'_id'];
				$data_recs = Utils_RecordBrowserCommon::get_records($dv, array('id'=>$data_recs));
			}
			$cell = 0;
			$results = array();
			foreach ($this->gb_captions as $v) {
				$res = call_user_func($this->display_cell_callback, $cell, $r, $data_recs);
				if (empty($this->categories)) {
					$res = $this->format_cell($this->format, $res);
				}
				$results[] = $res;
				$cell++;
			}
			$first = true;
			if (!empty($this->categories)) {
				foreach ($this->categories as $c) {
					$gb_row = $gb->get_new_row();
					if ($first) $grow = array(array('value'=>call_user_func($this->ref_record_display_callback, $r)));
					else $grow = array('');
					$grow[] = $c;
					$first = false;
					foreach ($results as $v) {
						$grow[] = $this->format_cell($this->format[$c], $v[$c]);
					}
					$gb_row->add_data_array($grow);
				}
			} else {
				$gb_row = $gb->get_new_row();
				array_unshift($results, array('value'=>call_user_func($this->ref_record_display_callback, $r)));
				$gb_row->add_data_array($results);
			}
		}
		$this->display_module($gb);
	}

}

?>