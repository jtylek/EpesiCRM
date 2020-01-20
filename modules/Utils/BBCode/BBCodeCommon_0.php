<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.9.0
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
        foreach ($bbcodes_raw as $v) {
            $callback = explode('::', $v['func']);
            if (is_callable($callback)) {
                self::$bbcodes[$v['code']] = $callback;
            }
        }
	}
	
	public static function optimize($text) {
		return self::parse($text, true);
	}
	
	public static function parse($text, $optimize_only=false) {
		self::$optimize_only = $optimize_only;
		if (self::$bbcodes===null) self::init();
		$ret = preg_replace_callback('/\[(.*?)(=(.*?|".*?"))?\](.*?)\[\/\\1\]/i', array('Utils_BBCodeCommon','replace'),$text);
		$ret2 = preg_replace_callback('/(\s|^)(https?:\/\/(.+?))(\s|<)/i', array('Utils_BBCodeCommon','replace_url'),$ret);
		return $ret2?$ret2:$text;
	}
	
	public static function strip($text) {
		if (self::$bbcodes===null) self::init();
		return preg_replace_callback('/\[(.*?)(=(.*?|".*?"))?\](.*?)\[\/\\1\]/i', array('Utils_BBCodeCommon','cutout'),$text);
	}
	
	public static function cutout($match) {
		$text = self::strip($match[4], self::$optimize_only);
		return $text;
		// optional (more precise) method:
/*		$tag = strtolower($match[1]);
		$param = trim(str_replace('&quot;','"',$match[3]),'"');
		$ret = null;
		if (isset(self::$bbcodes[$tag])) {
			self::$last_tag = $tag;
			$ret = call_user_func(self::$bbcodes[$tag], $text, $param, self::$optimize_only);
		}
		if ($ret) return $text;
		return $match[0];*/
	}
	
	public static function replace_url($match) {
	    if(self::$optimize_only) return $match[0];
	    return $match[1].self::tag_url($match[3],$match[2]).$match[4];
	}

	public static function replace($match) {
		$text = self::parse($match[4], self::$optimize_only);
		$tag = strtolower($match[1]);
		$param = trim(str_replace('&quot;','"',$match[3]),'"');
		$ret = null;
		if (isset(self::$bbcodes[$tag])) {
			self::$last_tag = $tag;
			$ret = call_user_func(self::$bbcodes[$tag], $text, $param, self::$optimize_only);
		} else if ($tag == 'rb') {
			self::$last_tag = $tag;
			$arr = explode('/', $param);
			if (count($arr) == 2) {
				$ret = Utils_RecordBrowserCommon::record_bbcode($arr[0], null, $text, $arr[1], self::$optimize_only, 'rb');
			}
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

	public static function tag_url($text, $param=null, $optimize_only=false) {
		if ($optimize_only) return null;
		$url = trim($param?$param:$text, ' ');
		if (strpos(strtolower($url), 'http://')===false && 
			strpos(strtolower($url), 'https://')===false && 
			$url) $url = 'http://'.$url;
		return '<a href="'.$url.'" target="_blank">'.$text.'</a>';
	}

	public static function tag_img($text, $param, $optimize_only=false) {
		if ($optimize_only) return null;
		return '<img src="'.$param.'">'.$text.'</a>';
	}
}

?>
