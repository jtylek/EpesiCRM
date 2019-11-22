<?php
/**
 * RecordBrowserCommon class.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

//!!! $table,$crits and sort variables are passed globally
defined("_VALID_ACCESS") || die();

//init
$ret = DB::GetRow('SELECT caption, recent, favorites FROM recordbrowser_table_properties WHERE tab=%s',array($table));
if(isset($_GET['search']) && $_GET['search']!=="Search" && $_GET['search']!=="")
	$type = 'all';
else
	$type = isset($_GET['type'])?$_GET['type']:Base_User_SettingsCommon::get('Utils_RecordBrowser',$table.'_default_view');
$order_num = (isset($_GET['order']) && isset($_GET['order_dir']))?$_GET['order']:-1;
$order = false;

//cols
$cols = Utils_RecordBrowserCommon::init($table);
$cols_out = array();
foreach($cols as $k=>$col) {
	if (!$col['visible'] && (!isset($info[$col['id']]) || !$info[$col['id']])) continue;
	if (isset($info[$col['id']]) && !$info[$col['id']]) continue;
	if(count($cols_out)==$order_num) $order=$col['id'];
	$col['name'] = _V($col['name']);
	if($type!='recent')
		$cols_out[] = array('name'=>$col['name'], 'order'=>$col['id'], 'record'=>$col, 'key'=>$k);
	else
		$cols_out[] = array('name'=>$col['name'], 'record'=>$col, 'key'=>$k);
}

//views
/*if($ret['recent'] && $type!='recent') print('<a '.(IPHONE?'class="button red" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'recent','rb_offset'=>0))).'">'.__('Recent').'</a>'.(IPHONE?'':'<br>'));
if($ret['favorites'] && $type!='favorites') print('<a '.(IPHONE?'class="button green" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'favorites','rb_offset'=>0))).'">'.__('Favorites').'</a>'.(IPHONE?'':'<br>'));
if(($ret['recent'] || $ret['favorites']) && $type!='all') print('<a '.(IPHONE?'class="button white" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('type'=>'all','rb_offset'=>0))).'">'.__('All').'</a>'.(IPHONE?'':'<br>'));*/
print('<form method="GET" action="mobile.php?'.http_build_query($_GET).'">');

if (!IPHONE) print('<table width="100%"><tr><td>');
if(Utils_RecordBrowserCommon::get_access($table, 'add')) {
	if (IPHONE)
		print('<a '.'class="button green" '.mobile_stack_href(array('Utils_RecordBrowserCommon','mobile_rb_edit'), array($table,false),__('Add record')).'>'.__('Add').'</a>');
	else
		print('<a '.mobile_stack_href(array('Utils_RecordBrowserCommon','mobile_rb_edit'), array($table,false),__('Add record')).'><img src="'.Base_ThemeCommon::get_template_file('Utils_RecordBrowser','mobile_add.png').'" border="0"></a>');
}
if (!IPHONE) print('</td><td align="right">');

if(IPHONE)
	print('<ul class="form">');
print('<input type="hidden" name="rb_offset" value="0">');
print((IPHONE?'<li>':'').'<select onchange="form.elements[\'search\'].value=\'Search\';form.submit()" name="type"><option value="all"'.($type=='all'?' selected=1':'').'>'.__('All').'</option><option value="recent"'.($type=='recent'?' selected=1':'').'>'.__('Recent').'</option><option value="favorites"'.($type=='favorites'?' selected=1':'').'>'.__('Favorites').'</option></select>'.(IPHONE?'</li>':''));
print((IPHONE?'<li>':'').'<input type="text" name="search" value="'.(isset($_GET['search'])?$_GET['search']:'Search').'" onclick="clickclear(this, \'Search\')" onblur="clickrecall(this,\'Search\')" />'.(IPHONE?'</li>':''));
if(IPHONE)
	print('</ul>');
else
	print('<input type="submit" value="OK"/>');

if (!IPHONE) print('</td></tr></table>');
print('</form>');
if(isset($_GET['search']) && $_GET['search']!=="Search" && $_GET['search']!=="") {
	$search_crits = array();
	$search_string = $_GET['search'];
	$search_string = DB::Concat(DB::qstr('%'),DB::qstr($search_string),DB::qstr('%'));
	$chr = '(';
	foreach ($cols_out as $col) {
		if(array_key_exists($col['record']['id'],$info)) continue;
		$args = $col['record'];
		$c = $args['id'];
		if ($args['type']=='text' || $args['type']=='currency' || ($args['type']=='calculated' && $args['param']!='')) {
			$search_crits[$chr.'"~'.$c] = $search_string;
			$chr='|';
			continue;
		}
		if ($args['type']!='commondata' && $args['type']!='multiselect' && $args['type']!='select') continue;
		continue;
		// Everything past this seems heavily broken - namely parsing commondata field type parameters
		if(isset($args['param'])) {
			if (is_array($args['param'])) {
				$str = array_values($args['param']);
			} else {
				$str = explode(';', $args['param']);
			}
			$ref = explode('::', $str[0]);
			if ($ref[0]!='' && isset($ref[1])) $search_crits[$chr.'"~'.$c.'['.$args['ref_field'].']'] = $search_string;
			if ($args['type']=='commondata' || $ref[0]=='__COMMON__')
				if (!isset($ref[1]) || $ref[0]=='__COMMON__') $search_crits[$chr.$c.'['.$args['ref_field'].']'] = $search_string;
		}
	}
	$crits = self::merge_crits($crits, $search_crits);
}

