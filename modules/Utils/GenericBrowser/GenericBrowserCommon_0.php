<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>, Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowserCommon extends ModuleCommon {
	public static $possible_vals_for_per_page=array(5=>5,10=>10,15=>15,20=>20,25=>25,30=>30,40=>40,50=>50);

	public static function user_settings(){
		return array(__('Browsing tables')=>array(
			array('name'=>'per_page','label'=>__('Records per page'),'type'=>'select','values'=>Utils_GenericBrowserCommon::$possible_vals_for_per_page,'default'=>20),
			array('name'=>'actions_position','label'=>__('Position of \'Actions\' column'),'type'=>'radio','values'=>array(0=>__('Left'),1=>__('Right')),'default'=>0),
			array('name'=>'adv_search','label'=>__('Advanced search by default'),'type'=>'bool','default'=>0),
			array('name'=>'adv_history','label'=>__('Advanced order history'),'type'=>'bool','default'=>0),
			array('name'=>'display_no_records_message','label'=>__('Hide \'No records found\' message'),'type'=>'bool','default'=>0),
			array('name'=>'show_all_button','label'=>__('Display \'Show all\' button'),'type'=>'bool','default'=>1),
			array('name'=>'zoom_actions','label'=>__('Zoom "Actions" buttons'),'type'=>'select', 'values'=>array(0=>__('Never'), 1=>__('For mobile devices'), 2=>__('Always')),'default'=>1)
			));
	}
	
	public static function mobile_table($cols,$data,$enable_sort=true) {
		if($enable_sort && is_string($enable_sort) && !isset($_GET['order'])) {
				$x = explode(' ',$enable_sort);
				foreach($cols as $i=>$v)
					if($x[0]==$v['order']) $_GET['order'] = $i;
				if(isset($_GET['order'])) {
					if(count($x)<2) $_GET['order_dir'] = 'asc';
					else $_GET['order_dir'] = $x[1];
				}
		}
		$th = Base_ThemeCommon::init_smarty();

		$all_width = 0;
		foreach($cols as $v) {
			if (array_key_exists('display', $v) && $v['display']==false)
				continue;
			if (isset($v['width'])) $all_width+=$v['width'];
		}
		$headers = array();
		foreach($cols as $i=>$v) {
			if (array_key_exists('display', $v) && $v['display']==false) {
				continue;
			}
			if(isset($v['order'])) $is_order = true;
			$headers[$i] = array();
			if (isset($_GET['order']) && isset($_GET['order_dir']) && $i==$_GET['order']) {
				$sort_direction = ($_GET['order_dir']=='desc')?'asc':'desc';
				$sort = 'style="padding-right: 12px; background-image: url('.Base_ThemeCommon::get_template_file('Utils_GenericBrowser','sort-'.$sort_direction.'ending.png').'); background-repeat: no-repeat; background-position: right;"';
			} else {
				$sort = '';
				$sort_direction = 'asc';
			}
			$headers[$i]['label'] = (isset($v['preppend'])?$v['preppend']:'').(isset($v['order'])?'<a href="mobile.php?'.http_build_query(array_merge($_GET,array('order'=>$i,'order_dir'=>$sort_direction))).'">' . '<span '.$sort.'>' . $v['name'] . '</span></a>':'<span>'.$v['name'].'</span>').(isset($v['append'])?$v['append']:'');
			$headers[$i]['attrs'] = '';
			if ($all_width && isset($v['width'])) $headers[$i]['attrs'] .= 'style="width: '.intval(100*$v['width']/$all_width).'%" ';
			$headers[$i]['attrs'] .= 'nowrap="1" ';
		}
		$th->assign('cols',array_values($headers));

		//sort data
		if($enable_sort && isset($_GET['order']) && isset($_GET['order_dir'])) {
				$col = array();
				foreach($data as $j=>$d)
					foreach($d as $i=>$c)
						if(isset($cols[$i]['order']) && $i==$_GET['order']) {
							if(is_array($c)) {
								if(isset($c['order_value']))
									$xxx = $c['order_value'];
								else
									$xxx = $c['value'];
							} else $xxx = $c;
							if(isset($cols[$i]['order_preg'])) {
								$ret = array();
								preg_match($cols[$i]['order_preg'],$xxx, $ret);
								$xxx = isset($ret[1])?$ret[1]:'';
							}
							$xxx = strip_tags(strtolower($xxx));
							$col[$j] = $xxx;
						}
	
				asort($col);
				$data2 = array();
				foreach($col as $j=>$v) {
					$data2[] = $data[$j];
				}
				if($_GET['order_dir']!='asc') {
					$data2 = array_reverse($data2);
				}
				$data = $data2;
		}
		
		$out_data = array();
		foreach($data as $row) {
			foreach($row as $k=>$cell) {
				if (!isset($cols[$k]) || (array_key_exists('display', $cols[$k]) && $cols[$k]['display']==false)) 
					continue;
				if(!is_array($cell)) {
					$cell.='&nbsp;';
					$out_data[] = array('label'=>$cell,'attrs'=>'');
				} else {
					if(!isset($cell['attrs'])) $cell['attrs'] = '';
					if(!isset($cell['label'])) $cell['label'] = '';
					$cell['label'].='&nbsp;';
					$out_data[] = $cell;
				}
			}
		}
		unset($data);
		$th->assign('data',$out_data);

		Base_ThemeCommon::display_smarty($th,'Utils/GenericBrowser','mobile');
	}
	
	public static function hide_overflow_div(){
		eval_js('table_overflow_hide(utils_genericbrowser__hide_current);');
	}

	public static function init_overflow_div(){
		if(!isset($_SESSION['client']['utils_genericbrowser']['div_exists'])) {
			load_js('modules/Utils/GenericBrowser/js/table_overflow.js');
			eval_js('Utils_GenericBrowser__overflow_div();',false);
			$_SESSION['client']['utils_genericbrowser']['div_exists'] = true;
		}
		on_exit(array('Utils_GenericBrowserCommon', 'hide_overflow_div'),null,false);
	}
}

Utils_GenericBrowserCommon::init_overflow_div();

?>
