<?php
/**
 * Help class.
 *
 * This class provides interactive help.
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage help
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_HelpCommon extends ModuleCommon {
	public static function screen_name($name) {
		print('<span style="display:none;" class="Base_Help__screen_name" value="'.$name.'"></span>');
	}
	public static function retrieve_help_from_file($module) {
		$file = 'modules/'.str_replace('_','/',$module).'/help/tutorials.hlp';
		if (file_exists($file))
		$f = fopen($file, 'r');
		$ret = array();
		$i = 0;
		while (!feof($f)) {
			$line = '';
			while (!feof($f) && substr($line, -1, 1)!=']') {
				$line .= ($line?'##':'').fgets($f);
				$line = trim($line);
			}
			$line = trim($line, '[]');
			if (!$line) continue;
			$line = explode(':', $line);
			$func = array_shift($line);
			$arg = implode(':', $line);
			switch ($func) {
				case 'LABEL': 	$i++;
								$ret[$i] = array('label'=>$arg, 'keywords'=>'', 'context'=>false, 'steps'=>'');
								break;
				case 'STEPS': 	$arg = explode('##', $arg);
								foreach ($arg as $k=>$v) {
									if (!$v) {
										unset($arg[$k]);
										continue;
									}
									$tmp = explode('//', $v);
									if (isset($tmp[1])) {
										$arg[$k] = $tmp[0].'//'._V(trim($tmp[1]));
									}
								}
								$arg = implode('##', $arg);
								$ret[$i]['steps'] = $arg;
								break;
				case 'KEYWORDS': $ret[$i]['keywords'] = $arg;
								break;
				case 'CONTEXT': $ret[$i]['context'] = (strtolower($arg)=='true')?true:false;
								break;
				default:
			}
		}
		return $ret;
	}
}

?>
