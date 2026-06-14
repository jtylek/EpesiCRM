<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 *
 * This script will serve a single js/css file in this directory. Here we place
 * the front-end-controller logic in user code, then use the "Files" controller
 * to minify the file. Alternately, we could have created a custom controller
 * with the same logic and passed it to Minify::handleRequest().
 */

/**
 * The Files controller only "knows" HTML, CSS, and JS files. Other files
 * would only be trim()ed and sent as plain/text.
 */
$serveExtensions = array('css', 'js');

set_time_limit(0);
// serve
if (isset($_GET['f'])) {
    $filename = $_GET['f']; // remove any naughty bits
    $filenamePattern = '/[^\'"\\/\\\\]+\\.(?:' 
        .implode('|', $serveExtensions).')$/';
	if(is_string($filename))
	    $arr = explode(',',$filename);
	elseif(is_array($filename))
		$arr = array_values($filename);
		
	if(isset($arr)) {
		$arr2 = array();
		$this_file_dir_pattern = '/'.preg_quote(dirname(__FILE__),'/').'/i';
		foreach($arr as $k=>$v) {
	    	if (preg_match($filenamePattern, $v) &&
    	    	file_exists($v) && preg_match($this_file_dir_pattern,realpath($v)))
					$arr2[] = $v;
		}
	
		ini_set('include_path','libs/minify'.PATH_SEPARATOR.'.'.PATH_SEPARATOR.'libs'.PATH_SEPARATOR.ini_get('include_path'));
	    require 'Minify.php';
		
		define('_VALID_ACCESS',1);
		require_once('include/data_dir.php');
		require_once('include/config.php');
        
		$cache_dir = DATA_DIR.'/cache/minify';
		if(!file_exists($cache_dir))
			mkdir($cache_dir,0777,true);
		Minify::setCache($cache_dir);
        
		$opts = array(	'files' => $arr2,
						'maxAge' => 86400 * 365,
						'rewriteCssUris'=>false
				    );
		if (!MINIFY_ENCODE) {
			$opts['encodeOutput'] = false;
			$opts['encodeMethod'] = '';
		}
        if (!MINIFY_SOURCES) {
            $opts['minifiers'] = array(
                Minify::TYPE_CSS => '',
                Minify::TYPE_HTML => '',
                Minify::TYPE_JS => ''
            );
		}

        // The Files controller can serve an array of files, but here we just
		// need one.
		Minify::serve('Files', $opts);

    	exit();
	}
}

header("HTTP/1.0 404 Not Found");
echo "HTTP/1.0 404 Not Found";
?>