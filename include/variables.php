<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class NoSuchVariableException extends Exception {};

class Variable {
	private static $variables;

	private static function load() {
		if(!isset(self::$variables)) {
		    self::$variables = DB::GetAssoc("SELECT name,value FROM variables");
		}
	}

	public static function get($name, $throw_error=true) {
		self::load();
		if(!array_key_exists($name,self::$variables)) {
			if($throw_error)
				throw new NoSuchVariableException('No such variable in database: ' . $name);
			return '';
		}
		return unserialize(self::$variables[$name]);
	}


	public static function set($name, $value) {
		self::load();
		$value = serialize($value);
		if(!array_key_exists($name,self::$variables)) {
			self::$variables[$name] = $value;
			return DB::Execute("INSERT INTO variables(name,value) VALUES(%s,%s)",array($name,$value));
		} else {
			self::$variables[$name] = $value;
			return DB::Execute("UPDATE variables SET value=%s WHERE name=%s", array($value, $name));
		}
	}

	public static function delete($name,$throw_error=true) {
		self::load();
		if(!array_key_exists($name,self::$variables)) {
			if($throw_error)
				throw new NoSuchVariableException('No such variable in database: ' . $name);
		} else {
			unset(self::$variables[$name]);;
			return DB::Execute("DELETE FROM variables WHERE name=%s", $name);
		}
	}
}

?>
