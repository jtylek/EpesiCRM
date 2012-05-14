<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
function get_mime_type($filepath,$original) {
	//new method, but not compiled in by default
	if(extension_loaded('fileinfo')) {
        	$fff = new finfo(FILEINFO_MIME);
	        $ret = $fff->file($filepath);
        	unset($fff);
	        return $ret;
    	}

	//unix system
	$ret = 0;
	ob_start();
	@passthru("file -bi {$filepath}",$ret);
	$output = ob_get_clean();
	if($ret==0) {
		$output = explode("; ",$output);
		if ( is_array($output) ) {
        	$output = $output[0];
		}
		return $output;
	}

	//deprecated method
	if(function_exists('mime_content_type'))
        	return mime_content_type($filepath);

	preg_match("/\.(.*?)$/", $original, $m);
	if(!isset($m[1])) return "application/octet-stream";
	switch(strtolower($m[1])){
       // case "js": return "application/javascript";
       // case "json": return "application/json";
       case "jpg": case "jpeg": case "jpe": return "image/jpg";
	   case "xlsx": 
	   case "xls": return "application/vnd.ms-excel";
	   case "docx": 
	   case "doc": return "application/msword";
   	   case "pdf": return "application/pdf";
       case "png": case "gif": case "bmp": return "image/".strtolower($m[1]);
       // case "css": return "text/css";
       // case "xml": return "application/xml";
       case "html": case "htm": case "php": return "text/html";
       default:
           return "application/octet-stream";
   } 
}
?>
