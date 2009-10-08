<?php
/**
 * Utils_Image.
 * It automates creating properly scaled image thumbnails. Works with most
 * popular image formats. Also adds a preloader for displayd images.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage image
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_ImageCommon extends ModuleCommon {

	/**
	 * Creates thumb of loaded image.
	 * 
	 * @param string path to image
	 * @param int max size.
	 * @param int max height. When specified, first parameter becomes responsible for max width.
	 */
	public static function create_thumb($img, $attr_x = null, $attr_y = null) {
		ini_set("gd.jpeg_ignore_warning", 1);
		
		if(!is_file($img)) {
			$img = Base_ThemeCommon::get_template_file('Utils/Image','error_image_not_found.gif');
		} 
		
		list($width,$height,$type,$attr) = getimagesize($img);
		
		$max_dim = 100;
		if( is_numeric($attr_x) )
			$max_dim = $attr_x;
		if( is_numeric($attr_y) && $width < $height )
			$max_dim = $attr_y;
			
		if($height > $max_dim || $width > $max_dim) {
			if($height < $width) {
				$thumb_width = $max_dim;
				$thumb_height = $height * ( $max_dim / $width );
			} else if($width < $height) {
				$thumb_width = $width * ( $max_dim / $height );
				$thumb_height = $max_dim;
			} else {
				$thumb_width = $max_dim;
				$thumb_height = $max_dim;
			}
		} else {
			$thumb_width = $width;
			$thumb_height = $height;
		}
		
		$thumb = md5($max_dim.$img).strrchr($img,'.');
	
		// check if thumbnail in desired scale already exists
		//  1) it does
		$thumb_real = ModuleManager::get_data_dir('Utils/Image').$thumb;
		if( is_file($thumb_real) && filemtime($img)<filemtime($thumb_real) && filectime($img)<filectime($thumb_real)) {
			//print ModuleManager::get_data_dir('Utils/Image').$thumb." exists<br>";
			list($thumb_width, $thumb_height, $type, $attr) = getimagesize($thumb_real);
		// 2) it does not
		} else { // create thumb
			//$old_err = error_reporting(0);
			//print "typ: ". $type;
			//print ModuleManager::get_data_dir('Utils/Image').$thumb." does not exist<br>";
			// if file is a jpeg graphic
			if( $type == 3 ) {
				$im = imagecreatefrompng($img); /* Attempt to open */
				if( $im ) {
					//constrain proportions if needed
					$t_im = imagecreatetruecolor($thumb_width, $thumb_height);
					
					
					//header("Content-type: image/png");
					//imagesavealpha($t_im, true);
					$background = imagecolorallocate($t_im, 0, 0, 0);
					ImageColorTransparent($t_im, $background); // make the new temp image all transparent
					imagealphablending($t_im, false); // turn off the alpha blending to keep the alpha channel
			 		imagesavealpha($t_im, true);

			 		imagecopyresampled($t_im, $im, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);
			
					imagepng($t_im, $thumb_real);
					imagecolordeallocate($t_im,$background);
					imagedestroy($t_im);
					imagedestroy($im);
					//print ModuleManager::get_data_dir('Utils/Image').$thumb." created<br>";
				}
			} elseif( $type == 2 ) {
				$im = imagecreatefromjpeg($img); /* Attempt to open */
				if( $im ) {
					//constrain proportions if needed
					$t_im = imagecreatetruecolor($thumb_width, $thumb_height);
			
					imagecopyresampled($t_im, $im, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height);
			
					imagejpeg($t_im, $thumb_real, 90);
					imagedestroy($im);
					imagedestroy($t_im);
					//print ModuleManager::get_data_dir('Utils/Image').$thumb." created<br>";
				}
			} elseif( $type == 1 ) {
				$im = imagecreatefromgif($img);
				if( $im ) {
					$imgSource = $im;
					$imgDestination1 = imagecreatetruecolor($thumb_width, $thumb_height); 
					
					$black = imagecolorallocate($imgDestination1, 0, 0, 1); 
					
					imagefill($imgDestination1, 0, 0, $black); 
					imagecolortransparent($imgDestination1, $black); 
					
					imagecopyresampled($imgDestination1, $imgSource, 0, 0, 0, 0, $thumb_width, $thumb_height, $width, $height); 
					
					imagetruecolortopalette($imgDestination1, true, 256); 
					
					imagegif($imgDestination1, $thumb_real); 
					imagecolordeallocate($imgDestination1,$black);
					imagedestroy($imgDestination1);
					imagedestroy($im);
				}
			}
			//error_reporting($old_err);
		}
		return array('thumb'=>$thumb_real,'width'=>$thumb_width, 'height'=>$thumb_height, 'type'=>$type, 'attrs'=>$attr);
	}
	
	/**
	 * This returns HTML of created thumb.
	 */
	public static function get_thumb_html($img, $attr_x = null, $attr_y = null) {
		$x = self::create_thumb($img, $attr_x, $attr_y);
		$md = md5($x['thumb']);
		load_js("modules/Utils/Image/js/image.js");
		eval_js('utils_image_load_thumb(\''.$x['thumb'].'\', \''.$md.'\')');

		return '<img class="loader_'.$md.'" src="'.Base_ThemeCommon::get_template_file('Utils/Image','loader.gif').'">';
	}
	
	/**
	 * This displays created thumb.
	 */
	public static function display_thumb($img, $attr_x = null, $attr_y = null) {
		print self::get_thumb_html($img, $attr_x, $attr_y);
	}
	
}
?>
