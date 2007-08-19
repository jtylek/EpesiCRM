<?php

/**
 * This file defines all other base functionality.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @package epesi-base
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Generates random string of specified length.
 * 
 * @param integer length
 * @return string random string
 */
function generate_password($length = 8) {
	// start with a blank password
	$password = "";

	// define possible characters
	$possible = "0123456789bcdfghjkmnpqrstvwxyz";

	// set up a counter
	$i = 0;

	// add random characters to $password until $length is reached
	while ($i < $length) {
		// pick a random character from the possible ones
		$char = substr($possible, mt_rand(0, strlen($possible) - 1), 1);

		// we don't want this character if it's already in the password
		if (!strstr($password, $char)) {
			$password .= $char;
			$i++;
		}
	}
	// done!
	return $password;
}

/**
 * Redirects to specified url. First parameter is array of variables to pass with redirection.
 * If no argument is specified returns saved redirect url.
 * 
 * @param array
 * @return string saved url
 */
function location($u = null,$ret = false) {
	static $variables = false;

	if($ret) {
		$ret = $variables;
		$variables = false;
		return $ret;
	}
	
	if($variables==false) $variables=array();

	if (is_array($u))
		$variables = array_merge($variables, $u);
}

/**
 * Requests css loading.
 * 
 * @param string css file path and name
 */
function load_css($u) {
	global $base;
	$session = & $base->get_tmp_session();
	if (is_string($u) && (!isset($session['__loaded_csses__']) || !array_key_exists($u, $session['__loaded_csses__']))) {
		$base->js('load_css(\'' . addslashes($u) . '\')');
		$session['__loaded_csses__'][$u] = 1;
		return true;
	}
	return false;
}

/**
 * Adds js to load.
 * 
 * @param string javascrpit code
 */
function load_js($u) {
	return eval_js_once('load_js(\'' . addslashes($u) . '\')');
}
/**
 * Adds js to load inline.
 * 
 * @param string javascrpit code
 * @return bool true on success, false otherwise
 */
function load_js_inline($u) {
	return eval_js_once( file_get_contents($u) );
}
/**
 * Adds js block to eval. If no argument is specified returns saved jses.
 * 
 * @param string javascrpit code
 */
function eval_js($u) {
	global $base;
	if (is_string($u)) {
		$base->js($u);
	}
}
/**
 * Adds js block to eval. Given js will be evaluated only once.
 * 
 * @param string javascrpit code
 * @return bool true on success, false otherwise
 */
function eval_js_once($u) {
	global $base;
	if(!is_string($u)) return false;
	$session = & $base->get_tmp_session();
	$md5 = md5($u);
	if (!isset($session['__evaled_jses__'][$md5])) {
		$base->js($u);
		$session['__evaled_jses__'][$md5] = true;
		return true;
	}
	return false;
}

/**
 * Adds method to call on exit.
 * 
 * @param mixed function to call
 * @param mixed list of arguments
 * @param bool if set to false the function will be called only once, location() doesn't affect with double call
 * @param bool if set to true the function will return currently hold list of functions (don't use it in modules)
 * @return mixed returns function list if requested, true if function was added to list, false otherwise
 */
function on_exit($u = null, $args = null, $stable=true, $ret = false) {
	static $headers = array ();
	
	if($ret) {
		$ret = $headers;
		$headers = array ();
		foreach($ret as $v)
			if($v['stable']) $headers[] = $v;
		return $ret;
	}

	if ($u != false) {
		$headers[] = array('func'=>$u,'args'=>$args, 'stable'=>$stable);
		return true;
	}
	return false;
}
/**
 * Adds method to call on init.
 * 
 * @param mixed function to call
 * @param mixed list of arguments
 * @param bool if set to false the function will be called only once, location() doesn't affect with double call
 * @param bool if set to true the function will return currently hold list of functions (don't use it in modules)
 * @return mixed function list if requested, true if function was added to list, false otherwise
 */
function on_init($u = null, $args = null, $stable=true, $ret = false) {
	static $headers = array ();
	
	if($ret) {
		$ret = $headers;
		$headers = array ();
		foreach($ret as $v)
			if($v['stable']) $headers[] = $v;
		return $ret;
	}

	if ($u != false)
		$headers[] = array('func'=>$u,'args'=>$args, 'stable'=>$stable);
}

