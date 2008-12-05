<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage bbcode
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_BBCodeCommon extends ModuleCommon {
	private static $bbcodes = null;
	private static $last_tag = null;
	private static $optimize_only = null;
	
	public static function init() {
		self::$bbcodes = array();
		$bbcodes_raw = DB::GetAll('SELECT * FROM utils_bbcode');
		foreach ($bbcodes_raw as $v)
			self::$bbcodes[$v['code']] = explode('::', $v['func']);
	}
	
	public static function optimize($text) {
		return self::parse($text, true);
	}
	
	public static function parse($text, $optimize_only=false) {
		self::$optimize_only = $optimize_only;
		if (self::$bbcodes===null) self::init();
		$matches = array();
		return preg_replace_callback('/\[(.*?)(=(.*?|".*?"))?\](.*?)\[\/\\1\]/', array('Utils_BBCodeCommon','replace'),$text);
	}
	
	public static function strip($text) {
		if (self::$bbcodes===null) self::init();
		$matches = array();
		return preg_replace_callback('/\[(.*?)(=(.*?|".*?"))?\](.*?)\[\/\\1\]/', array('Utils_BBCodeCommon','cutout'),$text);
	}
	
	public static function cutout($match) {
		$text = self::strip($match[4], self::$optimize_only);
		return $text;
		// optional (more precise) method:
		$tag = strtolower($match[1]);
		$param = trim(str_replace('&quot;','"',$match[3]),'"');
		$ret = null;
		if (isset(self::$bbcodes[$tag])) {
			self::$last_tag = $tag;
			$ret = call_user_func(self::$bbcodes[$tag], $text, $param, self::$optimize_only);
		}
		if ($ret) return $text;
		return $match[0];
	}

	public static function replace($match) {
		$text = self::parse($match[4], self::$optimize_only);
		$tag = strtolower($match[1]);
		$param = trim(str_replace('&quot;','"',$match[3]),'"');
		$ret = null;
		if (isset(self::$bbcodes[$tag])) {
			self::$last_tag = $tag;
			$ret = call_user_func(self::$bbcodes[$tag], $text, $param, self::$optimize_only);
		}
		if ($ret) return $ret;
		return $match[0];
	}
	
	public static function create_bbcode($tag, $param, $text, $comment='') {
		if ($tag===null) $tag=self::$last_tag;
		return '['.$tag.($param?'="'.$param.'"':'').($comment?':<b>'.$comment.'</b>':'').']'.$text.'[/'.$tag.']';
	}
	
	public static function new_bbcode($code, $module, $func) {
		DB::Execute('DELETE FROM utils_bbcode WHERE code=%s', array($code));
		DB::Execute('INSERT INTO utils_bbcode VALUES (%s, %s)', array($code,$module.'::'.$func));
	}
	
	public static function tag_b($text, $param=null, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<b>'.$text.'</b>';
	}

	public static function tag_i($text, $param=null, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<i>'.$text.'</i>';
	}

	public static function tag_u($text, $param=null, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<u>'.$text.'</u>';
	}

	public static function tag_s($text, $param=null, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<del>'.$text.'</del>';
	}

	public static function tag_color($text, $param, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<span style="color:'.$param.'">'.$text.'</span>';
	}

	public static function tag_url($text, $param, $optimize_only=false) {
		if ($optimize_only) return null;
		$url = trim($param?$param:$text, ' ');
		if (strpos(strtolower($url), 'http://')===false && 
			strpos(strtolower($url), 'https://')===false && 
			$url) $url = 'http://'.$url;
		return '<a href="'.$url.'">'.$text.'</a>';
	}

	public static function tag_img($text, $param, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<img src="'.$param.'">'.$text.'</a>';
	}
}

?>
