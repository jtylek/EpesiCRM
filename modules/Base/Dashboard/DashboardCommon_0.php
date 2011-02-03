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
		if(Acl::is_user())
			return array('Dashboard'=>array());
		return array();
	}

	public static function admin_access() {
		return self::Instance()->acl_check('set default dashboard');
	}

	public static function admin_caption() {
		return 'Default dashboard';
	}

	public static function body_access() {
		return Acl::is_user();
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
		return array('Misc'=>array(
					array('name'=>'default_color','label'=>'Default dashboard applet color', 'type'=>'select', 'values'=>$color, 'default'=>'4'),
					array('name'=>'remember_tab','label'=>'Remember last visited dashboard tab', 'type'=>'checkbox', 'default'=>false)
//			array('name'=>'display','label'=>'zAction bar displays','type'=>'select','values'=>array('icons only'=>'icons only','text only'=>'text only','both'=>'both'),'default'=>'both','reload'=>true)
				)
				);
	}

	public static function get_available_colors() {
		static $color = array(0 => '',
                              1 => 'black',
                              2 => 'blue',
                              3 => 'dark-blue',
                              4 => 'dark-gray',
                              5 => 'green',
                              6 => 'dark-green',
                              7 => 'red',
                              8 => 'dark-red',
                              9 => 'yellow',
                             10 => 'dark-yellow');
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
			$j++;
			$th = Base_ThemeCommon::init_smarty();
			$id = str_replace('_','-',$name);

			if (!isset($app_info[$name])) $app_info[$name] = '';
			$th->assign('content','<div class="content" style="padding:4px;" id="dashboard_applet_content_'.$id.'">'.
					$app_info[$name].
					'</div>');
			$th->assign('handle_class','handle');

			$th->assign('caption',Base_LangCommon::ts('Base_Dashboard',$cap));
			$th->assign('color',$colors[0]);

			$remove_button = Base_DashboardCommon::get_remove_applet_button($id, false);
			$th->assign('remove', $remove_button);
			$th->assign('__link', array('remove'=>Base_ThemeCommon::parse_links('remove', $remove_button)));
			//print('<xmp>'.self::get_remove_applet_button(null, false).'</xmp><br>');

			$th->assign('actions',array());

			$th->assign('config_mode',true);

			$html .= '<div class="applet" searchkey="'.Base_LangCommon::ts('Base_Dashboard',$cap).';'.$app_info[$name].'" order="'.$j.'" id="ab_item_'.'new_'.$id.'">';
			ob_start();
			Base_ThemeCommon::display_smarty($th,'Base_Dashboard','default');
			$html .= ob_get_clean();
			$html .= '</div>';
		}
		return $html;
	}
	
	public static function get_remove_applet_button($id, $default_dash) {
		return '<a class="remove" id="dashboard_remove_applet_'.$id.'" '.(is_numeric($id)?'':'style="display:none;" ').Utils_TooltipCommon::open_tag_attrs(Base_LangCommon::ts('Base_Dashboard','Remove')).' href="javascript:void(0);" onClick="if(confirm(\''.Base_LangCommon::ts('Base_Dashboard','Delete this applet?').'\'))remove_applet('.(is_numeric($id)?$id:-1).','.($default_dash?1:0).');">x</a>';
	}

	public static function remove_applet($id, $default) {
		if($default) {
			DB::Execute('DELETE FROM base_dashboard_default_settings WHERE applet_id=%d',array($id));
			DB::Execute('DELETE FROM base_dashboard_default_applets WHERE id=%d',array($id));
		} else {
			DB::Execute('DELETE FROM base_dashboard_settings WHERE applet_id=%d',array($id));
			DB::Execute('DELETE FROM base_dashboard_applets WHERE id=%d AND user_login_id=%d',array($id,Acl::get_user()));
		}
	}

}
?>
