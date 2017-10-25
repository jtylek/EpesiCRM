<?php

/**
 * This file defines all other base functionality.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @package epesi-base
 * @license MIT
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
	$possible = "0123456789abcdfghjkmnpqrstvwxyz";

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
function location($u = null,$ret = false, $clear = true) {
	static $variables = false;

	if($ret) {
		$ret = $variables;
		if($clear)
			$variables = false;
		return $ret;
	}

	if($variables==false) $variables=array();

	if (is_array($u))
		$variables = array_merge($variables, $u);
//	error_log('location '.print_r($u,true).' '.print_r($variables,true)."\n",3,'data/logger2');
}

/**
 * Requests css loading.
 *
 * @param string css file path and name
 */
function load_css($u,$loader=null) {
	return Epesi::load_css($u,$loader);
}

/**
 * Adds js to load.
 *
 * @param string javascript file
 * @param boolean append contents of js file instead of use src tag?
 */
function load_js($u,$loader=null) {
	return Epesi::load_js($u,$loader);
}

/**
 * Adds js block to eval. If no argument is specified returns saved jses.
 *
 * @param string javascrpit code
 */
function eval_js($u,$del_on_loc=true) {
	Epesi::js($u,$del_on_loc);
}
/**
 * Adds js block to eval. Given js will be evaluated only once.
 *
 * @param string javascrpit code
 * @return bool true on success, false otherwise
 */
