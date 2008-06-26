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
	private $lang;
	private $date_range;

	public function construct(){
		$this->lang = $this->init_module('Base/Lang');	
	}

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

	private function create_tooltip($ref_rec, $col, $value, $c='') {
		return Utils_TooltipCommon::open_tag_attrs($ref_rec.'<hr>'.$col.'<br>'.($c!=''?$c.':':'').$value, false).' ';
	}	
	
	public function check_dates($arg) {
		$idx = 0;
		switch ($arg[0]) {
			case 'year': $idx+=2;	
			case 'month': $idx+=2;	
			case 'week': $idx+=2;	
		}
		$st = $this->get_date($arg[0], $arg[1+$idx]);
		$nd = $this->get_date($arg[0], $arg[2+$idx]);
		return $st<=$nd;
	}
	
	public function display_date_picker() {
		$form = $this->init_module('Libs/QuickForm');
		$theme = $this->init_module('Base/Theme');
		$display_stuff_js = 'document.getElementById(\'day_elements\').style.display=\'none\';document.getElementById(\'month_elements\').style.display=\'none\';document.getElementById(\'week_elements\').style.display=\'none\';document.getElementById(\'year_elements\').style.display=\'none\';document.getElementById(this.value+\'_elements\').style.display=\'block\';';
		$form->addElement('select', 'date_range_type', $this->lang->t('Display report'), array('day'=>$this->lang->t('Days'), 'week'=>$this->lang->t('Weeks'), 'month'=>$this->lang->t('Months'), 'year'=>$this->lang->t('Years')), array('onChange'=>$display_stuff_js, 'onKeyUp'=>$display_stuff_js));
		$form->addElement('datepicker', 'from_day', $this->lang->t('From date'));
		$form->addElement('datepicker', 'to_day', $this->lang->t('To date'));
		$form->addElement('date', 'from_week', $this->lang->t('From week'), array('format'=>'Y W','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'to_week', $this->lang->t('To week'), array('format'=>'Y W','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'from_month', $this->lang->t('From month'), array('format'=>'Y m','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'to_month', $this->lang->t('To month'), array('format'=>'Y m','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'from_year', $this->lang->t('From year'), array('format'=>'Y','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'to_year', $this->lang->t('To year'), array('format'=>'Y','language'=>Base_LangCommon::get_lang_code()));
		if ($this->isset_module_variable('vals')) {
			$vals = $this->get_module_variable('vals');
			unset($vals['submited']);
			$form->setDefaults($vals);
		} else {
			foreach(array('week'=>5,'day'=>15,'month'=>12,'year'=>5) as $v=>$k) {
				$form->setDefaults(array('from_'.$v=>date('Y-m-d H:i:s', strtotime('-'.$k.' '.$v))));
				$form->setDefaults(array('to_'.$v=>date('Y-m-d H:i:s')));
			}
			$form->setDefaults(array('date_range_type'=>'day'));
		}
		$form->addElement('submit', 'submit', $this->lang->t('Show'));

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('date_range_type','from_day','to_day','from_week','to_week','from_month','to_month','from_year','to_year'), $this->lang->t('\'From\' date must be earlier than \'To\' date'), 'check_dates');
		
		$failed = false;		
		$vals = $form->exportValues();
		$this->set_module_variable('vals',$vals);
		if ($vals['submited'] && !$form->validate()) {
			$this->date_range = 'error';
			$failed = true;
		}

		$form->assign_theme('form',$theme);
		$theme->display('date_picker');
		$type = $vals['date_range_type'];
		foreach(array('week','day','year','month') as $v) 
			if ($v!=$type) eval_js('document.getElementById(\''.$v.'_elements\').style.display=\'none\';');

		if ($failed) {
			return array('type'=>'day', 'dates'=>array());
		}

		$this->date_range = array();
		foreach (array('date_range_type','from_'.$type,'to_'.$type) as $v)
			$this->date_range[$v] = $vals[$v];
		$header = array();
		$start 	= $this->get_date($type, $this->date_range['from_'.$type]);
		$end	= $this->get_date($type, $this->date_range['to_'.$type]);		
		$header[] = $start;
		while (true) {
			switch ($type) {
				case 'day': $start = strtotime(date('Y-m-d 12:00:00', $start+86400)); break;
				case 'week': $start = strtotime(date('Y-m-d 12:00:00', $start+604800)); break;
				case 'month': $start = strtotime(date('Y-m-15 12:00:00', $start+2592000)); break;
				case 'year': $start = strtotime(date('Y-06-15 12:00:00', $start+2592000*12)); break;
			}
			if ($start>$end) break;
			$header[] = $start;			
		}
		return array('type'=>$type, 'dates'=>$header);
	}
	
	public function get_date($type, $arg) {
		switch ($type) {
			case 'day': $arg = strtotime($arg.' 12:00:00'); break;
			case 'week': $narg = strtotime($arg['Y'].'-01-01 12:00:00')+604800*($arg['W']-1);
						 while (date('W', $narg)!=$arg['W'])
						 	$narg += 604800;
						 $arg = $narg;
						 break;
 			case 'month': $arg = strtotime($arg['Y'].'-'.$arg['m'].'-15 12:00:00'); break;
			case 'year': $arg = strtotime($arg['Y'].'-06-15 12:00:00'); break;
		}
		return $arg;
	}
	
	public function body() {
		if ($this->date_range=='error') return;
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
				$ret = DB::Execute('SELECT '.$dv.'_id FROM '.$dv.'_data AS rd LEFT JOIN '.$dv.' AS r ON r.id=rd.'.$dv.'_id WHERE rd.field=%s AND rd.value=%s AND r.active=1', $vals);
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
//		$table = $this->get_html_of_module($gb);
		$table = $this->get_html_of_module($gb, array(Base_ThemeCommon::get_template_filename('Utils_GenericBrowser','no_shadow')));
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