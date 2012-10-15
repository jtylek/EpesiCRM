<?php
/**
 * Provides login audit log
 * @author Paul Bukowski <pbukowski@telaxus.com> & Janusz Tylek <jtylek@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-crm
 * @subpackage loginaudit
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_LoginAuditCommon extends ModuleCommon {

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
			if (!$row['active']) $result[$row['id']] .= ' ('.__('inactive').')';
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
		if(isset($_SESSION['base_login_audit']) && isset($_SESSION['base_login_audit_user']) && $_SESSION['base_login_audit_user']==Acl::get_user()) {
			DB::Execute('UPDATE base_login_audit SET end_time=%T WHERE id=%d',array(time(),$_SESSION['base_login_audit']));
		} elseif(Acl::is_user()) {
			$now = time();
			$remote_address = $_SERVER['REMOTE_ADDR'];
			$remote_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
			DB::Execute('INSERT INTO base_login_audit(user_login_id,start_time,end_time,ip_address,host_name) VALUES(%d,%T,%T,%s,%s)',array(Acl::get_user(),$now,$now,$remote_address,$remote_host));
			$_SESSION['base_login_audit'] = DB::Insert_ID('base_login_audit','id');
			$_SESSION['base_login_audit_user'] = Acl::get_user();
		}
	}
}
on_init(array('CRM_LoginAuditCommon','init'));
?>
