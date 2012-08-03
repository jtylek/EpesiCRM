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

class Base_DashboardCommon extends ModuleCommon {
	public static function menu() {
		if(Base_AclCommon::check_permission('Dashboard'))
			return array(_M('Dashboard')=>array());
		return array();
	}

	public static function admin_access_levels() {
		return false;
	}

	public static function admin_caption() {
		return array('label'=>__('Default dashboard'), 'section'=>__('User Management'));
	}

	public static function body_access() {
		return Base_AclCommon::is_user();
	}

	public static function user_settings() {
		$color = array(1 => 'black',
                       2 => 'blue',
                       3 => 'dark-blue',
                       4 => 'dark-gray',
                       5 => 'green',
                       6 => 'dark-green',
                       7 => 'red',
                       8 => 'dark-red',
                       9 => 'yellow',
                      10 => 'dark-yellow');
		return array(__('Misc')=>array(
					array('name'=>'default_color','label'=>__('Default dashboard applet color'), 'type'=>'select', 'values'=>$color, 'default'=>'4'),
					array('name'=>'remember_tab','label'=>__('Remember last visited dashboard tab'), 'type'=>'checkbox', 'default'=>false)
				)
				);
	}

	public static function get_available_colors() {
		static $color = null;
		if ($color===null) $color = array(
							  0 => '',
                              1 => array('class'=>'black', 		'label'=>__('Black')),
                              2 => array('class'=>'blue', 		'label'=>__('Blue')),
                              3 => array('class'=>'dark-blue', 	'label'=>__('Dark blue')),
                              4 => array('class'=>'dark-gray', 	'label'=>__('Dark gray')),
                              5 => array('class'=>'green', 		'label'=>__('Green')),
                              6 => array('class'=>'dark-green', 'label'=>__('Dark green')),
                              7 => array('class'=>'red', 		'label'=>__('Red')),
                              8 => array('class'=>'dark-red', 	'label'=>__('Dark red')),
                              9 => array('class'=>'yellow', 	'label'=>__('Yellow')),
                             10 => array('class'=>'dark-yellow','label'=>__('Dark yellow')));
		$color[0] = $color[Base_User_SettingsCommon::get('Base_Dashboard','default_color')];
		return $color;
	}
	
	public static function get_installed_applets_html() {
		$colors = Base_DashboardCommon::get_available_colors();
		
		$app_cap = ModuleManager::call_common_methods('applet_caption');
		asort($app_cap);
		$app_info = ModuleManager::call_common_methods('applet_info');
		$j = 0;
		$html = '';
		foreach($app_cap as $name=>$cap) {
			if (!$cap) continue;
			$j++;
			$th = Base_ThemeCommon::init_smarty();
			$id = str_replace('_','-',$name);

			if (!isset($app_info[$name])) $app_info[$name] = '';
			$th->assign('content','<div class="content" style="padding:4px;" id="dashboard_applet_content_'.$id.'">'.
					$app_info[$name].
					'</div>');
			$th->assign('handle_class','handle');

			$th->assign('caption',$cap);
			$th->assign('color',$colors[0]['class']);

			$remove_button = Base_DashboardCommon::get_remove_applet_button($id, false);
			$th->assign('remove', $remove_button);
			$th->assign('__link', array('remove'=>Base_ThemeCommon::parse_links('remove', $remove_button)));
			//print('<xmp>'.self::get_remove_applet_button(null, false).'</xmp><br>');

			$th->assign('actions',array());

			$th->assign('config_mode',true);

			$html .= '<div class="applet" searchkey="'.($cap?$cap:$name).';'.$app_info[$name].'" order="'.$j.'" id="ab_item_'.'new_'.$id.'">';
			ob_start();
			Base_ThemeCommon::display_smarty($th,'Base_Dashboard','default');
			$html .= ob_get_clean();
			$html .= '</div>';
		}
		return $html;
	}
	
	public static function get_remove_applet_button($id, $default_dash) {
		return '<a class="remove" id="dashboard_remove_applet_'.$id.'" '.(is_numeric($id)?'':'style="display:none;" ').Utils_TooltipCommon::open_tag_attrs(__('Remove')).' href="javascript:void(0);" onClick="if(confirm(\''.__('Delete this applet?').'\'))remove_applet('.(is_numeric($id)?$id:-1).','.($default_dash?1:0).');">x</a>';
	}

	public static function remove_applet($id, $default) {
		if($default) {
			DB::Execute('DELETE FROM base_dashboard_default_settings WHERE applet_id=%d',array($id));
			DB::Execute('DELETE FROM base_dashboard_default_applets WHERE id=%d',array($id));
		} else {
			DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($id));
			DB::Execute('DELETE FROM base_dashboard_applets WHERE id=%d AND user_login_id=%d',array($id,Base_AclCommon::get_user()));
		}
	}

	public static function set_default_applets() {
		$tabs = DB::GetAll('SELECT id,pos,name FROM base_dashboard_default_tabs');
		foreach($tabs as $tab) {
			DB::Execute('INSERT INTO base_dashboard_tabs(user_login_id,pos,name) VALUES(%d,%d,%s)',array(Base_AclCommon::get_user(),$tab['pos'],$tab['name']));
			$id = DB::Insert_ID('base_dashboard_tabs','id');

			$ret = DB::GetAll('SELECT id,module_name,col,color,tab FROM base_dashboard_default_applets WHERE tab=%d ORDER BY pos',array($tab['id']));
			foreach($ret as $row) {
				DB::Execute('INSERT INTO base_dashboard_applets(module_name,col,user_login_id,color,tab) VALUES(%s,%d,%d,%d,%d)',array($row['module_name'],$row['col'],Base_AclCommon::get_user(),$row['color'],$id));
				$ins_id = DB::Insert_ID('base_dashboard_applets','id');
				$ret_set = DB::GetAll('SELECT name,value FROM base_dashboard_default_settings WHERE applet_id=%d',array($row['id']));
				foreach($ret_set as $row_set)
					DB::Execute('INSERT INTO base_dashboard_settings(applet_id,value,name) VALUES(%d,%s,%s)',array($ins_id,$row_set['value'],$row_set['name']));
			}
		}
	}
}
?>
