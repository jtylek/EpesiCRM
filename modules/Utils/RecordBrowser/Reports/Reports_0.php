<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser-Reports
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Reports extends Module {
	private static $colours = array('#00FFFF','#008000','#000080', '#808000', '#008080', '#0000FF','#00FF00','#800080','#FF00FF','#800000','#FF0000','#C0C0C0','#808080','#000000','#FFFF00');
	private $ref_records = array();
	private $ref_record_display_callback = null;
	private $paging = false;
	private $gb = null;
	private $gb_captions = null;
	private $calc_field_callback = null;
	private $display_cell_callback = array();
	private $categories = array();
	private $format = null;
	private $row_summary = false;
	private $col_summary = false;
	private $first = false;
	private $date_range;
	private $pdf = false;
	private $charts = false;
	private $pdf_ob = null;
	private $widths = array();
	private $fontsize = 12;
	private $height = 14;
	private $pdf_title = '';
	private $pdf_subject = '';
	private $pdf_filename = '';
	private $cols_total = array();
	private static $pdf_ready = 0;
	private $bonus_width = 15;
	
	public function construct() {
		$this->gb = $this->init_module('Utils/GenericBrowser',null,'report_page');
	}
	
	public function enable_paging($amount) {
		if (isset($_REQUEST['rb_reports_enable_pdf'])) return null;
		$this->paging = true;
		return $this->gb->get_limit($amount);
	}

	public function set_bonus_width($arg){
		$this->bonus_width = $arg;
	}

	public function set_reference_records($rr) {
		$this->ref_records = $rr;
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

	public function format_cell($format, $val, $type='none') {
		if (!is_array($format)) $format = array($format=>'');
		else $format = array_flip($format);
		$ret = array();
		if (!is_array($val)) $val = array($val);
		if (isset($format['currency']) || isset($format['numeric']) || isset($format['percent']))
			$format['fade_out_zero'] = 1;
		if (isset($format['currency']) && empty($val)) $val = array(0=>0);
		$css_class = '';
		$style = '';
		$attrs = '';
		foreach ($val as $k=>$v) {
			$next = $v;
			if (((float)$v)==0 && strlen($v)>0 && $v!=((string)((float)$v))) {
				unset($format['fade_out_zero']);
			} else {
				if (isset($format['currency'])) {
					$v = strip_tags($v);
					$next = Utils_CurrencyFieldCommon::format($v, $k);
				}
				if (isset($format['currency']) || isset($format['numeric']))
					if (((float)$v)!=0)
						unset($format['fade_out_zero']);
			}
			if (isset($format['percent'])) {
				if ($type=='row_total' || $type=='total_all') {
					$cols = count($this->gb_captions)-2;
					if (!empty($this->categories)) $cols--;
					$v = number_format($v/$cols,2);
				}
				if ($type=='col_total' || $type=='total_all') {
					$rows = count($this->ref_records);
					$v = number_format($v/$rows,2);
				}
				$next = $v.' %';
				if ($v!=0) unset($format['fade_out_zero']);
			}
			$ret[] = $next;
		}
		if (isset($format['currency'])) {
			foreach ($ret as $k=>$v) {
				if (count($ret)==1) break;
				if ($v==0) unset($ret[$k]);
			}			
		}
		if (isset($format['currency']) || isset($format['numeric']) || isset($format['percent'])) {
			$css_class .= ' number';
			if (isset($format['fade_out_zero']))
				$css_class .= ' fade-out-zero';
		}
		if ($this->first) $css_class .= ' top-row';
		if (isset($format['total-row_desc'])) $css_class .= ' total-row_desc';
		if (isset($format['row_desc'])) $css_class .= ' row-desc';
		if (isset($format['total_all'])) $css_class .= ' total-all';
		elseif (isset($format['total'])) $css_class .= ' total';
		$attrs .= ' class="'.$css_class.'"';
		$ret = implode('<br>',$ret);
		if ($this->pdf) $ret = array('value'=>$ret, 'style'=>$format, 'attrs'=>'');
		else $ret = array('value'=>$ret, 'style'=>$style, 'attrs'=>$attrs);
		return $ret;
	}

	private function create_tooltip($ref_rec, $col, $value, $c='') {
		if ($this->pdf) return '';
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

	public function display_date_picker($datepicker_defaults = array(), $form=null) {
		if ($form===null) $form = $this->init_module('Libs/QuickForm');
		$theme = $this->init_module('Base/Theme');
		$display_stuff_js = 'document.getElementById(\'day_elements\').style.display=\'none\';document.getElementById(\'month_elements\').style.display=\'none\';document.getElementById(\'week_elements\').style.display=\'none\';document.getElementById(\'year_elements\').style.display=\'none\';document.getElementById(this.value+\'_elements\').style.display=\'block\';';
		$form->addElement('select', 'date_range_type', $this->t('Display report'), array('day'=>$this->t('Days'), 'week'=>$this->t('Weeks'), 'month'=>$this->t('Months'), 'year'=>$this->t('Years')), array('onChange'=>$display_stuff_js, 'onKeyUp'=>$display_stuff_js));
		$form->addElement('datepicker', 'from_day', $this->t('From date'));
		$form->addElement('datepicker', 'to_day', $this->t('To date'));
		$form->addElement('date', 'from_week', $this->t('From week'), array('format'=>'Y W','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'to_week', $this->t('To week'), array('format'=>'Y W','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'from_month', $this->t('From month'), array('format'=>'Y m','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'to_month', $this->t('To month'), array('format'=>'Y m','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'from_year', $this->t('From year'), array('format'=>'Y','language'=>Base_LangCommon::get_lang_code()));
		$form->addElement('date', 'to_year', $this->t('To year'), array('format'=>'Y','language'=>Base_LangCommon::get_lang_code()));
		if ($this->isset_module_variable('vals')) {
			$vals = $this->get_module_variable('vals');
			unset($vals['submited']);
			$form->setDefaults($vals);
		} else {
			foreach(array('week'=>3,'day'=>13,'month'=>5,'year'=>5) as $v=>$k) {
				$form->setDefaults(array('from_'.$v=>date('Y-m-d H:i:s', strtotime('-'.$k.' '.$v))));
				$form->setDefaults(array('to_'.$v=>date('Y-m-d H:i:s')));
			}
			$form->setDefaults(array('date_range_type'=>'month'));
			$form->setDefaults($datepicker_defaults);
		}
		$form->addElement('submit', 'submit', $this->t('Show'));

		$form->registerRule('check_dates', 'callback', 'check_dates', $this);
		$form->addRule(array('date_range_type','from_day','to_day','from_week','to_week','from_month','to_month','from_year','to_year'), $this->t('\'From\' date must be earlier than \'To\' date'), 'check_dates');

		$failed = false;
		$other = $vals = $form->exportValues();
		$this->set_module_variable('vals',$vals);
		if ($vals['submited'] && !$form->validate()) {
			//$this->date_range = 'error';
			//$failed = true;
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
		$start_p 	= $start 	= $this->get_date($type, $this->date_range['from_'.$type]);
		$end_p 		= $end		= $this->get_date($type, $this->date_range['to_'.$type]);
		$header[] = $start;
		while (true) {
			switch ($type) {
				case 'day': 
					$start = strtotime(date('Y-m-d 12:00:00', $start+86400));
					$start_format = 'Y-m-d';
					$end_format = 'Y-m-d'; 
					break;
				case 'week': 
					$start = strtotime(date('Y-m-d 12:00:00', $start+604800)); 
					$start_format = 'Y-m-d';
					$end_format = 'Y-m-d';
					$fdow = Utils_PopupCalendarCommon::get_first_day_of_week();
					$start_p -= (4-$fdow)*24*60*60;
					$end_p += (2+$fdow)*24*60*60;
					break;
				case 'month': 
					$start = strtotime(date('Y-m-15 12:00:00', $start+2592000));
					$start_format = 'Y-m-01';
					$end_format = 'Y-m-t'; 
					break;
				case 'year': 
					$start = strtotime(date('Y-06-15 12:00:00', $start+2592000*12));
					$start_format = 'Y-01-01';
					$end_format = 'Y-12-31';
					break;
			}
			if ($start>$end) break;
			$header[] = $start;
		}
		return array('type'=>$type, 'dates'=>$header, 'start'=>date($start_format,$start_p), 'end'=>date($end_format,$end), 'other'=>$other);
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

	public function new_table_page() {
		if (!$this->pdf) {
			$this->gb->set_table_columns($this->gb_captions);
			$this->gb->set_inline_display();
		}
	}

	public function display_pdf_header() {
		$theme = $this->init_module('Base/Theme');
		$grow = array();
		foreach ($this->gb_captions as $k=>$v)
			$grow[] = array('value'=>$v['name'], 'style'=>array('header'=>1));
		$theme->assign('row',$grow);
		$theme->assign('params',array('widths'=>$this->widths,'height'=>$this->height));
		ob_start();
		$theme->display('pdf_row');
		$table = ob_get_clean();
		$this->pdf_ob->writeHTML($table);
	}

	public function display_pdf_row($grow) {
		$table = '';
		foreach ($grow as $row) {
			$theme = $this->init_module('Base/Theme');
			$theme->assign('row',$row);
			$theme->assign('params',array('widths'=>$this->widths,'height'=>$this->height));
			ob_start();
			$theme->display('pdf_row');
			$table .= ob_get_clean();
		}
		$table = Libs_TCPDFCommon::stripHTML($table);
		$pages = $this->pdf_ob->getNumPages();
		$tmppdf = clone($this->pdf_ob->tcpdf);
		$tmppdf->WriteHTML($table,false,0,false);
		if ($pages==$tmppdf->getNumPages()) {
			$this->pdf_ob->writeHTML($table,false);
			return;
		}
		$this->pdf_ob->AddPage();
		$this->display_pdf_header();
		$this->pdf_ob->writeHTML($table,false);
	}
	
	public function get_cols_total() {
		return $this->cols_total;
	}
	
	public function modify_cols_total($i, $val, $cat=null) {
		if (empty($this->categories)) {
//			if (!isset($this->cols_total[$i])) $this->cols_total[$i] = 0;
			$this->cols_total[$i][0] += $val;
		} else {
//			if (!isset($this->cols_total[$cat])) $this->cols_total[$cat] = array();
//			if (!isset($this->cols_total[$cat][$i])) $this->cols_total[$cat][$i] = 0;
			$this->cols_total[$cat][$i][0] += $val;
		}
	}

	public function make_table() {
		if ($this->row_summary!==false)
			$this->gb_captions[] = array('name'=>$this->row_summary['label']);
		$this->new_table_page();

		if ($this->pdf) {
			$cols = count($this->gb_captions);
			// 760 - total width for landscape page
			$this->widths = array(floor((710-$this->bonus_width)/$cols+$this->bonus_width));
			for ($i=1;$i<$cols;$i++)
				$this->widths[] = floor((710-$this->bonus_width)/$cols);
			$sum = 0;
			foreach($this->widths as $v) $sum+=$v;
			$this->widths[0]+= 720-$sum;
			$this->fontsize = 12;
			switch (true) {
				case ($cols>16): $this->fontsize -=1;
				case ($cols>13): $this->fontsize -=1;
				case ($cols>11): $this->fontsize -=1;
				case ($cols>9): $this->fontsize -=1;
				case ($cols>7): $this->fontsize -=1;
				case ($cols>5): $this->fontsize -=2;
			}
			$this->height = $this->fontsize+2;
			$this->display_pdf_header();
		}


		if (empty($this->ref_records)) {
			print('There were no records to display report for.');
			return;
		}
		$this->cols_total = array();
		/***** MAIN TABLE *****/
		$row_count = 1;
		$gb_captions = $this->gb_captions;
		array_shift($gb_captions);
		if (!empty($this->categories)) array_shift($gb_captions);
		foreach($this->ref_records as $k=>$r) {
			$results = call_user_func($this->display_cell_callback, $r);
			if (!is_array($results)) $results = array($results);
			if (empty($this->categories)) {
				$total = array();
				$i = 0;
				$ref_rec = call_user_func($this->ref_record_display_callback, $r);
				foreach ($results as & $res_ref) {
					if (!is_array($res_ref)) $res_ref = array($res_ref);
					if ($this->row_summary!==false) {
						foreach ($res_ref as $k=>$w) {
							if (!isset($total[$k])) $total[$k] = 0;
							$total[$k] += strip_tags($w);
						}
					}
					if ($this->col_summary!==false) {
						if (!isset($this->cols_total[$i])) $this->cols_total[$i] = array();
						foreach ($res_ref as $k=>$w) {
							if (!isset($this->cols_total[$i][$k])) $this->cols_total[$i][$k] = 0;
							$this->cols_total[$i][$k] += strip_tags($w);
						}
					}
					$res_ref = $this->format_cell($this->format, $res_ref);
					$res_ref['attrs'] .= $this->create_tooltip($ref_rec, $gb_captions[$i]['name'], $res_ref['value']);
					$i++;
				}
				array_unshift($results, $this->format_cell(array('row_desc'), $ref_rec));
				if ($this->row_summary!==false) {
					if (isset($this->row_summary['callback'])) $total = call_user_func($this->row_summary['callback'], $results, $total);
					$next = $this->format_cell(array($this->format,'total'), $total, 'row_total');
					$next['attrs'] .= $this->create_tooltip($ref_rec, $this->row_summary['label'], $next['value']);
					$results[] = $next;
				}
				$ggrow = array($results);
			} else {
				$ggrow = array();
				$this->first = true;
				$count = count($this->categories);
				foreach ($this->categories as $c) {
					if ($this->first) {
						$ref_rec = call_user_func($this->ref_record_display_callback, $r);
						$grow = array(0=>$this->format_cell(array('row_desc'), $ref_rec));
						$grow[0]['attrs'] .= ' rowspan="'.$count.'" ';
					} else $grow = array(0=>array('dummy'=>1, 'value'=>''));
					$grow[] = $this->format_cell(array(), $c);
					$total = array();
					$i = 0;
					if (!isset($this->cols_total[$c])) $this->cols_total[$c] = array();
					$format = array($this->format[$c]);
					foreach ($results as $v) {
						if (!is_array($v[$c])) $v[$c] = array($v[$c]);
						if ($this->row_summary!==false) {
							foreach ($v[$c] as $k=>$w) {
								if (!isset($total[$k])) $total[$k] = 0;
								$total[$k] += strip_tags($w);
							}
						}
						if ($this->col_summary!==false) {
							if (!isset($this->cols_total[$c][$i])) $this->cols_total[$c][$i] = array();
							foreach ($v[$c] as $k=>$w) {
								if (!isset($this->cols_total[$c][$i][$k])) $this->cols_total[$c][$i][$k] = 0;
								$this->cols_total[$c][$i][$k] += strip_tags($w);
							}
						}
						$next = $this->format_cell($format, $v[$c]);
						$next['attrs'] .= $this->create_tooltip($ref_rec, $gb_captions[$i]['name'], $next['value'], $c);
						$grow[] = $next;
						$i++;
					}
					$format[] = 'total';
					if ($this->row_summary!==false) {
						if (isset($this->row_summary['callback'])) $total = call_user_func($this->row_summary['callback'], $results, $total, $c);
						$next = $this->format_cell($format, $total, 'row_total');
						$next['attrs'] .= $this->create_tooltip($ref_rec, $this->row_summary['label'], $next['value'], $c);
						$grow[] = $next;
					}
					$this->first = false;
					$ggrow[] = $grow;
				}
			}
			if ($this->pdf) {
				$this->display_pdf_row($ggrow);
			} else {
				foreach ($ggrow as $grow) {
					$gb_row = $this->gb->get_new_row();
					$gb_row->add_data_array($grow);
				}
			}
		}
		/***** BOTTOM SUMMARY *****/
		if ($this->col_summary!==false) {
			if (!$this->pdf) $this->col_summary['label'] = $this->col_summary['label'].' ('.$this->t('page').')';
			if (empty($this->categories)) {
				$total = array();
				$i=0;
				foreach ($this->cols_total as & $res_ref) {
					if (!is_array($res_ref)) $res_ref = array($res_ref);
					if ($this->row_summary!==false) {
						foreach ($res_ref as $k=>$w) {
							if (!isset($total[$k])) $total[$k] = 0;
							$total[$k] += strip_tags($w);
						}
					}
					$res_ref = $this->format_cell(array($this->format,'total'), $res_ref, 'col_total');
					$res_ref['attrs'] .= $this->create_tooltip($this->col_summary['label'], $gb_captions[$i]['name'], $res_ref['value']);
					$i++;
				}
				array_unshift($this->cols_total, $this->format_cell(array('total-row_desc'), $this->col_summary['label']));
				if ($this->row_summary!==false) {
					$next = $this->format_cell(array($this->format,'total_all'), $total, 'total_all');
					$next['attrs'] .= $this->create_tooltip($this->col_summary['label'], $this->row_summary['label'], $next['value']);
					$this->cols_total[] = $next;
				}
				$ggrow = array($this->cols_total);
			} else {
				$this->first = true;
				$count = count($this->categories);
				$ggrow = array();
				foreach ($this->categories as $c) {
					if ($this->first) {
						$grow = array(0=>$this->format_cell(array('total-row_desc'), $this->col_summary['label']));
						$grow[0]['attrs'] .= 'rowspan="'.$count.'" ';
					} else $grow = array(0=>array('dummy'=>1, 'value'=>'', 'attrs'=>'class=" total"'));
					$grow[] = $this->format_cell(array('total'), $c);
					$total = array();
					if (!isset($this->cols_total[$c])) $this->cols_total[$c] = array();
					$format = array($this->format[$c], 'total');
					$i=0;
					foreach ($this->cols_total[$c] as $v) {
						if ($this->row_summary!==false) {
							foreach ($v as $k=>$w) {
								if (!isset($total[$k])) $total[$k] = 0;
								$total[$k] += $w;
							}
						}
						$next = $this->format_cell($format, $v, 'col_total');
						$next['attrs'] .= $this->create_tooltip($this->col_summary['label'], $gb_captions[$i]['name'], $next['value'], $c);
						$grow[] = $next;
						$i++;
					}
					$format = array($this->format[$c], 'total_all');
					if ($this->row_summary!==false) {
						$next = $this->format_cell($format, $total, 'total_all');
						$next['attrs'] .= $this->create_tooltip($this->col_summary['label'], $this->row_summary['label'], $next['value'], $c);
						$grow[] = $next;
					}
					$this->first = false;
					$ggrow[] = $grow;
				}
			}
		}
		if ($this->pdf) {
			$this->display_pdf_row($ggrow,true);
		} elseif($this->charts) {

		} else {
			foreach ($ggrow as $grow) {
				$gb_row = $this->gb->get_new_row();
				$gb_row->add_data_array($grow);
			}
			$this->display_module($this->gb, array(Base_ThemeCommon::get_template_filename('Utils_GenericBrowser','no_shadow')));
		}
	}

	public function draw_chart($r,$ref_rec,$gb_captions) {
			$f = $this->init_module('Libs/OpenFlashChart');
			$f2 = $this->init_module('Libs/OpenFlashChart');
			$results = call_user_func($this->display_cell_callback, $r);

			$title = new title( $ref_rec );
			$f->set_title( $title );
			$f2->set_title( $title );
			$labels = array();
			foreach($gb_captions as $cap)
				$labels[] = $cap['name'];
			$x_ax = new x_axis();
			$x_ax->set_labels_from_array($labels);
			$f->set_x_axis($x_ax);
			$f2->set_x_axis($x_ax);
			$max = 5;
			$max2 = 5;
			$curr = false;
			$num = false;

			if (empty($this->categories)) {
				$arr = array();
				$bar = new line_hollow();
				$bar->set_colour(self::$colours[0]);
				foreach ($results as & $res_ref) {
					if (is_array($res_ref))
						$res_ref = array_pop($res_ref);
					$val = (int)strip_tags($res_ref);
					$arr[] = $val;
					if($this->format=='currency') {
						if($max2<$val) $max2=$val;
					} else {
						if($max<$val) $max=$val;
					}
				}
				$bar->set_values( $arr );
				if($this->format=='currency') {
					$f2->add_element( $bar );
					$curr = true;
				} else {
					$f->add_element( $bar );
					$num = true;
				}
			} else {
				foreach ($this->categories as $q=>$c) {
					$bar = new line_hollow();
					$bar->set_colour(self::$colours[$q%count(self::$colours)]);
					$bar->set_key(strip_tags($c),3);
					$arr = array();
					foreach ($results as $v) {
						$val = (int)strip_tags($v[$c]);
						$arr[] = $val;
						if($this->format[$c]=='currency') {
							if($max2<$val) $max2=$val;
						} else {
							if($max<$val) $max=$val;
						}
					}
					$bar->set_values( $arr );
					if($this->format[$c]=='currency') {
						$f2->add_element( $bar );
						$curr = true;
					} else {
						$f->add_element( $bar );
						$num = true;
					}
				}
			}

			if($num) {
				$y_ax = new y_axis();
				$y_ax->set_range(0,$max);
				$y_ax->set_steps($max/10);
				$f->set_y_axis($y_ax);

				$f->set_width(950);
				$f->set_height(400);

				$this->display_module($f);
				print('<br>');
			}

			if($curr) {
				$y_ax = new y_axis();
				$y_ax->set_range(0,$max2);
				$y_ax->set_steps($max2/10);
				$f2->set_y_axis($y_ax);

				$f2->set_width(950);
				$f2->set_height(400);

				$this->display_module($f2);
				print('<br>');
			}

	}

	public function draw_summary_chart($gb_captions) {
			$f = $this->init_module('Libs/OpenFlashChart'); //row summary numeric
			$f2 = $this->init_module('Libs/OpenFlashChart'); //row summary currency
			$fc = $this->init_module('Libs/OpenFlashChart'); //columns summary numeric
			$fc2 = $this->init_module('Libs/OpenFlashChart'); //columns summary currency

			$title = new title( "Summary by row" );
			$f->set_title( $title );
			$f2->set_title( $title );
			if(!empty($this->categories)) {
				$labels = array();
				$labels_c = array();
				foreach ($this->categories as $q=>$c) {
					if($this->format[$c]=='currency') {
						$labels_c[] = strip_tags($c);
					} else {
						$labels[] = strip_tags($c);
					}
				}
				$x_ax = new x_axis();
				$x_ax->set_labels_from_array($labels);
				$f->set_x_axis($x_ax);
				$x_ax = new x_axis();
				$x_ax->set_labels_from_array($labels_c);
				$f2->set_x_axis($x_ax);
			}

			$title = new title( "Summary by column" );
			$fc->set_title( $title );
			$fc2->set_title( $title );
			$labels = array();
			foreach($gb_captions as $cap)
				$labels[] = $cap['name'];
			$x_ax = new x_axis();
			$x_ax->set_labels_from_array($labels);
			$fc->set_x_axis($x_ax);
			$fc2->set_x_axis($x_ax);
			$max = 5;
			$max2 = 5;
			$maxc = 5;
			$maxc2 = 5;
			$curr = false;
			$num = false;
			$col_total=array();

			foreach($this->ref_records as $k=>$r) {
				$results = call_user_func($this->display_cell_callback, $r);

				$ref_rec = call_user_func($this->ref_record_display_callback, $r);

				$bar = new bar_glass();
				$bar->set_colour(self::$colours[$k%count(self::$colours)]);
				$bar->set_key(strip_tags($ref_rec),3);

				if(empty($this->categories)) {
					$total = 0;
					$i = 0;
					foreach ($results as & $res_ref) {
						if (is_array($res_ref))
							$res_ref = array_pop($res_ref);
						$val = strip_tags($res_ref);
							$total += $val;
						if (!isset($this->cols_total[$i])) $this->cols_total[$i] = 0;
						$this->cols_total[$i] += $val;
						$i++;
					}
					$bar->set_values(array($total));
					if($this->format=='currency') {
						$max2 = $total;
						$f2->add_element( $bar );
						$curr = true;
					} else {
						$max = $total;
						$f->add_element( $bar );
						$num = true;
					}
				} else {
					$bar_c = new bar_glass();
					$bar_c->set_colour(self::$colours[$k%count(self::$colours)]);
					$bar_c->set_key(strip_tags($ref_rec),3);
					$arr = array();
					$arr_c = array();
					foreach ($this->categories as $q=>$c) {
						$total = 0;
						if(!isset($this->cols_total[$c])) $this->cols_total[$c] = array();
						$i=0;
						foreach ($results as $v) {
							$val = (int)strip_tags($v[$c]);
							$total += $val;
							if (!isset($this->cols_total[$c][$i])) $this->cols_total[$c][$i] = 0;
							$this->cols_total[$c][$i] += $val;
							$i++;
						}
						if($this->format[$c]=='currency') {
							$arr_c[] = $total;
							if($max2<$total) $max2 = $total;
						} else {
							$arr[] = $total;
							if($max<$total) $max = $total;
						}
					}
					if(!empty($arr)) {
						$bar->set_values( $arr );
						$f->add_element( $bar );
						$num = true;
					}
					if(!empty($arr_c)) {
						$bar_c->set_values( $arr_c );
						$f2->add_element( $bar_c );
						$curr = true;
					}
				}
			}


			if($num) {
				$y_ax = new y_axis();
				$y_ax->set_range(0,$max);
				$y_ax->set_steps($max/10);
				$f->set_y_axis($y_ax);

				$f->set_width(950);
				$f->set_height(400);

				$this->display_module($f);
				print('<br>');
			}

			if($curr) {
				$y_ax = new y_axis();
				$y_ax->set_range(0,$max2);
				$y_ax->set_steps($max2/10);
				$f2->set_y_axis($y_ax);

				$f2->set_width(950);
				$f2->set_height(400);

				$this->display_module($f2);
				print('<br>');
			}

			if(empty($this->categories)) {
				$bar = new bar_glass();
				$bar->set_colour(self::$colours[0]);
				$bar->set_key('Total',3);
				$mm = 5;
				foreach($this->cols_total as $val)
					if($mm<$val) $mm=$val;
				$bar->set_values($this->cols_total);
				if($this->format=='currency') {
					$maxc2 = $mm;
					$fc2->add_element( $bar );
				} else {
					$maxc = $mm;
					$fc->add_element( $bar );
				}
			} else {
				$i = 0;
				foreach($this->cols_total as $k=>$arr) {
					$bar = new bar_glass();
					$bar->set_colour(self::$colours[$i%count(self::$colours)]);
					$bar->set_key(strip_tags($k),3);
					$bar->set_values($arr);
					$mm = 5;
					foreach($arr as $val)
						if($mm<$val) $mm=$val;
					if($this->format[$k]=='currency') {
						if($mm>$maxc2) $maxc2 = $mm;
						$fc2->add_element( $bar );
					} else {
						if($mm>$maxc) $maxc = $mm;
						$fc->add_element( $bar );
					}
					$i++;
				}
			}


			if($num) {
				$y_ax = new y_axis();
				$y_ax->set_range(0,$maxc);
				$y_ax->set_steps($maxc/10);
				$fc->set_y_axis($y_ax);

				$fc->set_width(950);
				$fc->set_height(400);

				$this->display_module($fc);
				print('<br>');
			}

			if($curr) {
				$y_ax = new y_axis();
				$y_ax->set_range(0,$maxc2);
				$y_ax->set_steps($maxc2/10);
				$fc2->set_y_axis($y_ax);

				$fc2->set_width(950);
				$fc2->set_height(400);

				$this->display_module($fc2);
				print('<br>');
			}

	}

	public function draw_category_chart($ref_rec,$gb_captions) {
			$f = $this->init_module('Libs/OpenFlashChart');

			$title = new title( $ref_rec );
			$f->set_title( $title );
			$labels = array();
			foreach($gb_captions as $cap)
				$labels[] = $cap['name'];
			$x_ax = new x_axis();
			$x_ax->set_labels_from_array($labels);
			$f->set_x_axis($x_ax);
			$max = 5;

			foreach($this->ref_records as $q=>$r) {
				$results = call_user_func($this->display_cell_callback, $r);

				$title2 = strip_tags(call_user_func($this->ref_record_display_callback, $r));
				$bar = new line_hollow();
				$bar->set_colour(self::$colours[$q%count(self::$colours)]);
				$bar->set_key($title2,3);
				$arr = array();
				foreach ($results as $v) {
					if($ref_rec) {
						if (is_array($v[$ref_rec]))
							$v[$ref_rec] = array_pop($v[$ref_rec]);
						$val = (int)strip_tags($v[$ref_rec]);
					} else {
						if (is_array($v))
							$v = array_pop($v);
						$val = (int)strip_tags($v);
					}
					$arr[] = $val;
					if($max<$val) $max=$val;
				}
				$bar->set_values( $arr );
				$f->add_element( $bar );
			}

			$y_ax = new y_axis();
			$y_ax->set_range(0,$max);
			$y_ax->set_steps($max/10);
			$f->set_y_axis($y_ax);

			$f->set_width(950);
			$f->set_height(400);

			$this->display_module($f);
	}

	public function make_charts() {
		if (empty($this->ref_records)) {
			print('There were no records to display report for.');
			return;
		}


		$this->cols_total = array();
		/***** MAIN TABLE *****/
		$row_count = 1;
		$gb_captions = $this->gb_captions;
		array_shift($gb_captions);
		if (!empty($this->categories)) array_shift($gb_captions);

		$tb = & $this->init_module('Utils/TabbedBrowser');
		foreach($this->ref_records as $k=>$r) {
			$title = strip_tags(call_user_func($this->ref_record_display_callback, $r));
			$tb->set_tab($title, array($this,'draw_chart'),array($r,$title,$gb_captions));
		}
		if (empty($this->categories)) {
			$title = 'All';
			$tb->set_tab($title, array($this,'draw_category_chart'),array('',$gb_captions));
		} else {
			foreach ($this->categories as $q=>$c) {
				$title = strip_tags($c);
				$tb->set_tab($title, array($this,'draw_category_chart'),array($title,$gb_captions));
			}
		}
		$tb->set_tab('Summary', array($this,'draw_summary_chart'),array($gb_captions));
		$this->display_module($tb);
		$this->tag();
	}

	public function from_to_date() {
		$start = $this->date_range['from_'.$this->date_range['date_range_type']];
		$end = $this->date_range['to_'.$this->date_range['date_range_type']];
		switch ($this->date_range['date_range_type']) {
			case 'week':	$start = $this->t('%d, week %d',array($start['Y'], $start['W']));
							$end = $this->t('%d, week %d',array($end['Y'], $end['W']));
							break;
			case 'month':	$start = $this->t('%s %d',array(date('F',$this->get_date('month', $start)),$start['Y']));
							$end = $this->t('%s %d',array(date('F',$this->get_date('month', $end)),$end['Y']));
							break;
			case 'year':	$start = $this->t('%d',array($start['Y']));
							$end = $this->t('%d',array($end['Y']));
							break;
		}
		return array($start, $end);
	}

	public function pdf_subject_date_range() {
		return $this->t(ucfirst($this->date_range['date_range_type']).' report -  %s  -  %s', $this->from_to_date());
	}

	public function set_pdf_title($arg) {
		$this->pdf_title = $arg;
	}

	public function set_pdf_subject($arg) {
		$this->pdf_subject = $arg;
	}

	public function set_pdf_filename($arg) {
		$this->pdf_filename = $arg;
	}

	public function body($pdf=false, $charts=false) {
		if ($this->is_back()) return false;
		if ($this->date_range=='error') return;
		Base_ThemeCommon::load_css('Utils/RecordBrowser/Reports');
		$this->pdf = $pdf || isset($_REQUEST['rb_reports_enable_pdf']);
		unset($_REQUEST['rb_reports_enable_pdf']);
		$this->charts = $charts;
		if ($this->pdf) {
			$this->pdf_ob = $this->init_module('Libs/TCPDF', 'L');
			$this->pdf_ob->set_title($this->pdf_title);
			$this->pdf_ob->set_subject($this->pdf_subject);
			$this->pdf_ob->prepare_header();
			$this->pdf_ob->AddPage();
		} elseif (!$this->charts) {
			Base_ActionBarCommon::add('report','Charts',$this->create_callback_href(array($this, 'body'), array(false,true)));
		}

		if($this->charts)
			$this->make_charts();
		else
			$this->make_table();

		if($charts) {
			Base_ActionBarCommon::add('report','Table',$this->create_back_href());
			return true;
		} else {
			if ($this->pdf){
				Base_ActionBarCommon::add('save','Download PDF','href="'.$this->pdf_ob->get_href($this->pdf_filename).'"');
				self::$pdf_ready = 1;
			} elseif ($this->pdf_title!='' && self::$pdf_ready == 0) {
				if (count($this->gb_captions)<20)
					Base_ActionBarCommon::add('print','Create PDF',$this->create_href(array('rb_reports_enable_pdf'=>1)));
				else
					Base_ActionBarCommon::add('print','Create PDF','','Too many columns to prepare printable version - please limit number of columns');
			}
		}
		return false;
	}

}

?>
