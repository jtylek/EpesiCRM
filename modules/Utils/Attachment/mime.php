<?php
function get_mime_type($filepath,$original) {
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
/*	ob_start();
	passthru("file -i -b {$filepath}");
	$output = ob_get_clean();
	$output = explode("; ",$output);
	if ( is_array($output) ) {
        	$output = $output[0];
	}
	return $output;
*/
	preg_match("/\.(.*?)$/", $original, $m);    # Get File extension for a better match
	switch(strtolower($m[1])){
       case "js": return "application/javascript";
       case "json": return "application/json";
       case "jpg": case "jpeg": case "jpe": return "image/jpg";
       case "png": case "gif": case "bmp": return "image/".strtolower($m[1]);
       case "css": return "text/css";
       case "xml": return "application/xml";
       case "html": case "htm": case "php": return "text/html";
       default:
           return "";
   } 
}
?>
