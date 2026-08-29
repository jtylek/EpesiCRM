<?php
/**
 * Shows who is logged to epesi.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-tools
 * @subpackage WhoIsOnline
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tools_WhoIsOnlineCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-people'; }

	public static function user_settings() {
		return array(__('Misc')=>array(
			array('name'=>'show_me','type'=>'checkbox','label'=>__('Show me in online users'),'default'=>1)
			));
	}
	
	public static function get() {
		DB::Execute('delete from tools_whoisonline_users where session_name not in (select name from session)');
		$ret = DB::GetCol('SELECT DISTINCT ul.login FROM tools_whoisonline_users twu INNER JOIN user_login ul on ul.id=twu.user_login_id');
		return $ret;
	}

	public static function get_ids() {
		DB::Execute('delete from tools_whoisonline_users where session_name not in (select name from session)');
		$ret = DB::GetCol('SELECT DISTINCT twu.user_login_id as id FROM tools_whoisonline_users twu');
		return $ret;
	}
}
if(!array_key_exists('tools_whoisonline', $_SESSION)
   || $_SESSION['tools_whoisonline'] != Base_AclCommon::get_user()) {
    $current_user = Base_AclCommon::get_user();
    $session_id = EpesiSession::truncated_id();
    if ($current_user && Base_User_SettingsCommon::get('Tools_WhoIsOnline','show_me')) {
        // DB::Replace() is check-then-insert-or-update, not atomic (ADOdb's own doc
        // comment on Replace() warns of this), so two concurrent requests for a
        // session not yet recorded here (e.g. several modules' AJAX calls firing at
        // once right after login) can both find no existing row and both attempt an
        // INSERT. The loser hits a harmless MySQL 1062 "Duplicate entry ... for key
        // 'PRIMARY'" - the row ends up with the correct data either way - so
        // suppress logging just that expected race instead of spamming
        // php_errors.log on every occurrence.
        $saved_error_fn = DB::IgnoreErrors();
        DB::Replace('tools_whoisonline_users', array('session_name'=>$session_id, 'user_login_id'=>$current_user), array('session_name'), true);
        $errno = DB::ErrorNo();
        DB::IgnoreErrors($saved_error_fn);
        if ($errno && $errno != 1062) {
            epesi_log("DB error [EXECUTE] $errno: ".DB::ErrorMsg()."\nQuery: Tools_WhoIsOnlineCommon Replace tools_whoisonline_users\n", 'php_errors.log');
        }
    }
    if ($session_id && !$current_user) {
        DB::Execute('DELETE FROM tools_whoisonline_users WHERE session_name=%s', array($session_id));
    }
    $_SESSION['tools_whoisonline'] = $current_user;
}
?>