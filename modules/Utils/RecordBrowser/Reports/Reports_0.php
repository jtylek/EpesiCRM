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
	private $row_summary = false;
	private $col_summary = false;
	private $first = false;

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
	
	public function set_summary($colrow, $action) {
		if ($colrow=='col') $this->col_summary = $action;
		if ($colrow=='row') $this->row_summary = $action;
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
		if (!is_array($format)) $format = array($format=>'');
		else $format = array_flip($format);
		$ret = $str;
		$css_class = '';
		$style = '';
		$attrs = '';
		if (isset($format['currency'])) $ret = '$&nbsp;'.number_format($str,2,'.',',');
//		if ($this->first) $style .= 'border-top:1px solid #555555;';
//		if (isset($format['total'])) $style .= 'background-color:#DFFFDF;';
//		if (isset($format['currency']) || isset($format['numeric'])) {
//			if (strip_tags($str)==0) $style .= 'color: #AAAAAA;';
//			$style .= 'text-align:right;';
//		}
		if ($this->first) $css_class .= ' top-row';
		if (isset($format['total-row_desc'])) $css_class .= ' total-row_desc';
		if (isset($format['row_desc'])) $css_class .= ' row-desc';
		if (isset($format['total_all'])) $css_class .= ' total-all';
		elseif (isset($format['total'])) $css_class .= ' total';
		if (isset($format['currency']) || isset($format['numeric'])) {
			if (strip_tags($str)==0) $css_class .= ' fade-out-zero';
			$css_class .= ' number';
		}
		$attrs .= ' class="'.$css_class.'"';
		return array('value'=>$ret, 'style'=>$style, 'attrs'=>$attrs);
	}

	public function create_tooltip($ref_rec, $col, $value, $c='') {
		return Utils_TooltipCommon::open_tag_attrs($ref_rec.'<hr>'.$col.'<br>'.($c!=''?$c.':':'').$value, false).' ';
	}	
	
	public function body() {
		Base_ThemeCommon::load_css('Utils/RecordBrowser/Reports');
		$gb = $this->init_module('Utils_GenericBrowser',null,$this->ref_record.'_report');
		if ($this->row_summary!==false) {
			$this->gb_captions[] = array('name'=>$this->row_summary['label']);
		}
		$gb->set_table_columns($this->gb_captions);
		array_shift($this->gb_captions);
		if (!empty($this->categories)) array_shift($this->gb_captions);
		$records = Utils_RecordBrowserCommon::get_records($this->ref_record, $this->ref_record_crits);
		if (empty($records)) {
			print('There were no records to display report for.');
			return;
		}
		$cols_total = array();
		/***** MAIN TABLE *****/
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
			$results = call_user_func($this->display_cell_callback, $r, $data_recs);
			if (empty($this->categories)) {
				$total = 0;
				$i = 0;
				$ref_rec = call_user_func($this->ref_record_display_callback, $r);
				foreach ($results as & $res_ref) {
					$val = strip_tags($res_ref);
					if ($this->row_summary!==false) $total += $val;
					if ($this->col_summary!==false) {
						if (!isset($cols_total[$i])) $cols_total[$i] = 0;  
						$cols_total[$i] += $val;
					}
					$res_ref = $this->format_cell($this->format, $res_ref);
					$res_ref['attrs'] .= $this->create_tooltip($ref_rec, $this->gb_captions[$i]['name'], $res_ref['value']);
					$i++;
				}
				$gb_row = $gb->get_new_row();
				array_unshift($results, $this->format_cell(array('row_desc'), $ref_rec));
				if ($this->row_summary!==false) {
					$next = $this->format_cell($this->format, $total);
					$next['attrs'] .= $this->create_tooltip($ref_rec, $this->row_summary['label'], $next['value']);
					$results[] = $next;
				}
				$gb_row->add_data_array($results);
			} else {
				$this->first = true;
				$count = count($this->categories);
				foreach ($this->categories as $c) {
					$gb_row = $gb->get_new_row();
					if ($this->first) {
						$ref_rec = call_user_func($this->ref_record_display_callback, $r);
						$grow = array(0=>$this->format_cell(array('row_desc'), $ref_rec));
						$grow[0]['attrs'] .= ' rowspan="'.$count.'" ';
					} else $grow = array(0=>array('dummy'=>1, 'value'=>''));
					$grow[] = $this->format_cell(array(), $c);
					$total = 0;
					$i = 0;  
					if (!isset($cols_total[$c])) $cols_total[$c] = array();
					$format = array($this->format[$c]);
					foreach ($results as $v) {
						$val = strip_tags($v[$c]);
						if ($this->row_summary!==false) $total += $val;
						if ($this->col_summary!==false) {
							if (!isset($cols_total[$c][$i])) $cols_total[$c][$i] = 0;  
							$cols_total[$c][$i] += $val;
						}
						$next = $this->format_cell($format, $v[$c]);
						$next['attrs'] .= $this->create_tooltip($ref_rec, $this->gb_captions[$i]['name'], $next['value'], $c);
						$grow[] = $next;
						$i++;
					}
					$format[] = 'total';
					if ($this->row_summary!==false) {
						$next = $this->format_cell($format, $total);
						$next['attrs'] .= $this->create_tooltip($ref_rec, $this->row_summary['label'], $next['value'], $c);
						$grow[] = $next;
					}
					$this->first = false;
					$gb_row->add_data_array($grow);
				}
			}
		}
		/***** BOTTOM SUMMARY *****/
		if ($this->col_summary!==false) {
			if (empty($this->categories)) {
				$total = 0;
				foreach ($cols_total as & $res_ref) {
					if ($this->row_summary!==false) $total += $res_ref;
					$res_ref = $this->format_cell($this->format, $res_ref);
					$res_ref['attrs'] .= $this->create_tooltip($this->col_summary['label'], $this->gb_captions[$i]['name'], $res_ref['value']);
					$i++;
				}
				$gb_row = $gb->get_new_row();
				array_unshift($cols_total, $this->format_cell(array('total-row_desc'), $this->col_summary['label']));
				if ($this->row_summary!==false) {
					$next = $this->format_cell($this->format, $total);
					$next['attrs'] .= $this->create_tooltip($this->col_summary['label'], $this->row_summary['label'], $next['value']);
					$cols_total[] = $next;
				}
				$gb_row->add_data_array($cols_total);
			} else {
				$this->first = true;
				$count = count($this->categories);
				foreach ($this->categories as $c) {
					$gb_row = $gb->get_new_row();
					if ($this->first) {
						$grow = array(0=>$this->format_cell(array('total-row_desc'), $this->col_summary['label']));
						$grow[0]['attrs'] .= 'rowspan="'.$count.'" ';
					} else $grow = array(0=>array('dummy'=>1, 'value'=>''));
					$grow[] = $this->format_cell(array('total'), $c);
					$total = 0;
					if (!isset($cols_total[$c])) $cols_total[$c] = array();
					$format = array($this->format[$c], 'total');
					$i=0;
					foreach ($cols_total[$c] as $v) {
						if ($this->row_summary!==false) $total += $v;
						$next = $this->format_cell($format, $v);
						$next['attrs'] .= $this->create_tooltip($this->col_summary['label'], $this->gb_captions[$i]['name'], $next['value'], $c);
						$grow[] = $next;
						$i++;
					}
					$format = array($this->format[$c], 'total_all');
					if ($this->row_summary!==false) {
						$next = $this->format_cell($format, $total);
						$next['attrs'] .= $this->create_tooltip($this->col_summary['label'], $this->row_summary['label'], $next['value'], $c);
						$grow[] = $next;
					}
					$this->first = false;
					$gb_row->add_data_array($grow);
				}
			}
		}
		$gb->set_inline_display();
		$table = $this->get_html_of_module($gb);
//		$table = $this->get_html_of_module($gb, array(Base_ThemeCommon::get_template_filename('Utils_RecordBrowser_Reports','generic_browser')));
		print($table);
/*		$pdf = $this->init_module('Libs/FPDF', 'P');
		$pdf->fpdf->AddPage();
		$pdf->fpdf->UseTableHeader(true);
//		$table = '<table border="1">';
//		$table .= '<tr><th>Header 1</th><th>Header 2</th></tr>';
//		for ($i=0;$i<100;$i++) $table .= '<tr><td>Row '.$i.' Col 1<br>!<br>?</td><td>Col 2</td></tr>';
//		$table .= '</table>';
		$pdf->fpdf->WriteHTML($table);
		print('<a href="'.$pdf->get_href().'">Here!</a>');*/
	}

}

?>