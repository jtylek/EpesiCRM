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
		if(!Base_AclCommon::check_permission('Dashboard')) return;
		$this->help('Dashboard Help','main');

		if(ModuleManager::is_installed('Utils/RecordBrowser')>=0) //speed up links to RB
			if(Utils_RecordBrowserCommon::check_for_jump()) return;

		$this->dashboard();
	}
	
	private function dashboard() {
		load_js($this->get_module_dir().'ab.js');
		$default_dash = $this->get_module_variable('default');
		$config_mode = $this->get_module_variable('config_mode', false);
		if ($config_mode) {
			Base_ActionBarCommon::add('back',__('Done'),$this->create_callback_href(array($this,'switch_config_mode')));
		} else {
			Base_ActionBarCommon::add('settings',__('Config'),$this->create_callback_href(array($this,'switch_config_mode')));
		}

		if($default_dash)
			$tabs = DB::GetAll('SELECT * FROM base_dashboard_default_tabs ORDER BY pos');
		else {
			$tabs = DB::GetAll('SELECT * FROM base_dashboard_tabs WHERE user_login_id=%d ORDER BY pos',array(Base_AclCommon::get_user()));
			if(!$tabs) {
				Base_DashboardCommon::set_default_applets();
				$tabs = DB::GetAll('SELECT * FROM base_dashboard_tabs WHERE user_login_id=%d ORDER BY pos',array(Base_AclCommon::get_user()));
			}
		}

		if ($config_mode) {
			// *** New tab code ****
			$f = $this->init_module('Libs_QuickForm');
			$f->addElement('hidden', 'tab_name', 'Tab Name', array('id'=>'dashboard_tab_name'));
			$f->addElement('hidden', 'id', 'Tab ID', array('id'=>'dashboard_tab_id'));
			$f->display();
			if ($f->validate()) {
				$vals = $f->exportValues();
				$name = $vals['tab_name'];
				if ($name) {
					$id = $vals['id'];
					$table = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
					if ($id)
						DB::Execute('UPDATE '.$table.' SET name=%s WHERE id=%d',array($name,$id));
					else {
						if($default_dash) {
							$max = DB::GetOne('SELECT max(pos)+1 FROM '.$table);
							if ($max===false || $max===null) $max=0;
							DB::Execute('INSERT INTO '.$table.'(name,pos) VALUES(%s,%d)',array($name,$max));
						} else {
							$max = DB::GetOne('SELECT max(pos)+1 FROM '.$table.' WHERE user_login_id=%d',array(Base_AclCommon::get_user()));
							if ($max===false || $max===null) $max=0;
							DB::Execute('INSERT INTO '.$table.'(name,pos,user_login_id) VALUES(%s,%d,%d)',array($name,$max,Base_AclCommon::get_user()));
						}
					}
					location(array());
					return;
				}
			}
			eval_js('edit_dashboard_tab=function(id){if(get_new_dashboard_tab_name("'.__('Enter label for the Dashboard tab').'","'.__('Label cannot be empty').'",id)){'.$f->get_submit_form_js().'}}');
		}

		if ($config_mode)
			print('<table style="width:100%;"><tr><td style="width:75%;vertical-align:top;">');
		if(count($tabs)>1 || $config_mode) {
			foreach($tabs as $tab) {
				$label = $tab['name'];
				$buttons = array();
				if ($config_mode) {
					$label .= '&nbsp;';
					if($tab['pos']>$tabs[0]['pos'])
						$label .= '<a '.$this->create_callback_href(array($this,'move_tab'),array($tab['id'],$tab['pos'],-1)).'><img src="'.Base_ThemeCommon::get_template_file('/Base/Dashboard','roll-left.png').'" onMouseOver="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','roll-left-hover.png').'\';" onMouseOut="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','roll-left.png').'\';" width="14" height="14" alt="<" border="0"></a>';
					if($tab['pos']<$tabs[count($tabs)-1]['pos'])
						$label .= '<a '.$this->create_callback_href(array($this,'move_tab'),array($tab['id'],$tab['pos'],+1)).'><img src="'.Base_ThemeCommon::get_template_file('/Base/Dashboard','roll-right.png').'" onMouseOver="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','roll-right-hover.png').'\';" onMouseOut="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','roll-right.png').'\';" width="14" height="14" alt="<" border="0"></a>';
					$label .= '<a href="javascript:void(0);" onclick="edit_dashboard_tab('.$tab['id'].');"><img src="'.Base_ThemeCommon::get_template_file('/Base/Dashboard','configure.png').'" onMouseOver="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','configure-hover.png').'\';" onMouseOut="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','configure.png').'\';" width="14" height="14" alt="<" border="0"></a>';
					$label .= '<a '.$this->create_confirm_callback_href(__('Delete this tab and all applets assigned to it?'),array($this,'delete_tab'),$tab['id']).'><img src="'.Base_ThemeCommon::get_template_file('/Base/Dashboard','close.png').'" onMouseOver="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','close-hover.png').'\';" onMouseOut="this.src=\''.Base_ThemeCommon::get_template_file('/Base/Dashboard','close.png').'\';" width="14" height="14" alt="<" border="0"></a>';
				}
				$this->tb->set_tab($label, array($this,'display_dashboard'),$tab['id'], $config_mode, $buttons);
			}
			if ($config_mode) {
				// *** New tab button ****
				$this->tb->start_tab(__('Add new Tab'));
				$this->tb->set_href('href="javascript:void(0);" onclick="edit_dashboard_tab(null);"');
				$this->tb->end_tab();
			}

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
		if ($config_mode) {
			print('</td>');
			print('<td id="dashboard" style="vertical-align:top;">');
			$search_caption = __('Search applets...');
			print('<input type="text" id="dashboard_applets_filter" style="color:#555;width:90%;" value="'.$search_caption.'" onblur="dashboard_prepare_filter_box(0,\''.$search_caption.'\')" onfocus="dashboard_prepare_filter_box(1,\''.$search_caption.'\')" onkeyup="dashboard_filter_applets()">');
			print('<div id="dashboard_applets_new_scroll" style="overflow-y:auto;overflow-x: hidden;height:200px;vertical-align:top">');
			print('<div id="dashboard_applets_new" style="vertical-align:top">');

			print(Base_DashboardCommon::get_installed_applets_html());
			
			print('</div>');
			print('</div>');
			print('</td></tr></table>');
			eval_js('var dim=document.viewport.getDimensions();var dct=$("dashboard_applets_new_scroll");dct.style.height=(Math.max(dim.height,document.documentElement.clientHeight)-150)+"px";');
		}
	}
	
	public function switch_config_mode() {
		$this->set_module_variable('config_mode', !$this->get_module_variable('config_mode', false));
	}

	public function display_dashboard($tab_id) {
//		Base_ActionBarCommon::add('add',__('Add applet'),$this->create_callback_href(array($this,'applets_list'),$tab_id));

		$default_dash = $this->get_module_variable('default');
		$colors = Base_DashboardCommon::get_available_colors();
		$applets = array(0=>array(),1=>array(),2=>array());
		$config_mode = $this->get_module_variable('config_mode', false);
		if($default_dash)
			$ret = DB::Execute('SELECT col,id,module_name,color FROM base_dashboard_default_applets WHERE tab=%d ORDER BY col,pos',array($tab_id));
		else
			$ret = DB::Execute('SELECT col,id,module_name,color FROM base_dashboard_applets WHERE user_login_id=%d AND tab=%d ORDER BY pos',array(Base_AclCommon::get_user(),$tab_id));
		while($row = $ret->FetchRow())
			$applets[$row['col']][] = $row;

		print('<div id="dashboard" style="width: 100%;">');
		for($j=0; $j<3; $j++) {
			print('<div id="dashboard_applets_'.$tab_id.'_'.$j.'" style="width:33%;min-height:200px;padding-bottom:10px;vertical-align:top;display:inline-block">');

			foreach($applets[$j] as $row) {
				if (!is_callable(array($row['module_name'].'Common', 'applet_caption'))) continue;
				$cap = call_user_func(array($row['module_name'].'Common', 'applet_caption'));
				if(!$cap || ModuleManager::is_installed($row['module_name'])==-1) {//if its invalid entry
					continue;
				}

				$m = $this->init_module($row['module_name'],null,$row['id']);

				$opts = array();
				$opts['title'] = $cap;
				$opts['toggle'] = true;
				$opts['href'] = null;
				$opts['go'] = false;
				$opts['go_function'] = 'body';
				$opts['go_arguments'] = array();
				$opts['go_constructor_arguments'] = array();
				$opts['actions'] = array();
				$opts['id'] = $row['id'];

				$th = $this->init_module('Base/Theme');

				if ($config_mode || !$m) $content = '';
				else $content = $this->get_html_of_module($m,array($this->get_values($row['id'],$row['module_name']), & $opts, $row['id']),'applet');
				$th->assign('content','<div class="content">'.
						$content.
						'</div>');
				$th->assign('handle_class','handle');

				if($opts['toggle'] && !$config_mode)
					$th->assign('toggle','<a class="toggle" '.Utils_TooltipCommon::open_tag_attrs(__('Toggle')).'>=</a>');
					
				foreach ($opts['actions'] as $k=>$v)
					if (!$v) unset($opts['actions'][$k]);

				if($opts['go'])
					$opts['href']=$this->create_main_href($row['module_name'],$opts['go_function'],$opts['go_arguments'],$opts['go_constructor_arguments']);
				if($opts['href'])
					$th->assign('href','<a class="href" '.Utils_TooltipCommon::open_tag_attrs(__('Fullscreen')).' '.$opts['href'].'>G</a>');

				$th->assign('remove',Base_DashboardCommon::get_remove_applet_button($row['id'], $default_dash));
				
				if (!$config_mode)
					$th->assign('configure','<a class="configure" '.Utils_TooltipCommon::open_tag_attrs(__('Configure')).' '.$this->create_callback_href(array($this,'configure_applet'),array($row['id'],$row['module_name'])).'>c</a>');

				$th->assign('caption',$opts['title']);
				$th->assign('color',$colors[$row['color']]['class']);
				
				$th->assign('actions',$opts['actions']);

				$th->assign('config_mode',$config_mode);

				print('<div class="applet" id="ab_item_'.$row['id'].'">');
				$th->display();
				print('</div>');
			}
			print('</div>');
		}
		print('</div>');
		eval_js('dashboard_activate('.$tab_id.','.($default_dash?1:0).')');
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

	public function move_tab($id,$old_pos,$dir) {
		$default_dash = $this->get_module_variable('default');
		$table = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
		DB::StartTrans();
		$new_pos = DB::GetOne('SELECT '.($dir>0?'MIN':'MAX').'(pos) FROM '.$table.' WHERE pos'.($dir>0?'>':'<').'%d AND user_login_id=%s',array($old_pos, Base_AclCommon::get_user()));
		$id2 = DB::GetOne('SELECT id FROM '.$table.' WHERE pos=%d AND user_login_id=%s',array($new_pos,Base_AclCommon::get_user()));
		DB::Execute('UPDATE '.$table.' SET pos=%d WHERE id=%d',array($old_pos,$id2));
		DB::Execute('UPDATE '.$table.' SET pos=%d WHERE id=%d',array($new_pos,$id));
		DB::CompleteTrans();
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
			$cols = DB::GetAssoc('SELECT col,count(id) FROM base_dashboard_applets WHERE user_login_id=%d AND tab=%d GROUP BY col ORDER BY col',array(Base_AclCommon::get_user(),$tab_id));
			for($col=0; $col<3 && isset($cols[$col]); $col++);
			if($col==3) $col=0;
			if(isset($cols[$col]))
				$pos = $cols[$col];
			DB::Execute('INSERT INTO base_dashboard_applets(user_login_id,module_name,tab,col,pos) VALUES (%d,%s,%d,%d,%d)',array(Base_AclCommon::get_user(),$mod,$tab_id,$col,$pos));
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
			DB::Execute('DELETE FROM base_dashboard_applets WHERE id=%d AND user_login_id=%d',array($id,Base_AclCommon::get_user()));
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

		$f = $this->init_module('Libs/QuickForm',__('Saving settings'),'settings');
		$caption = call_user_func(array($mod.'Common','applet_caption'));

		if($is_conf) {
			$f->addElement('header',null,__('%s settings', array($caption)));

			$menu = call_user_func($sett_fn);
			if (is_array($menu))
				$this->add_module_settings_to_form($menu,$f,$id,$mod);
			else
				trigger_error('Invalid applet settings function: '.$mod,E_USER_ERROR);
		}

		$f->addElement('header',null,$caption.' '.__('display settings'));

		$color = Base_DashboardCommon::get_available_colors();
		$color[0] = __('Default').': '.$color[0]['label'];
		for($k=1; $k<count($color); $k++)
			$color[$k] = '&bull; '.$color[$k]['label'];
		$f->addElement('select', '__color', __('Color'), $color, array('style'=>'width: 100%;'));

		$default_dash = $this->get_module_variable('default');
		$table_tabs = 'base_dashboard_'.($default_dash?'default_':'').'tabs';
		$table_applets = 'base_dashboard_'.($default_dash?'default_':'').'applets';
		$tabs = DB::GetAssoc('SELECT id,name FROM '.$table_tabs.($default_dash?'':' WHERE user_login_id='.Base_AclCommon::get_user()));
		$f->addElement('select','__tab',__('Tab'),$tabs);
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

		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		Base_ActionBarCommon::add('save',__('Save'),$f->get_submit_form_href());
		Base_ActionBarCommon::add('settings',__('Restore Defaults'),'onClick="'.$this->set_default_js.'" href="javascript:void(0)"');

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
			if(Base_AclCommon::is_user()) {
				$ret = DB::Execute('SELECT s.applet_id,s.name,s.value FROM base_dashboard_settings s INNER JOIN base_dashboard_applets a ON a.id=s.applet_id WHERE a.user_login_id=%d',array(Base_AclCommon::get_user()));
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
			if (isset($v['rule'])) {
				if(isset($v['rule']['message']) && isset($v['rule']['type'])) $v['rule'] = array($v['rule']);
			}
		}
		$this->set_default_js = '';
		$f -> add_array($info, $this->set_default_js);
		$f -> setDefaults($values);
	}

	public function caption() {
		return __('Dashboard');
	}

	//////////////////////////////////////////////////////////
	//default dashboard
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}
		Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());
		
		$this->set_module_variable('default',true);
		$this->dashboard();
	}

}

?>
