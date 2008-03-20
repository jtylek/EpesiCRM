<?php
function get_mime_type($filepath) {
	//new method, but not compiled in by default
	if(extension_loaded('fileinfo')) {
        	$fff = new finfo(FILEINFO_MIME);
	        $ret = $fff->file($filepath);
        	$fff->close();
	        return $ret;
    	}

	//deprecated method
	if(function_exists('mime_content_type'))
        	return mime_content_type($filepath);

	//unix system
	ob_start();
	passthru("file -i -b {$filepath}");
	$output = ob_get_clean();
	$output = explode("; ",$output);
	if ( is_array($output) ) {
        	$output = $output[0];
	}
	return $output;
}
?>