<?php
/**
 * Provides login audit log
 * @author Paul Bukowski <pbukowski@telaxus.com> & Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage loginaudit
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_LoginAuditCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-door-open'; }

	public static function user_label($id) {
		$label = Base_UserCommon::get_user_login($id);
		$c = Utils_RecordBrowserCommon::get_id('contact', 'login', $id);
		if ($c)
			$label = CRM_ContactsCommon::contact_format_no_company($c, true).' ['.$label.']';
		return $label;
	}

	public static function user_suggestbox($str) {
		$wild = DB::Concat(DB::qstr('%'), DB::qstr($str), DB::qstr('%'));
		$contacts_raw = CRM_ContactsCommon::get_contacts(array('!login'=>'', '(~"first_name'=>$wild, '|~"last_name'=>$wild));
		$contacts = array();
		$contacts_login_ids = array();
		foreach ($contacts_raw as $c) {
			$contacts_login_ids[] = $c['login'];
			$contacts[$c['login']] = $c;
		}
		if (!empty($contacts_login_ids)) $qry_ids = ' OR id IN ('.implode(',', $contacts_login_ids).')';
		else $qry_ids = '';
		$ret = DB::SelectLimit('SELECT id, active FROM user_login WHERE login '.DB::like().' '.$wild.$qry_ids.' ORDER BY active DESC', 10);
		$result = array();
		while ($row = $ret->FetchRow()) {
			$result[$row['id']] = self::user_label($row['id']);
			if (!$row['active']) $result[$row['id']] .= ' ('.__('Inactive').')';
		}
		asort($result);
		return $result;
	}

	public static function applet_caption() {
		return __('Last Login');
	}
	
	public static function applet_info() {
		return __('Simple aplet which displays your last login information (date, IP adress)');
	}
	
	public static function admin_caption() {
		return array('label'=>__('Login Audit'), 'section'=>__('User Management'));
	}

	public static function body_access() {
		return Acl::is_user();
	}

	public static function init() {
		if (!Acl::is_user()) return;
		$tracked = isset($_SESSION['base_login_audit']) && isset($_SESSION['base_login_audit_user']) && $_SESSION['base_login_audit_user']==Acl::get_user();
		// The session (memcache/file - independent of the SQL database) can
		// easily outlive a base_login_audit row it's already pointed at: any
		// reset/reinstall of the database while this login's session is
		// still alive leaves $_SESSION thinking it's tracked, so this always
		// skipped the INSERT and update()'s UPDATE silently affected 0 rows
		// forever after - the whole session tracked nothing again until
		// logging out. Confirming the row is actually still there before
		// trusting the session flag makes this self-heal on the very next
		// request instead.
		if ($tracked && !DB::GetOne('SELECT id FROM base_login_audit WHERE id=%d',array($_SESSION['base_login_audit'])))
			$tracked = false;
		if (!$tracked) {
			$now = time();
            $remote_address = get_client_ip_address();
			// get_client_host_name() (include/misc.php) caches the reverse-DNS
			// lookup in the session against this IP, so a self-heal re-track
			// (see above) doesn't pay for gethostbyaddr() again when the IP
			// hasn't changed.
			$remote_host = get_client_host_name($remote_address);
			// Same OS/Browser label Base_User_LoginCommon::new_autologin_id()
			// uses for its "remembered devices" description - display-only,
			// not for feature detection or security decisions.
			$device = parse_user_agent() ?? '';
			DB::Execute('INSERT INTO base_login_audit(user_login_id,start_time,end_time,ip_address,host_name,device) VALUES(%d,%T,%T,%s,%s,%s)',array(Acl::get_user(),$now,$now,$remote_address,$remote_host,$device));
			$_SESSION['base_login_audit'] = DB::Insert_ID('base_login_audit','id');
			$_SESSION['base_login_audit_user'] = Acl::get_user();
		}
	}
	public static function update() {
		if(isset($_SESSION['base_login_audit']) && isset($_SESSION['base_login_audit_user']) && $_SESSION['base_login_audit_user']==Acl::get_user()) {
			DB::Execute('UPDATE base_login_audit SET end_time=%T WHERE id=%d',array(time(),$_SESSION['base_login_audit']));
		}
	}
}
on_init(array('CRM_LoginAuditCommon','init'));
register_shutdown_function(array('CRM_LoginAuditCommon','update'));

?>