function eval_js_once($u,$del_on_loc=false) {
	if(!is_string($u) || strlen($u)==0) return false;
	$md5 = md5($u);
	if (!isset($_SESSION['client']['__evaled_jses__'][$md5])) {
		Epesi::js($u,$del_on_loc);
		$_SESSION['client']['__evaled_jses__'][$md5] = true;
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
function on_exit($u = null, $args = null, $stable=true, $ret = false, $last_call=false) {
	static $headers = array ();

	if($ret) {
		if($last_call) {
			$ret = $headers;
			$headers = array();
		} else { 
			$ret = array();
			foreach($headers as $v)
				if($v['stable']) $ret[] = $v; //if stable call always
		}
		return $ret;
	}

	if ($u != false) {
		if($args===null) $args = array();
		elseif(!is_array($args))
			trigger_error('Invalid args passed to on_exit method',E_USER_ERROR);
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

	if ($u != false) {
		if($args===null) $args = array();
		elseif(!is_array($args))
			trigger_error('Invalid args passed to on_init method',E_USER_ERROR);
		$headers[] = array('func'=>$u,'args'=>$args, 'stable'=>$stable);
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
function dir_tree($path, $hidden=false, $maxdepth = -1, $d = 0) {
	if (substr($path, strlen($path) - 1) != '/') {
		$path .= '/';
	}
	$hiddens = @file_get_contents($path.'.hidden');
	if($hiddens) {
		$hiddens = explode(",",trim($hiddens));
	}
	if(is_array($hidden)) {
		if(!is_array($hiddens))
			$hiddens = array();
		$hiddens = array_merge($hiddens,$hidden);
		$show_hidden = false;
	} elseif($hidden) {
		$show_hidden = true;
	} else
		$show_hidden = false;
	$dirlist = array ();
	$dirlist[] = $path;
	if ($handle = opendir($path)) {
		while (false !== ($file = readdir($handle))) {
			if ($file == '.' || $file == '..' || (!$show_hidden && preg_match('/^\./',$file)) || ($hiddens && in_array($file,$hiddens))) 
				continue;
			$file = $path . $file;
			if (is_dir($file) && $d >= 0 && ($d < $maxdepth || $maxdepth < 0)) {
				$result = dir_tree($file . '/', $hidden, $maxdepth, $d +1);
				$dirlist = array_merge($dirlist, $result);
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
 * Returns files tree matching pattern starting at given directory.
 *
 * @param string starting directory
 * @param string glob pattern
 * @param mixed glob flags
 * @param integer maximum depth of the tree
 * @param integer depth counter, for internal use
 * @return array directory tree
 */
function preg_tree($path, $pattern, $maxdepth = -1, $d = 0) {
	if (substr($path, strlen($path) - 1) != '/') {
		$path .= '/';
	}
	$list = array();
	if ($handle = opendir($path)) {
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..') {
				$filep = $path . $file;
				if(preg_match($pattern,$file)) $list[] = $filep;
				if (is_dir($filep) && $d >= 0 && ($d < $maxdepth || $maxdepth < 0))
					$list = array_merge($list,preg_tree($filep . '/', $pattern, $maxdepth, $d +1));
			}
		}
		closedir($handle);
	}
	if ($d == 0) {
		natcasesort($list);
	}
	return $list;
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
		if (is_dir($name) && is_link($name)==false) {
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
			if (!is_dir($dest_name)) mkdir($dest_name);
			recursive_copy($src_name, $dest_name);
		} else
			copy($src_name, $dest_name);
	}
}

function recalculate_time($date,$time) {
	if (isset($time['a'])) {
		$result_h = ($time['h']%12);
		$result_m = $time['i'];
		if ($time['a']=='pm') $result_h += 12;
	} else {
		$result_m = $time['i'];
		$result_h = $time['H'];
	}
	return strtotime($date.' '.$result_h.':'.$result_m.':00');
}

function escapeJS($str,$double=true,$single=true) {return Epesi::escapeJS($str,$double,$single);}

function get_epesi_url() {
    if(defined('EPESI_URL')) return rtrim(EPESI_URL,'/') . '/';
	if(php_sapi_name() == 'cli')
		return dirname(dirname(__FILE__));
	$protocol = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!== "off") ? 'https://' : 'http://';
    $domain_name = '';
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST']) {
        $domain_name = $_SERVER['HTTP_HOST'];
    } else if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME']) {
        $domain_name = $_SERVER['SERVER_NAME'];
    }
    $url = ($domain_name ? ($protocol . $domain_name) : '') . EPESI_DIR;
    return rtrim(trim($url), '/') . '/';
}

function get_client_ip_address()
{
    $remote_address = $_SERVER['REMOTE_ADDR'];
    if (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $remote_address = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $remote_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $remote_address = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $remote_address = $_SERVER['HTTP_CLIENT_IP'];
    }
	// x-forwarded for can be a list of ip addresses
	$remote_address = explode(',', $remote_address);
    return trim($remote_address[0]);
}

function filesize_hr($size) {
	if(!is_numeric($size)) $size = filesize($size);
	$bytes = array('B','KB','MB','GB','TB');
	foreach($bytes as $val) {
		if($size > 1024){
			$size = $size / 1024;
		}else{
			break;
		}
  	}
	return number_format($size, 2)." ".$val;
}

function create_html_form(& $form_name, $action_url, $variables, $target = null, $additional_properties = array(), $method = "POST") {
    if ($form_name === null)
        $form_name = uniqid("postform");

    $properties = $additional_properties;
    if (!is_array($properties))
        $properties = array();
    $properties['action'] = $action_url;
    $properties['method'] = $method;
    $properties['name'] = $form_name;
    if ($target !== null)
        $properties['target'] = $target;
    $html = '<form';
    foreach ($properties as $name => $value)
        $html .= " $name=\"" . htmlspecialchars($value) . "\"";
    $html .= ">";  // close start tag

    foreach ($variables as $name => $value) {
        $name = htmlspecialchars($name);
        $value = htmlspecialchars($value);
        $html .= '<input type="hidden" name="' . $name . '" value="' . $value . '">';
    }
    $html .= "</form>";  // close form
    return $html;
}

////////////////////////////////////////////////////
// mobile devices

function detect_mobile_device(){
  
  // check if the user agent value claims to be windows but not windows mobile
  if(stristr($_SERVER['HTTP_USER_AGENT'],'windows')&&!(stristr($_SERVER['HTTP_USER_AGENT'],'windows ce')||stristr($_SERVER['HTTP_USER_AGENT'],'windows mobile')||stristr($_SERVER['HTTP_USER_AGENT'],'palm'))){
    return false;
  }
  // check if the user agent gives away any tell tale signs it's a mobile browser
  if(preg_match('/up.browser|up.link|windows ce|iemobile|mini|mmp|symbian|midp|wap|phone|pocket|mobile|pda|psp|ppc|symbian/i',$_SERVER['HTTP_USER_AGENT'])){
    return true;
  }
  // check the http accept header to see if wap.wml or wap.xhtml support is claimed
  if(isset($_SERVER['HTTP_ACCEPT']) && (stristr($_SERVER['HTTP_ACCEPT'],'text/vnd.wap.wml')||stristr($_SERVER['HTTP_ACCEPT'],'application/vnd.wap.xhtml+xml'))){
    return true;
  }
  // check if there are any tell tales signs it's a mobile device from the _server headers
  if(isset($_SERVER['HTTP_X_WAP_PROFILE'])||isset($_SERVER['HTTP_PROFILE'])||isset($_SERVER['X-OperaMini-Features'])||isset($_SERVER['UA-pixels'])){
    return true;
  }
  // build an array with the first four characters from the most common mobile user agents
  $a = array(
                    'acs-'=>'acs-',
                    'alav'=>'alav',
                    'alca'=>'alca',
                    'amoi'=>'amoi',
                    'audi'=>'audi',
                    'aste'=>'aste',
                    'avan'=>'avan',
                    'benq'=>'benq',
                    'bird'=>'bird',
                    'blac'=>'blac',
                    'blaz'=>'blaz',
                    'brew'=>'brew',
                    'cell'=>'cell',
                    'cldc'=>'cldc',
                    'cmd-'=>'cmd-',
                    'dang'=>'dang',
                    'doco'=>'doco',
                    'eric'=>'eric',
                    'hipt'=>'hipt',
                    'inno'=>'inno',
                    'ipaq'=>'ipaq',
                    'java'=>'java',
                    'jigs'=>'jigs',
                    'kddi'=>'kddi',
                    'keji'=>'keji',
                    'leno'=>'leno',
                    'lg-c'=>'lg-c',
                    'lg-d'=>'lg-d',
                    'lg-g'=>'lg-g',
                    'lge-'=>'lge-',
                    'maui'=>'maui',
                    'maxo'=>'maxo',
                    'midp'=>'midp',
                    'mits'=>'mits',
                    'mmef'=>'mmef',
                    'mobi'=>'mobi',
                    'mot-'=>'mot-',
                    'moto'=>'moto',
                    'mwbp'=>'mwbp',
                    'nec-'=>'nec-',
                    'newt'=>'newt',
                    'noki'=>'noki',
                    'opwv'=>'opwv',
                    'palm'=>'palm',
                    'pana'=>'pana',
                    'pant'=>'pant',
                    'pdxg'=>'pdxg',
                    'phil'=>'phil',
                    'play'=>'play',
                    'pluc'=>'pluc',
                    'port'=>'port',
                    'prox'=>'prox',
                    'qtek'=>'qtek',
                    'qwap'=>'qwap',
                    'sage'=>'sage',
                    'sams'=>'sams',
                    'sany'=>'sany',
                    'sch-'=>'sch-',
                    'sec-'=>'sec-',
                    'send'=>'send',
                    'seri'=>'seri',
                    'sgh-'=>'sgh-',
                    'shar'=>'shar',
                    'sie-'=>'sie-',
                    'siem'=>'siem',
                    'smal'=>'smal',
                    'smar'=>'smar',
                    'sony'=>'sony',
                    'sph-'=>'sph-',
                    'symb'=>'symb',
                    't-mo'=>'t-mo',
                    'teli'=>'teli',
                    'tim-'=>'tim-',
                    'tosh'=>'tosh',
                    'treo'=>'treo',
                    'tsm-'=>'tsm-',
                    'upg1'=>'upg1',
                    'upsi'=>'upsi',
                    'vk-v'=>'vk-v',
                    'voda'=>'voda',
                    'wap-'=>'wap-',
                    'wapa'=>'wapa',
                    'wapi'=>'wapi',
                    'wapp'=>'wapp',
                    'wapr'=>'wapr',
                    'webc'=>'webc',
                    'winw'=>'winw',
                    'winw'=>'winw',
                    'xda-'=>'xda-'
                  );
  // check if the first four characters of the current user agent are set as a key in the array
  if(isset($a[substr($_SERVER['HTTP_USER_AGENT'],0,4)])){
    return true;
  }
}

function detect_iphone(){
	if (!isset($_SERVER['HTTP_USER_AGENT'])) return false;
  if(preg_match('/iphone/i',$_SERVER['HTTP_USER_AGENT'])||preg_match('/iPad/i',$_SERVER['HTTP_USER_AGENT'])||preg_match('/ipod/i',$_SERVER['HTTP_USER_AGENT'])||preg_match('/android/i',$_SERVER['HTTP_USER_AGENT'])||preg_match('/webOS/i',$_SERVER['HTTP_USER_AGENT'])){
	return true;
  }
  return false;
}

if(detect_iphone())
	define('IPHONE',1);
else
	define('IPHONE',0);

////////////////////////////
// strip epesi invalid html code

class EpesiHTML {
	public static function startElement($parser, $name, $attrs) {
		if($name=='EPESI') return;
		$r_attrs = '';
		foreach($attrs as $k=>$v) {
			if(preg_match('/(id|onMouseMove|onMouseUp|onMouseOut|onKeyPress|onLoad|onClick|onMouseOver)/i',$k) || ($name=='A' && strcasecmp($k,'href')==0 && strncasecmp($v,'javascript:',11)==0)) continue;
			$r_attrs .= ' '.$k.'="'.str_replace(array('"','\\'),array('\"','\\\\'),$v).'"';
		}
		echo '<'.$name.$r_attrs.'>';
	}

	public static function endElement($parser, $name) {
		if($name=='EPESI' || $name=='BR') return;
		echo "</$name>";
	}
	
	public static function characterData($parser, $data) {
		echo $data;
	}

	public static function parse($val,$return_error=false) {
		$xml_parser = xml_parser_create('UTF-8');
		xml_parser_set_option( $xml_parser, XML_OPTION_CASE_FOLDING, 1 );
		xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_set_element_handler($xml_parser, array('EpesiHTML',"startElement"), array('EpesiHTML',"endElement"));
		xml_set_character_data_handler($xml_parser, array('EpesiHTML',"characterData"));

		$val = '<?xml version="1.0" standalone="yes" ?>
<!DOCTYPE epesi_note [
<!ENTITY nbsp   "&#160;">
<!ENTITY iexcl  "&#161;">
<!ENTITY cent   "&#162;">
<!ENTITY pound  "&#163;">
<!ENTITY curren "&#164;">
<!ENTITY yen    "&#165;">
<!ENTITY brvbar "&#166;">
<!ENTITY sect   "&#167;">
<!ENTITY uml    "&#168;">
<!ENTITY copy   "&#169;">
<!ENTITY ordf   "&#170;">
<!ENTITY laquo  "&#171;">
<!ENTITY not    "&#172;">
<!ENTITY shy    "&#173;">
<!ENTITY reg    "&#174;">
<!ENTITY macr   "&#175;">
<!ENTITY deg    "&#176;">
<!ENTITY plusmn "&#177;">
<!ENTITY sup2   "&#178;">
<!ENTITY sup3   "&#179;">
<!ENTITY acute  "&#180;">
<!ENTITY micro  "&#181;">
<!ENTITY para   "&#182;">
<!ENTITY middot "&#183;">
<!ENTITY cedil  "&#184;">
<!ENTITY sup1   "&#185;">
<!ENTITY ordm   "&#186;">
<!ENTITY raquo  "&#187;">
<!ENTITY frac14 "&#188;">
<!ENTITY frac12 "&#189;">
<!ENTITY frac34 "&#190;">
<!ENTITY iquest "&#191;">
<!ENTITY Agrave "&#192;">
<!ENTITY Aacute "&#193;">
<!ENTITY Acirc  "&#194;">
<!ENTITY Atilde "&#195;">
<!ENTITY Auml   "&#196;">
<!ENTITY Aring  "&#197;">
<!ENTITY AElig  "&#198;">
<!ENTITY Ccedil "&#199;">
<!ENTITY Egrave "&#200;">
<!ENTITY Eacute "&#201;">
<!ENTITY Ecirc  "&#202;">
<!ENTITY Euml   "&#203;">
<!ENTITY Igrave "&#204;">
<!ENTITY Iacute "&#205;">
<!ENTITY Icirc  "&#206;">
<!ENTITY Iuml   "&#207;">
<!ENTITY ETH    "&#208;">
<!ENTITY Ntilde "&#209;">
<!ENTITY Ograve "&#210;">
<!ENTITY Oacute "&#211;">
<!ENTITY Ocirc  "&#212;">
<!ENTITY Otilde "&#213;">
<!ENTITY Ouml   "&#214;">
<!ENTITY times  "&#215;">
<!ENTITY Oslash "&#216;">
<!ENTITY Ugrave "&#217;">
<!ENTITY Uacute "&#218;">
<!ENTITY Ucirc  "&#219;">
<!ENTITY Uuml   "&#220;">
<!ENTITY Yacute "&#221;">
<!ENTITY THORN  "&#222;">
<!ENTITY szlig  "&#223;">
<!ENTITY agrave "&#224;">
<!ENTITY aacute "&#225;">
<!ENTITY acirc  "&#226;">
<!ENTITY atilde "&#227;">
<!ENTITY auml   "&#228;">
<!ENTITY aring  "&#229;">
<!ENTITY aelig  "&#230;">
<!ENTITY ccedil "&#231;">
<!ENTITY egrave "&#232;">
<!ENTITY eacute "&#233;">
<!ENTITY ecirc  "&#234;">
<!ENTITY euml   "&#235;">
<!ENTITY igrave "&#236;">
<!ENTITY iacute "&#237;">
<!ENTITY icirc  "&#238;">
<!ENTITY iuml   "&#239;">
<!ENTITY eth    "&#240;">
<!ENTITY ntilde "&#241;">
<!ENTITY ograve "&#242;">
<!ENTITY oacute "&#243;">
<!ENTITY ocirc  "&#244;">
<!ENTITY otilde "&#245;">
<!ENTITY ouml   "&#246;">
<!ENTITY divide "&#247;">
<!ENTITY oslash "&#248;">
<!ENTITY ugrave "&#249;">
<!ENTITY uacute "&#250;">
<!ENTITY ucirc  "&#251;">
<!ENTITY uuml   "&#252;">
<!ENTITY yacute "&#253;">
<!ENTITY thorn  "&#254;">
<!ENTITY yuml   "&#255;">
]><epesi>'.$val.'</epesi>';
		ob_start();
		$ret_ok = true;
		if(!xml_parse($xml_parser, $val, true)) {
        	echo(sprintf("XML error: %s at line %d",
                    xml_error_string(xml_get_error_code($xml_parser)),
                    xml_get_current_line_number($xml_parser)));
			$ret_ok = false;
		}
		$ret = ob_get_clean();
		xml_parser_free($xml_parser);
		if(!$ret_ok) {
			if($return_error)
				return $ret;
			else
				return false;
		}
		return $ret;
	}
}

function curl_exec_follow($ch, $maxredirect = null) { 
	$mr = $maxredirect === null ? 5 : intval($maxredirect); 
	if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) { 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0); 
		curl_setopt($ch, CURLOPT_MAXREDIRS, $mr); 
	} else { 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
		if ($mr > 0) { 
			$newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); 

			$rch = curl_copy_handle($ch); 
			curl_setopt($rch, CURLOPT_HEADER, true); 
			curl_setopt($rch, CURLOPT_NOBODY, true); 
			curl_setopt($rch, CURLOPT_FORBID_REUSE, false); 
			curl_setopt($rch, CURLOPT_RETURNTRANSFER, true); 
			do { 
				curl_setopt($rch, CURLOPT_URL, $newurl); 
				$header = curl_exec($rch); 
				if (curl_errno($rch)) { 
					$code = 0; 
				} else { 
					$code = curl_getinfo($rch, CURLINFO_HTTP_CODE); 
					if ($code == 301 || $code == 302) { 
						preg_match('/Location:(.*?)\n/', $header, $matches); 
						$newurl = trim(array_pop($matches)); 
					} else { 
						$code = 0; 
					} 
				} 
			} while ($code && --$mr); 
			curl_close($rch); 
			if (!$mr) { 
				if ($maxredirect === null) { 
					trigger_error('Too many redirects. When following redirects, libcurl hit the maximum amount.', E_USER_WARNING); 
				} else { 
					$maxredirect = 0; 
				} 
				return false; 
			} 
			curl_setopt($ch, CURLOPT_URL, $newurl); 
		} 
	} 
	return curl_exec($ch); 
}

function get_function_caller($describe=true) {
	$trace = debug_backtrace(true, 3);

	if (!isset($trace[2])) return $describe? '': array();

	$caller = $trace[2];

	$ret = $caller;
	if ($describe) {
		$ret = '';
		if (isset($caller['class']))
			$ret .= $caller['class']. '::';

		if (isset($caller['function']))
			$ret .= $caller['function'];

		if (isset($caller['file']) && isset($caller['line']))
			$ret .= ", File '{$caller['file']}: {$caller['line']}";

		if (!empty($ret)) $ret = 'Called by ' . $ret;
	}

	return $ret;
}
