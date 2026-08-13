<?php
/**
 * Develop_Translations class.
 * 
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-develop
 * @subpackage translations
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Develop_TranslationsCommon extends ModuleCommon {
	public static function menu() {
		return array(_M('Translations')=>array());
	}
	
	public static function get_user($first_name, $last_name, $ip, $credits, $website, $email) {
		$id = DB::GetOne('SELECT id FROM develop_trans_users WHERE first_name=%s AND last_name=%s AND ip=%s AND credits=%d AND credits_website=%s AND contact_email=%s', array($first_name, $last_name, $ip, $credits, $website, $email));
		if (!$id) {
			DB::Execute('INSERT INTO develop_trans_users (first_name, last_name, ip, credits, credits_website, contact_email) VALUES (%s, %s, %s, %d, %s, %s)', array($first_name, $last_name, $ip, $credits, $website, $email));
			$id = DB::Insert_ID('develop_trans_users', 'id');
		}
		return $id;
	}
	
	public static function set($id, $status, $remove_other=false) {
        $r = DB::GetRow('SELECT * FROM develop_trans_contribs WHERE id=%d', array($id));
        if (!count($r)) return false;
        $remove_other = ($remove_other && $status);

        if ($status==1) {
        	$field = 'used';
        	$lang = $r['lang'];
        	$file = 'modules/Develop/Translations/dictionaries/'.$lang.'.php';
        	if (!file_exists($file)) {
        		$f = @fopen($file, 'w');
        		if(!$f)	return false;
        		fwrite($f, "<?php\n");
        		fwrite($f, "/**\n * Translation file.\n * @package epesi-translations\n * @subpackage $lang\n */\n");
        		fwrite($f, 'global $translations;'."\n");
        		fclose($f);
        	}
        
        	$f = @fopen($file, 'a');
        	if(!$f)	return false;
        	fwrite($f, '$translations[\''.addcslashes($r['org'],'\\\'').'\']=\''.addcslashes($r['trans'],'\\\'')."';\n");
        	fclose($f);
        } else
        	$field = 'discarded';
        
        DB::Execute('UPDATE develop_trans_contribs SET '.$field.'=1 WHERE id=%d', array($id));

        if ($status==1)
            DB::Execute('UPDATE develop_trans_contribs SET used=1 WHERE used=0 AND discarded=0 AND lang=%s AND org=%s AND trans=%s', array($r['lang'], $r['org'], $r['trans']));
        if ($remove_other)
            DB::Execute('UPDATE develop_trans_contribs SET discarded=1 WHERE used=0 AND discarded=0 AND lang=%s AND org=%s', array($r['lang'], $r['org']));
        return true;
	}
}

?>