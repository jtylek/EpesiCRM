<?php
/**
 * Utils_Image.
 * It automates creating properly scaled image thumbnails. Works with most
 * popular image formats. Also adds a preloader for displayd images.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 * @subpackage image
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Image extends Module {
	private $img = '';
	private $img_path = '';
	private $img_id;
	private static $img_counter = 0;
	private $thumb = '';
	private $width;
	private $height;
	private $type;
	private $thumb_width;
	private $thumb_height;
	private $left_caption = '';
	private $right_caption = '';
	private $max_dim = 100;
	private $theme;
	
	public function construct() {
		$this->theme = & $this->init_module('Base/Theme');
	}
	
	/**
	 * Here you set imege to operate on and some additional parameters.
	 * 
	 * @param string path to image.
	 * @param array options: 
	 * 	left_caption - text on the left of image
	 * 	right_caption - text on the right of image
	 * 	thumb_size - max size of created thumbnail
	 * 	thumb_size_x - max width of created thumbnail
	 * 	thumb_size_y - max height of created thumbnail
	 */
	public function load($img, array $attr = null) {
		// parse attributes
		if(!is_file($img)) {
			//print $img." -- no file<br>";
			$this->img = $this->theme->get_theme_path().'Utils_Image__error_image_not_found.gif';
			if(!is_file($this->img))
				$this->img = 'data/Base_Theme/templates/default/Utils_Image__error_image_not_found.gif';
			//print $this->img." -- error file<br>";
		} else {
			$this->img = $img;
		}
		
		$this->left_caption = '';
		$this->right_caption = '';
		$this->max_dim = 100;
		$this->thumb = '';
		
		$fony = array();
		list($this->width, $this->height, $this->type, $fony) = getimagesize($this->img);
		
		if( $attr ) {
			if( isset($attr['thumb_size']) && is_numeric($attr['thumb_size']) )
				$this->max_dim = $attr['thumb_size'];
			if( isset($attr['left_caption']) )
				$this->left_caption = htmlspecialchars( $attr['left_caption'] );
			if( isset($attr['right_caption']) )
				$this->right_caption = htmlspecialchars( $attr['right_caption'] );
			
			if( isset($attr['thumb_size_x']) )
				$this->max_dim = $attr_x;
			if( isset($attr['thumb_size_y']) && $this->width < $this->height )
				$this->max_dim = $attr_y;
		}
	}
	
	/**
	 * Creates thumb of loaded image.
	 * 
	 * @param int max size.
	 * @param int max height. When specified, first parameter becomes responsible for max width.
	 */
	public function create_thumb($attr_x = null, $attr_y = null) {
		$this->img_id = Utils_Image::$img_counter;
		Utils_Image::$img_counter++;
		if( is_int($attr_x) )
			$this->max_dim = $attr_x;
		if( is_int($attr_y) && $this->width < $this->height )
			$this->max_dim = $attr_y;
			
		if($this->height > $this->max_dim || $this->width > $this->max_dim) {
			if($this->height < $this->width) {
				$this->thumb_width = $this->max_dim;
				$this->thumb_height = $this->height * ( $this->max_dim / $this->width );
			} else if($this->width < $this->height) {
				$this->thumb_width = $this->width * ( $this->max_dim / $this->height );
				$this->thumb_height = $this->max_dim;
			} else {
				$this->thumb_width = $this->max_dim;
				$this->thumb_height = $this->max_dim;
			}
		} else {
			$this->thumb_width = $this->width;
			$this->thumb_height = $this->height;
		}
		
		$img_path = explode('/', str_replace("..", "UP", $this->img));
		$img_file = array_pop($img_path);
		if($img_path) {
			$this->thumb = join('/', $img_path) . '/' . $this->max_dim . '_' . $img_file;
		} else {
			$this->thumb = $this->max_dim . '_' . $img_file;
		}
		// check if thumbnail in desired scale already exists
		
		//  1) it does
		if( is_file($this->get_data_dir().$this->thumb) ) {
			//print $this->get_data_dir().$this->thumb." exists<br>";
			list($this->thumb_width, $this->thumb_height, $type, $attr) = getimagesize($this->get_data_dir().$this->thumb);
		// 2) it does not
		} else { // create thumb
			$path_till_now = '';
			foreach($img_path as $dir) {
				$path_till_now .= '/' . $dir;
				$mkdir = $this->get_data_dir() . $path_till_now;
				if(file_exists($mkdir) && is_dir($mkdir) && is_writable($mkdir) && is_readable($mkdir)) continue;
				mkdir($mkdir);
			}
			//print "typ: ". $this->type;
			//print $this->get_data_dir().$this->thumb." does not exist<br>";
			// if file is a jpeg graphic
			if( $this->type == 3 ) {
				$im = imagecreatefrompng($this->img); /* Attempt to open */
				if( $im ) {
					//constrain proportions if needed
					$t_im = imagecreatetruecolor($this->thumb_width, $this->thumb_height);
					
					
					//header("Content-type: image/png");
					//imagesavealpha($t_im, true);
					$background = imagecolorallocate($t_im, 0, 0, 0);
					ImageColorTransparent($t_im, $background); // make the new temp image all transparent
					imagealphablending($t_im, false); // turn off the alpha blending to keep the alpha channel
			 		imagesavealpha($t_im, true);

			 		imagecopyresampled($t_im, $im, 0, 0, 0, 0, $this->thumb_width, $this->thumb_height, $this->width, $this->height);
			
					imagepng($t_im, $this->get_data_dir().$this->thumb);
					imagecolordeallocate($t_im,$background);
					imagedestroy($t_im);
					imagedestroy($im);
					//print $this->get_data_dir().$this->thumb." created<br>";
				}
			} elseif( $this->type == 2 ) {
				$im = imagecreatefromjpeg($this->img); /* Attempt to open */
				if( $im ) {
					//constrain proportions if needed
					$t_im = imagecreatetruecolor($this->thumb_width, $this->thumb_height);
			
					imagecopyresampled($t_im, $im, 0, 0, 0, 0, $this->thumb_width, $this->thumb_height, $this->width, $this->height);
			
					header("Content-type: image/jpeg");
					
					imagejpeg($t_im, $this->get_data_dir().$this->thumb, 90);
					imagedestroy($im);
					imagedestroy($t_im);
					//print $this->get_data_dir().$this->thumb." created<br>";
				}
			} elseif( $this->type == 1 ) {
				$im = imagecreatefromgif($this->img);
				if( $im ) {
					$imgSource = $im;
					$imgDestination1 = imagecreatetruecolor($this->thumb_width, $this->thumb_height); 
					
					$black = imagecolorallocate($imgDestination1, 0, 0, 1); 
					
					imagefill($imgDestination1, 0, 0, $black); 
					imagecolortransparent($imgDestination1, $black); 
					
					imagecopyresampled($imgDestination1, $imgSource, 0, 0, 0, 0, $this->thumb_width, $this->thumb_height, $this->width, $this->height); 
					
					imagetruecolortopalette($imgDestination1, true, 256); 
					
					imagegif($imgDestination1, $this->get_data_dir().$this->thumb); 
					imagecolordeallocate($imgDestination1,$black);
					imagedestroy($imgDestination1);
					imagedestroy($im);
				}
			}
       	}
		load_js("modules/Utils/Image/js/image.js");
		return $this->get_data_dir().$this->thumb;
	}
	
	// THUMB ---------------------------------------------------
	/**
	 * This returns HTML of created thumb.
	 */
	public function thumb_toHtml($attr_x = null, $attr_y = null) {
		$this->img_id = Utils_Image::$img_counter;
		Utils_Image::$img_counter++;
		$this->create_thumb($attr_x, $attr_y);
		eval_js('wait_while_null( "load_thumb", "load_thumb(\''.$this->get_data_dir().$this->thumb.'\', '.$this->img_id.')" );');

		$ret = $this->left_caption.'<img id="img_'.$this->img_id.'" src="'.$this->theme->get_theme_path().'loader.gif">'.$this->right_caption;
		return $ret;
	}
	
	/**
	 * This displays created thumb.
	 */
	public function display_thumb($attr_x = null, $attr_y = null) {
		print $this->thumb_toHtml($attr_x, $attr_y);
	}
	
	// REGULAR ------------------------------------------------
	/**
	 * This returns HTML of original image.
	 */
	public function toHtml() {
		return '<img width="'.$this->width.'" height="'.$this->height.'" src="'.$this->img.'">';
	}
	
	/**
	 * This displays original image.
	 */
	public function display() {
		print '<img width="'.$this->width.'" height="'.$this->height.'" src="'.$this->img.'">';
	}
	
	// DEFAULT -----------------------------------------------
	/**
	 * This displays created thumb.
	 */
	public function body() {
		$this->display_thumb();
	}
	
	// ATTRIBUTES --------------------------------------------
	/**
	 * This returns array with image attributes (width, height, type, 'height="yyy" width="xxx"').
	 */
	public function get_attributes() {
		return getimagesize($this->img);
	}
	
	/**
	 * This returns associative array with thumbnail attributes (width, height, type).
	 */
	public function get_thumb_attributes() {
		return array(
			'width'=>$this->thumb_width, 
			'height'=>$this->thumb_height, 
			'type'=>$this->type);
	}
	
	/**
	 * This returns address of created thumbnail.
	 */
	public function get_thumb_path( $size, $path = '' ) {
		if($path === '') 
			$path = $this->img;
		$img_path = explode('/', str_replace("..", "UP", $path));
		$img_file = array_pop($img_path);
		if($img_path) {
			$ret = join('/', $img_path) . '/' . $size . '_' . $img_file;
		} else {
			$ret = $this->max_dim . '_' . $img_file;
		}
		return $this->get_data_dir().$ret;
	}
}
?>
