<?php
/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence TL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Variable {
	private static $variables;
	
	private static function load() {
		if(!isset(self::$variables)) {
		    self::$variables = array();
		    $ret = DB::Execute("SELECT name,value FROM variables");
		    while($row = $ret->FetchRow()) 
			self::$variables[$row['name']] = $row['value'];
		}
	}
	
	public static function get($name) {
		self::load();
		if(!array_key_exists($name,self::$variables))
			throw new Exception('No such variable in database('.var_export(self::$variables,true).'): ' . $name);
		return self::$variables[$name];
	}

	
	public static function set($name, $value) {
		self::load();
		if(!array_key_exists($name,self::$variables)) {
	    		self::$variables[$name] = $value;
			return DB::Execute("INSERT INTO variables(name,value) VALUES(%s,%s)",array($name,$value));
		} else {
			self::$variables[$name] = $value;
			return DB::Execute("UPDATE variables SET value=%s WHERE name=%s", array($value, $name));
		}
	}

	public static function delete($name) {
		self::load();
		if(!array_key_exists($name,self::$variables)) {
			throw new Exception('No such variable in database('.var_export(self::$variables,true).'): ' . $name);
		} else {
			unset(self::$variables[$name]);;
			return DB::Execute("DELETE FROM variables WHERE name=%s", $name);
		}
	}
}

?>
