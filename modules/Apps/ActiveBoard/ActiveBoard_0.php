<?php
/** 
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @version 0.1
 * @package apps-activeboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_ActiveBoard extends Module {
	private $back_from_list = false;
	private $lang;
	
	public function body() {
		$this->lang = $this->init_module('Base/Lang');
		Base_ActionBarCommon::add('add','Add applet',$this->create_callback_href(array($this,'applets_list')));
		load_js_inline($this->get_module_dir().'ab.js');
		print('<div id="activeboard">');
		for($j=0; $j<3; $j++) {
			print('<div id="activeboard_applets_'.$j.'" style="float:left;width:33%;min-height:100px">');
			
			$ret = DB::Execute('SELECT id,module_name FROM apps_activeboard_applets WHERE col=%d AND base_user_login_id=%d ORDER BY pos',array($j,Base_UserCommon::get_my_user_id()));
			while($row = $ret->FetchRow()) {
				$m = $this->init_module($row['module_name'],null,$row['id']);
				$th = $this->init_module('Base/Theme');
				$th->assign('handle_class','handle');
				$th->assign('caption',call_user_func(array($row['module_name'].'Common', 'applet_caption')));
				$th->assign('toggle_open','<a class="toggle">');
				$th->assign('toggle_close','</a>');
				$th->assign('remove_open','<a class="remove" '.$this->create_confirm_callback_href($this->lang->t('Are you sure?'),array($this,'delete_applet'),$row['id']).'>');
				$th->assign('remove_close','</a>');
				$th->assign('content','<div class="content">'.
						$this->get_html_of_module($m,null,'applet').
						'</div>');
				print('<div class="applet" id="ab_item_'.$row['id'].'">');
				$th->display();
				print('</div>');
			}
			print('</div>');
		}
		print('</div>');
		eval_js('wait_while_null("Effect","activeboard_activate()")');
	}
	
	public function applets_list() {
		if($this->back_from_list) return false;

		$tipmod = $this->init_module('Utils/Tooltip');
		foreach(ModuleManager::$modules as $name=>$obj) {
			if(method_exists($obj['name'].'Common', 'applet_caption')) {
				$attrs = '';
				if(method_exists($obj['name'].'Common', 'applet_info')) {
					$out = '';
					$ret = call_user_func(array($obj['name'].'Common', 'applet_info'));
					if(is_array($ret)) {
						$out .= '<table>';
						foreach($ret as $k=>$v)
							$out .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
						$out .= '</table>';
					} elseif(is_string($ret))
						$out = $ret;
					else
						trigger_error('Invalid applet info for module: '.$obj['name'],E_USER_ERROR);
					$attrs .= $tipmod->open_tag_attrs($out).' ';
				}
				print('<a '.$attrs.$this->create_callback_href(array($this,'add_applet'),$obj['name']).'>'.call_user_func(array($obj['name'].'Common', 'applet_caption')).'</a><br>');
			}
		}
		return true;
	}
	
	public function add_applet($mod) {
		$this->back_from_list=true;
		DB::Execute('INSERT INTO apps_activeboard_applets(base_user_login_id,module_name) VALUES (%d,%s)',array(Base_UserCommon::get_my_user_id(),$mod));
	}

	public function delete_applet($id) {
		DB::Execute('DELETE FROM apps_activeboard_applets WHERE id=%d AND base_user_login_id=%d',array($id,Base_UserCommon::get_my_user_id()));
	}

}

?>