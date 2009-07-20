<?php
/**
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage dashboard
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Dashboard extends Module {
	private $tb;
	private $set_default_js='';

	public function construct() {
		$this->tb = $this->init_module('Utils/TabbedBrowser');
	}

	public function body() {
		$this->help('Dashboard Help','main');

		if(ModuleManager::is_installed('Utils/RecordBrowser')>=0) //speed up links to RB
			if(Utils_RecordBrowserCommon::check_for_jump()) return;

		$this->dashboard();
	}

	private function dashboard() {
		//Base_MenuCommon::add_quick_menu('Dashboard/Manage tabs',$this->create_callback_href(array($this,'tabs_list')));
		load_js($this->get_module_dir().'ab.js');
		$default_dash = $this->get_module_variable('default');

		if($default_dash)
			$tabs = DB::GetAll('SELECT name,id FROM base_dashboard_default_tabs ORDER BY pos');
		else {
			$tabs = DB::GetAll('SELECT name,id FROM base_dashboard_tabs WHERE user_login_id=%d ORDER BY pos',array(Acl::get_user()));
			if(!$tabs) {
				$this->set_default_applets();
				$tabs = DB::GetAll('SELECT name,id FROM base_dashboard_tabs WHERE user_login_id=%d ORDER BY pos',array(Acl::get_user()));
			}
		}

		if(count($tabs)>1) {
			foreach($tabs as $tab)
				$this->tb->set_tab($tab['name'], array($this,'display_dashboard'),$tab['id']);

			$remember_tab = Base_User_SettingsCommon::get('Base_Dashboard','remember_tab');
			if($remember_tab) {
				if(isset($_REQUEST['__homepage_req_session__'])  && isset($_SESSION['client']['dashboard_tab'])) {
					$this->tb->switch_tab($_SESSION['client']['dashboard_tab']);//force switch tab
				} elseif(isset($_SESSION['client']['dashboard_tab'])) {
					$this->tb->set_default_tab($_SESSION['client']['dashboard_tab']);
				} else {
					$this->tb->set_default_tab($this->tb->get_tab());
				}
				$_SESSION['client']['dashboard_tab'] = $this->tb->get_tab();
			}

			$this->display_module($this->tb);
			$this->tb->tag();
		} else
			$this->display_dashboard($tabs[0]['id']);
	}

	public function display_dashboard($tab_id) {
		Base_ActionBarCommon::add('add','Add applet',$this->create_callback_href(array($this,'applets_list'),$tab_id));

		$default_dash = $this->get_module_variable('default');
		$colors = Base_DashboardCommon::get_available_colors();
		$applets = array(0=>array(),1=>array(),2=>array());
		if($default_dash)
			$ret = DB::Execute('SELECT col,id,module_name,color FROM base_dashboard_default_applets WHERE tab=%d ORDER BY col,pos',array($tab_id));
		else
			$ret = DB::Execute('SELECT col,id,module_name,color FROM base_dashboard_applets WHERE user_login_id=%d AND tab=%d ORDER BY pos',array(Acl::get_user(),$tab_id));
		while($row = $ret->FetchRow())
			$applets[$row['col']][] = $row;

		print('<div id="dashboard" style="width: 100%">');
		for($j=0; $j<3; $j++) {
			print('<div id="dashboard_applets_'.$j.'" style="width:33%;min-height:200px;padding-bottom:10px;vertical-align:top;float:left">');

			foreach($applets[$j] as $row) {
				if(ModuleManager::is_installed($row['module_name'])==-1) {//if its invalid entry
					$this->delete_applets($row['module_name']);
					continue;
				}

				$m = $this->init_module($row['module_name'],null,$row['id']);

				$opts = array();
				$opts['title'] = $this->t(call_user_func(array($row['module_name'].'Common', 'applet_caption')));
				$opts['toggle'] = true;
				$opts['href'] = null;
				$opts['go'] = false;
				$opts['go_function'] = 'body';
				$opts['go_arguments'] = array();
				$opts['go_constructor_arguments'] = array();
				$opts['actions'] = array();
				$opts['id'] = $row['id'];

				$th = $this->init_module('Base/Theme');

				$th->assign('content','<div class="content">'.
						$this->get_html_of_module($m,array($this->get_values($row['id'],$row['module_name']), & $opts, $row['id']),'applet').
						'</div>');
				$th->assign('handle_class','handle');

				if($opts['toggle'])
					$th->assign('toggle','<a class="toggle" '.Utils_TooltipCommon::open_tag_attrs($this->ht('Toggle')).'>=</a>');

				if($opts['go'])
					$opts['href']=$this->create_main_href($row['module_name'],$opts['go_function'],$opts['go_arguments'],$opts['go_constructor_arguments']);
				if($opts['href'])
					$th->assign('href','<a class="href" '.Utils_TooltipCommon::open_tag_attrs($this->ht('Fullscreen')).' '.$opts['href'].'>G</a>');

				$th->assign('remove','<a class="remove" '.Utils_TooltipCommon::open_tag_attrs($this->ht('Remove')).' '.$this->create_confirm_callback_href($this->ht('Delete this applet?'),array($this,'delete_applet'),$row['id']).'>x</a>');

				$th->assign('configure','<a class="configure" '.Utils_TooltipCommon::open_tag_attrs($this->ht('Configure')).' '.$this->create_callback_href(array($this,'configure_applet'),array($row['id'],$row['module_name'])).'>c</a>');

				$th->assign('caption',$opts['title']);
				$th->assign('color',$colors[$row['color']]);
				
				$th->assign('actions',$opts['actions']);

				print('<div class="applet" id="ab_item_'.$row['id'].'">');
				$th->display();
				print('</div>');
			}
			print('</div>');
		}
		print('</div>');
		eval_js('dashboard_activate('.($default_dash?1:0).')');
	}

	public function tabs_list() {
//		if($this->is_back()) return false;
//		Base_ActionBarCommon::add('back','Dashboard',$this->create_back_href());
		Base_ActionBarCommon::add('add','Add tab',$this->create_callback_href(array($this,'edit_tab')));

		$default_dash = $this->get_module_variable('default');

		$gb = $this->init_module('Utils/GenericBrowser',null,'tabs');
		$gb->set_table_columns(array(
				array('name'=>$this->t('Caption'))
				));

		if($default_dash)
			$ret = DB::GetAll('SELECT id,name,pos FROM base_dashboard_default_tabs ORDER BY pos');
		else
			$ret = DB::GetAll('SELECT id,name,pos FROM base_dashboard_tabs WHERE user_login_id=%d ORDER BY pos',array(Acl::get_user()));
		foreach($ret as $row) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_data($row['name']);
			$gb_row->add_action($this->create_callback_href(array($this,'edit_tab'),$row['id']), 'Edit');
			$gb_row->add_action($this->create_confirm_callback_href($this->t('Delete this tab and all applets assigned to it?'),array($this,'delete_tab'),$row['id']), 'Delete');
			if($row['pos']>$ret[0]['pos'])
				$gb_row->add_action($this->create_callback_href(array($this,'move_tab'),array($row['id'],$row['pos'],-1)), 'move-up');
			if($row['pos']<$ret[count($ret)-1]['pos'])
				$gb_row->add_action($this->create_callback_href(array($this,'move_tab'),array($row['id'],$row['pos'],+1)), 'move-down');
		}

		$this->display_module($gb);
		return true;
	}

	public function delete_tab($id) {
		$default_dash = $this->get_module_variable('default');
		$table_tabs = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
		$table_applets = 'base_dashboard_'.($default_dash?'default_':'').'applets';
		$table_settings = 'base_dashboard_'.($default_dash?'default_':'').'settings';

		$ret = DB::GetAll('SELECT id FROM '.$table_applets.' WHERE tab=%d',array($id));
		foreach($ret as $row)
			DB::Execute('DELETE FROM '.$table_settings.' WHERE applet_id=%d',array($row['id']));
		DB::Execute('DELETE FROM '.$table_applets.' WHERE tab=%d',array($id));
		DB::Execute('DELETE FROM '.$table_tabs.' WHERE id=%d',array($id));
	}

	public function edit_tab($id=null) {
		if($this->is_back()) return false;

		$default_dash = $this->get_module_variable('default');
		$table = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
		$qf = $this->init_module('Libs/QuickForm');
		$qf->add_table($table, array(
				array('name'=>'name', 'label'=>$this->t('Caption'))
			));
		if(isset($id))
			$qf->setDefaults(array('name'=>DB::GetOne('SELECT name FROM '.$table.' WHERE id=%d',array($id))));
		if($qf->validate()) {
			$name = $qf->exportValue('name');
			if(isset($id))
				DB::Execute('UPDATE '.$table.' SET name=%s WHERE id=%d',array($name,$id));
			else {
				DB::StartTrans();
				if($default_dash) {
					$max = DB::GetOne('SELECT max(pos)+1 FROM '.$table);
					if ($max===false || $max===null) $max=0;
					DB::Execute('INSERT INTO '.$table.'(name,pos) VALUES(%s,%d)',array($name,$max));
				} else {
					$max = DB::GetOne('SELECT max(pos)+1 FROM '.$table.' WHERE user_login_id=%d',array(Acl::get_user()));
					if ($max===false || $max===null) $max=0;
					DB::Execute('INSERT INTO '.$table.'(name,pos,user_login_id) VALUES(%s,%d,%d)',array($name,$max,Acl::get_user()));
				}
				DB::CompleteTrans();
			}
			return false;
		}
		Base_ActionBarCommon::add('save','Save',$qf->get_submit_form_href());
		Base_ActionBarCommon::add('back','Cancel',$this->create_back_href());
		$qf->display();
		return true;
	}

	public function move_tab($id,$old_pos,$dir) {
		$default_dash = $this->get_module_variable('default');
		$table = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
		DB::StartTrans();
		$new_pos = DB::GetOne('SELECT '.($dir>0?'MIN':'MAX').'(pos) FROM '.$table.' WHERE pos'.($dir>0?'>':'<').'%d AND user_login_id=%s',array($old_pos, Acl::get_user()));
		$id2 = DB::GetOne('SELECT id FROM '.$table.' WHERE pos=%d AND user_login_id=%s',array($new_pos,Acl::get_user()));
		DB::Execute('UPDATE '.$table.' SET pos=%d WHERE id=%d',array($old_pos,$id2));
		DB::Execute('UPDATE '.$table.' SET pos=%d WHERE id=%d',array($new_pos,$id));
		DB::CompleteTrans();
	}

	public function applets_list($tab_id) {
		$fc = $this->get_module_variable('first_conf');
		if(isset($fc)) {
			$mod = $this->get_module_variable('mod_conf');
			$ok = null;
			if(!$this->configure_applet($fc,$mod,$ok)) {
				if(!$ok)
					$this->delete_applet($fc);
				else
					Base_StatusBarCommon::message(Base_LangCommon::ts($this->get_type(),'Applet added'));
				$this->unset_module_variable('first_conf');
				$this->unset_module_variable('mod_conf');
				return false;
			}
			return true;
		}

		if($this->is_back()) return false;
		Base_ActionBarCommon::add('back','Dashboard',$this->create_back_href());


		$buttons = array();
		$app_cap = ModuleManager::call_common_methods('applet_caption');
		asort($app_cap);
		$app_info = ModuleManager::call_common_methods('applet_info');
		foreach($app_cap as $name=>$cap) {
			$attrs = '';
			$info = '';
			if(isset($app_info[$name])) {
				$ret = $app_info[$name];
				if(is_array($ret)) {
					$info .= '<table>';
					foreach($ret as $k=>$v)
						$info .= '<tr><td>'.$k.'</td><td>'.$v.'</td></tr>';
					$info .= '</table>';
				} elseif(is_string($ret))
					$info = $ret;
				else
					trigger_error('Invalid applet info for module: '.$name,E_USER_ERROR);
				$attrs .= Utils_TooltipCommon::open_tag_attrs($info,false).' ';
			}
			if (method_exists($name.'Common','applet_icon'))
				$icon = call_user_func(array($name.'Common','applet_icon'));
			else {
				try {
					$icon = Base_ThemeCommon::get_template_file($name,'icon.png');
				} catch(Exception $e) {
					$icon = null;
				}
			}
			$desc = strip_tags((!is_string($app_info[$name]) && isset($app_info[$name]['description']))?$app_info[$name]['description']:$info);
			$buttons[] = array('link'=>'<a '.$attrs.$this->create_callback_href(array($this,'add_applet'),array($name,$tab_id)).'>'.$cap.'</a>',
						'icon'=>$icon,'desc'=>((strlen($desc)>100)?substr($desc,0,100).'...':$desc));
		}

		if(empty($buttons)) {
			print($this->t('No applets installed'));
			return true;
		}

		$theme =  & $this->pack_module('Base/Theme');
		$theme->assign('header', $this->t('Add applet'));
		$theme->assign('buttons', $buttons);
		$theme->display('list');

		return true;
	}

	public function add_applet($mod,$tab_id) {
		$pos = 0;
		DB::StartTrans();
		if($this->get_module_variable('default')) {
			$cols = DB::GetAssoc('SELECT col,count(id) FROM base_dashboard_default_applets WHERE tab=%d GROUP BY col ORDER BY col',array($tab_id));
			for($col=0; $col<3 && isset($cols[$col]); $col++);
			if($col==3) $col=0;
			if(isset($cols[$col]))
				$pos = $cols[$col];
			DB::Execute('INSERT INTO base_dashboard_default_applets(module_name,tab,col,pos) VALUES (%s,%d,%d,%d)',array($mod,$tab_id,$col,$pos));
		} else {
			$cols = DB::GetAssoc('SELECT col,count(id) FROM base_dashboard_applets WHERE user_login_id=%d AND tab=%d GROUP BY col ORDER BY col',array(Acl::get_user(),$tab_id));
			for($col=0; $col<3 && isset($cols[$col]); $col++);
			if($col==3) $col=0;
			if(isset($cols[$col]))
				$pos = $cols[$col];
			DB::Execute('INSERT INTO base_dashboard_applets(user_login_id,module_name,tab,col,pos) VALUES (%d,%s,%d,%d,%d)',array(Acl::get_user(),$mod,$tab_id,$col,$pos));
		}
		DB::CompleteTrans();
		$sett_fn = array($mod.'Common','applet_settings');
		$this->set_module_variable('first_conf',DB::Insert_ID('base_dashboard_'.($this->get_module_variable('default')?'default_':'').'applets','id'));
		$this->set_module_variable('mod_conf',$mod);
	}

	public function delete_applet($id) {
		if($this->get_module_variable('default')) {
			DB::Execute('DELETE FROM base_dashboard_default_settings WHERE applet_id=%d',array($id));
			DB::Execute('DELETE FROM base_dashboard_default_applets WHERE id=%d',array($id));
		} else {
			DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($id));
			DB::Execute('DELETE FROM base_dashboard_applets WHERE id=%d AND user_login_id=%d',array($id,Acl::get_user()));
		}
	}

	public static function delete_applets($module) {
		$module = str_replace('/','_',$module);

		$ret = DB::GetAll('SELECT id FROM base_dashboard_default_applets WHERE module_name=%s',array($module));
		foreach($ret as $row)
			DB::Execute('DELETE FROM base_dashboard_default_settings WHERE applet_id=%d',array($row['id']));
		DB::Execute('DELETE FROM base_dashboard_default_applets WHERE module_name=%s',array($module));

		$ret = DB::GetAll('SELECT id FROM base_dashboard_applets WHERE module_name=%s',array($module));
		foreach($ret as $row)
			DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($row['id']));
		DB::Execute('DELETE FROM base_dashboard_applets WHERE module_name=%s',array($module));
	}

	public function configure_applet($id,$mod,& $ok=null) {
		if($this->is_back()) {
			$ok=false;
			return false;
		}

		$sett_fn = array($mod.'Common','applet_settings');
		$is_conf = is_callable($sett_fn);
		$fc = $this->get_module_variable('first_conf');
		if(!$is_conf && $fc) {
			$ok=true;
			return false;
		}

		$f = &$this->init_module('Libs/QuickForm',$this->ht('Saving settings'),'settings');
		$caption = call_user_func(array($mod.'Common','applet_caption'));

		if($is_conf) {
			$f->addElement('header',null,$this->t($caption. ' settings'));

			$menu = call_user_func($sett_fn);
			if (is_array($menu))
				$this->add_module_settings_to_form($menu,$f,$id,$mod);
			else
				trigger_error('Invalid applet settings function: '.$mod,E_USER_ERROR);
		}

		$f->addElement('header',null,$this->t($caption.' display settings'));

		$color = Base_DashboardCommon::get_available_colors();
		$color[0] = $this->t('Default').': '.$this->ht(ucfirst($color[0]));
		for($k=1; $k<count($color); $k++)
			$color[$k] = '&bull; '.$this->ht(ucfirst($color[$k]));
		$f->addElement('select', '__color', $this->t('Color'), $color, array('style'=>'width: 100%;'));

		$default_dash = $this->get_module_variable('default');
		$table_tabs = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
		$table_applets = 'base_dashboard_'.($default_dash?'default_':'').'applets';
		$tabs = DB::GetAssoc('SELECT id,name FROM '.$table_tabs.($default_dash?'':' WHERE user_login_id='.Acl::get_user()));
		$f->addElement('select','__tab',$this->t('Tab'),$tabs);
		$dfs = DB::GetRow('SELECT tab,color FROM '.$table_applets.' WHERE id=%d',array($id));
		$f->setDefaults(array('__tab'=>$dfs['tab'],'__color'=>$dfs['color']));

		if($f->validate()) {
			//$f->process(array(& $this, 'submit_settings'));
			$submited = $f->exportValues();
			DB::Execute('UPDATE '.$table_applets.' SET tab=%d WHERE id=%d',array($submited['__tab'],$id));
			DB::Execute('UPDATE '.$table_applets.' SET color=%d WHERE id=%d',array($submited['__color'],$id));

			$defaults = $this->get_default_values($mod);
			$old = $this->get_values($id,$mod);
			foreach($defaults as $name=>$def_value) {
				if(!isset($submited[$name])) $submited[$name]=0;
				if($submited[$name]!=$old[$name]) {
					if($this->get_module_variable('default')) {
						if($submited[$name]==$def_value)
							DB::Execute('DELETE FROM base_dashboard_default_settings WHERE applet_id=%d AND name=%s',array($id,$name));
						else
							DB::Replace('base_dashboard_default_settings', array('applet_id'=>$id, 'name'=>$name, 'value'=>$submited[$name]), array('applet_id','name'), true);
					} else {
						if($submited[$name]==$def_value)
							DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d AND name=%s',array($id,$name));
						else
							DB::Replace('base_dashboard_settings', array('applet_id'=>$id, 'name'=>$name, 'value'=>$submited[$name]), array('applet_id','name'), true);
					}
				}
			}
			$ok = true;
			self::$settings_cache = null;
			return false;
		}
		$ok=null;
		$f->display();

		Base_ActionBarCommon::add('back','Back',$this->create_back_href());
		Base_ActionBarCommon::add('save','Save',$f->get_submit_form_href());
		Base_ActionBarCommon::add('settings','Restore defaults','onClick="'.$this->set_default_js.'" href="javascript:void(0)"');

		return true;

	}

	private function get_default_values($mod) {
		static $variables;
		if (isset($variables[$mod]))
			return $variables[$mod];

		$variables[$mod] = array();
		if(method_exists($mod.'Common', 'applet_settings')) {
			$menu = call_user_func(array($mod.'Common','applet_settings'));
			foreach($menu as $v)
				if($v['type']=='group') {
					foreach($v['elems'] as $e)
						if(isset($e['default']))
							$variables[$mod][$e['name']] = $e['default'];
				} elseif(isset($v['default']))
					$variables[$mod][$v['name']] = $v['default'];
		}
		return $variables[$mod];
	}

	private static $settings_cache;
	
	private function get_values($id,$mod) {
		if(!isset(self::$settings_cache)) {
			self::$settings_cache = array('default'=>array(), 'user'=>array());
			$ret = DB::Execute('SELECT applet_id,name,value FROM base_dashboard_default_settings');
			while($row = $ret->FetchRow())
				self::$settings_cache['default'][$row['applet_id']][] = $row;

			self::$settings_cache['user'] = array();
			if(Acl::is_user()) {
				$ret = DB::Execute('SELECT s.applet_id,s.name,s.value FROM base_dashboard_settings s INNER JOIN base_dashboard_applets a ON a.id=s.applet_id WHERE a.user_login_id=%d',array(Acl::get_user()));
				while($row = $ret->FetchRow())
					self::$settings_cache['user'][$row['applet_id']][] = $row;
			} 
		}
		if($this->get_module_variable('default'))
			$c = self::$settings_cache['default'];
		else
			$c = self::$settings_cache['user'];

		if(!isset($c[$id]))
			$c = array();
		else
			$c = $c[$id];

		$variables = $this->get_default_values($mod);
		
		foreach($c as $v)
			$variables[$v['name']] = $v['value'];

		return $variables;
	}

	private function add_module_settings_to_form($info, &$f, $id, $module){
		$values = $this->get_values($id,$module);
		foreach($info as & $v){
			if(isset($v['label'])) $v['label'] = $this->t($v['label']);
			if(isset($v['values']) && is_array($v['values']))
				foreach($v['values'] as &$x)
					$x = $this->ht($x);
			if (isset($v['rule'])) {
				if(isset($v['rule']['message']) && isset($v['rule']['type'])) $v['rule'] = array($v['rule']);
				foreach ($v['rule'] as & $r)
					if (isset($r['message'])) $r['message'] = $this->t($r['message']);
			}
		}
		$this->set_default_js = '';
		$f -> add_array($info, $this->set_default_js);
		$f -> setDefaults($values);
	}

	public function caption() {
		return "Dashboard";
	}

	//////////////////////////////////////////////////////////
	//default dashboard
	public function admin() {
		$this->set_module_variable('default',true);
		$this->dashboard();
	}

	public function set_default_applets() {
		$tabs = DB::GetAll('SELECT id,pos,name FROM base_dashboard_default_tabs');
		foreach($tabs as $tab) {
			DB::Execute('INSERT INTO base_dashboard_tabs(user_login_id,pos,name) VALUES(%d,%d,%s)',array(Acl::get_user(),$tab['pos'],$tab['name']));
			$id = DB::Insert_ID('base_dashboard_tabs','id');

			$ret = DB::GetAll('SELECT id,module_name,col,color,tab FROM base_dashboard_default_applets WHERE tab=%d ORDER BY pos',array($tab['id']));
			foreach($ret as $row) {
				DB::Execute('INSERT INTO base_dashboard_applets(module_name,col,user_login_id,color,tab) VALUES(%s,%d,%d,%d,%d)',array($row['module_name'],$row['col'],Acl::get_user(),$row['color'],$id));
				$ins_id = DB::Insert_ID('base_dashboard_applets','id');
				$ret_set = DB::GetAll('SELECT name,value FROM base_dashboard_default_settings WHERE applet_id=%d',array($row['id']));
				foreach($ret_set as $row_set)
					DB::Execute('INSERT INTO base_dashboard_settings(applet_id,value,name) VALUES(%d,%s,%s)',array($ins_id,$row_set['value'],$row_set['name']));
			}
		}
	}

}

?>
