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
								$ret[$i] = array('label'=>$arg, 'context'=>false, 'steps'=>'');
								break;
				case 'STEPS': 	$ret[$i]['steps'] = $arg;
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