//$crits = array();
//$sort = array();
switch($type) {
	case 'favorites':
		$crits[':Fav'] = true;
		break;
	case 'recent':
		$crits[':Recent'] = true;
		$sort = array(':Visited_on' => 'DESC');
		break;
}
if(!IPHONE && $type!='recent' && $order && ($_GET['order_dir']=='asc' || $_GET['order_dir']=='desc')) {
	$sort = array($order => strtoupper($_GET['order_dir']));
}
$offset = isset($_GET['rb_offset'])?$_GET['rb_offset']:0;
if(IPHONE)
	$num_rows = 20;
else
	$num_rows = 10;
$data = Utils_RecordBrowserCommon::get_records($table,$crits,array(),$sort,array('numrows'=>$num_rows,'offset'=>$num_rows*$offset));

//parse data
if(IPHONE) {
	$letter = null;
	$letter_col = current($cols_out);
	$letter_col = $letter_col['record']['id'];
	print('<ul>');
} else
	$data_out = array();
foreach($data as $v) {
	if(IPHONE) {
		$row_sort = '';
		$row_info = '';
	} else
		$row = array();
	foreach($cols_out as $col) {
		$i = array_key_exists($col['record']['id'],$info);
		$val = Utils_RecordBrowserCommon::get_val($table,$col['key'],$v,IPHONE,$col['record']);
		if(IPHONE) {
			if($val==='') continue;
			if($type!='recent' && $col['record']['id'] == $letter_col && $letter!==$val{0}) {
				$letter=$val{0};
				print('</ul><h4>'.$letter.'</h4><ul>');
			}
			if($i)
				$row_info .= ($info[$col['record']['id']]?$col['name'].': ':'').$val.'<br>';
			else
				$row_sort .= $val.' ';
		} else
			$row[] = $val;
	}
	if(IPHONE) {
		$open = self::record_link_open_tag($table, $v['id'], false);
		$close = self::record_link_close_tag();
		$row = $open.$row_sort.$close.$open.$row_info.$close;
		print('<li class="arrow">'.$row.'</li>');
	} else
		$data_out[] = $row;
}

//display table
if(IPHONE) {
	print('</ul>');
} else {
	Utils_GenericBrowserCommon::mobile_table($cols_out,$data_out,false);
}

//display paging
$cur_num_rows = Utils_RecordBrowserCommon::get_records_count($table,$crits);
if($offset>0) print('<a '.(IPHONE?'class="button red" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('rb_offset'=>($offset-1)))).'">'.__('Prev').'</a>');
if($offset<$cur_num_rows/$num_rows-1) print(' <a '.(IPHONE?'class="button green" ':'').'href="mobile.php?'.http_build_query(array_merge($_GET,array('rb_offset'=>($offset+1)))).'">'.__('Next').'</a>');
if($cur_num_rows>$num_rows) {
	$qf = new HTML_QuickForm('rb_page', 'get','mobile.php?'.http_build_query($_GET));
	$qf->addElement('text', 'rb_offset', __('Page(0-%d)',array($cur_num_rows/$num_rows)));
	$qf->addElement('submit', 'submit_button', __('OK'),IPHONE?'class="button white"':'');
	$qf->addRule('rb_offset', __('Field required'), 'required');
	$qf->addRule('rb_offset', __('Invalid page number'), 'numeric');
	$renderer =& $qf->defaultRenderer();
/*	if(IPHONE) {
		$renderer->setFormTemplate("<form{attributes}>{hidden}<ul>{content}</ul></form>");
		$renderer->setElementTemplate('<li class="error"><!-- BEGIN required --><span style="color: #ff0000">*</span><!-- END required -->{label}<!-- BEGIN error --><span style=\"color: #ff0000\">{error}</span><!-- END error -->{element}</li>');
		$renderer->setRequiredNoteTemplate("<li>{requiredNote}</li>");
	}		*/
	$qf->accept($renderer);
	print($renderer->toHtml());
}
?>