if (STRIP_OUTPUT) {
	function strip_html($data) {
		// strip unecessary comments and characters from a webpages text
		// all line comments, multi-line comments \\r \\n \\t multi-spaces that make a script readable.
		// it also safeguards enquoted values and values within textareas, as these are required

		$data = preg_replace_callback("/>[^<]*<\\/textarea/i", "harden_characters", $data);
		$data = preg_replace_callback("/\"[^\"<>]+\"/", "harden_characters", $data);

		$data = preg_replace("/(\\t|\\r|\\n)/", "", $data); // remove new lines \\n, tabs and \\r

		$data = preg_replace_callback("/\"[^\"<>]+\"/", "unharden_characters", $data);
		$data = preg_replace_callback("/>[^<]*<\\/textarea/", "unharden_characters", $data);

		return $data;
	}

	function harden_characters($array) {
		$safe = $array[0];
		$safe = preg_replace('/\\n/', "%0A", $safe);
		$safe = preg_replace('/\\t/', "%09", $safe);
		return $safe;
	}

	function unharden_characters($array) {
		$safe = $array[0];
		$safe = preg_replace('/%0A/', "\\n", $safe);
		$safe = preg_replace('/%09/', "\\t", $safe);
		return $safe;
	}

	function strip_js($input) {
		$stripPregs = array (
			'/^\s*$/',
			'/^\s*\/\/.*$/'
		);
		$blockStart = '/^\s*\/\/\*/';
		$blockEnd = '/\*\/\s*(.*)$/';
		$inlineComment = '/\/\*.*\*\//';
		$out = '';

		$lines = explode("\n", $input);
		$inblock = false;
		foreach ($lines as $line) {
			$keep = true;
			if ($inblock) {
				if (preg_match($blockEnd, $line)) {
					$inblock = false;
					$line = preg_match($blockEnd, '$1', $line);
					$keep = strlen($line) > 0;
				}
			} else
				if (preg_match($inlineComment, $line)) {
					$keep = true;
				} else
					if (preg_match($blockStart, $line)) {
						$inblock = true;
						$keep = false;
					}

			if (!$inblock) {
				foreach ($stripPregs as $preg) {
					if (preg_match($preg, $line)) {
						$keep = false;
						break;
					}
				}
			}

			if ($keep && !$inblock) {
				$out .= trim($line) . "\n";
			}
		}
		return $out;
	}
}
/**
 * Returns directory tree starting at given directory.
 * 
 * @param string starting directory
 * @param integer maximum depth of the tree
 * @param integer depth counter, for internal use
 * @return array directory tree
 */
function dir_tree($path, $maxdepth = -1, $d = 0) {
	if (substr($path, strlen($path) - 1) != '/') {
		$path .= '/';
	}
	$dirlist = array ();
	$dirlist[] = $path;
	if ($handle = opendir($path)) {
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
				$file = $path . $file;
				if (is_dir($file) && $d >= 0 && ($d < $maxdepth || $maxdepth < 0)) {
					$result = dir_tree($file . '/', $maxdepth, $d +1);
					$dirlist = array_merge($dirlist, $result);
				}
			}
		}
		closedir($handle);
	}
	if ($d == 0) {
		natcasesort($dirlist);
	}
	return ($dirlist);
}
/**
 * Removes directory recursively, deleteing all files stored under this directory
 * 
 * @param string directory to remove
 */
function recursive_rmdir($path) {
	if (!is_dir($path)) {
		unlink($path);
		return;
	}
	$path = rtrim($path, '/');
	$content = scandir($path);
	foreach ($content as $name) {
		if ($name == '.' || $name == '..')
			continue;
		$name = $path . '/' . $name;
		if (is_dir($name)) {
			recursive_rmdir($name);
		} else
			unlink($name);
	}
	rmdir($path);
}
/**
 * Copies directory recursively, along with all files stored under source directory.
 * If destination directory doesn't exist it will be created.
 * 
 * @param string source directory
 * @param string destination directory
 */
function recursive_copy($src, $dest) {
	if (!is_dir($src)) {
		copy($src, $dest);
		return;
	}
	$src = rtrim($src, '/');
	$dest = rtrim($dest, '/');
	if (!is_dir($dest))
		mkdir($dest);
	$content = scandir($src);
	foreach ($content as $name) {
		if ($name == '.' || $name == '..')
			continue;
		$src_name = $src . '/' . $name;
		$dest_name = $dest . '/' . $name;
		if (is_dir($src_name)) {
			mkdir($dest_name);
			recursive_copy($src_name, $dest_name);
		} else
			copy($src_name, $dest_name);
	}
}
/**
 * Escapes special characters in js code.
 * 
 * @param string js code to escape
 * @return string escaped js code
 */
function escapeJs($str) {
	// borrowed from smarty
	return strtr($str, array (
		'\\' => '\\\\',
		"'" => "\\'",
		'"' => '\\"',
		"\r" => '\\r',
		"\n" => '\\n',
		'</' => '<\/'
	));
}
?>
