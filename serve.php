<?php

/**
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

// serve
if (isset($_GET['f'])) {
    $filename = $_GET['f']; // remove any naughty bits
    $filenamePattern = '/[^\'"\\/\\\\]+\\.(?:' 
        .implode('|', $serveExtensions).   ')$/';
        
    if (preg_match($filenamePattern, $filename)
        && file_exists($filename)) {

  		
		ini_set('include_path','libs/minify'.PATH_SEPARATOR.'.'.PATH_SEPARATOR.ini_get('include_path'));
        require 'Minify.php';
        
		$cache_dir = 'data/minify_cache';
		if(!file_exists($cache_dir))
			mkdir($cache_dir);
        Minify::useServerCache($cache_dir);
        
        // The Files controller can serve an array of files, but here we just
        // need one.
        Minify::serve('Files', array(
            'files' => array($filename),
			'setExpires' => time() + 86400 * 365
        ));
        exit();
    }
}

header("HTTP/1.0 404 Not Found");
echo "HTTP/1.0 404 Not Found";