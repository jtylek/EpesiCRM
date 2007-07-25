<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Gallery extends Module {
	private $path;
	
	public function construct( $arg ) {
		$this->path = $arg;	
	}
		
	function getListing($p_dirpath, $pattern = '0') { 
		$r_ret  = array(); 
			if ( is_dir($p_dirpath) ) {
				if ($handle = opendir($p_dirpath)) { 
					while (false !== ($file = readdir($handle))) { 
						if ($file != "." && $file != ".." && $file !="CVS") { 
							if ( !is_dir($p_dirpath."/".$file) ) {
								if($pattern != '0') {
									if(preg_match($pattern, $file))
										array_push($r_ret, $file); 
								} else 
									array_push($r_ret, $file); 
							}
						} 
					} 
					closedir($handle); 
				} else { 
					return "Unable to open directory: " . $p_dirpath; 
				} 
			}
			return $r_ret; 
	}
	
	function getDirs($p_dirpath, $pattern = '0') { 
		$r_ret = array(); 
		if ( is_dir($p_dirpath) ) {
			if ($handle = opendir($p_dirpath)) { 
				while(false !== ($file = readdir($handle))) { 
					if($file != "." && $file != "..") { 
						if ( is_dir($p_dirpath."/".$file) ){
							if($pattern != '0') {
								if(preg_match($pattern, $file)) 
									array_push($r_ret, $file); 
							} else 
								array_push($r_ret, $file); 
						} 
					} 
				} 
				closedir($handle);
			} else 	{
				return "Unable to open directory: ./" . $p_dirpath; 
			} 
			return $r_ret; 
		}
	}
	
	public function set_directory( $dir ) {
		$this->path = $dir;
	}
	
	public function toHtml(  ) {
		load_js('modules/Utils/Gallery/js/gallery.js');
		$dir = $this->path;
		$img = $this->get_unique_href_variable('img', '');
		$slideshow = $this->get_unique_href_variable('slideshow', 'no');
		$theme = & $this->init_module('Base/Theme');
		
		if($img == '') {
				
			$ret = '';
			$image = & $this->init_module('Utils/Image');
			
			$images = $this->getListing("./".$dir, "/(\.png$)|(\.jpeg$)|(\.jpg$)|(\.gif$)/i");
			
			
			$image_list = array();
			for($i = 0; $i < count($images); $i++ ) {
				$image->load("./".$dir."/".$images[$i]);
				
				$image_list[$i] = array();
				$image_list[$i]['open_link'] = "<a ".$this->create_unique_href(array('img'=>$images[$i])).">";
				$image_list[$i]['img'] = $image->thumb_toHtml(120);
			
				$image_list[$i]['name'] = $images[$i];
				$image_list[$i]['close_link'] = "</a>";
			}
			$theme->assign('style', 'show_all');
			$theme->assign('image_list', $image_list);
			
			$ret = $this->get_html_of_module($theme);
			
			return $ret;
		} else {
			
			// SLIDESHOW
			if( $slideshow == 'yes' ) {
				print "<div id=gal_deb></div>";
				$ret = '';
				$image = & $this->init_module('Utils/Image');
				
				$images = $this->getListing("./".$dir, "/(\.png$)|(\.jpeg$)|(\.jpg$)|(\.gif$)/i");
				
				$image->load("./".$dir."/".$img); 
							
				$preview = array();
				$preview['open_link'] = '<a  href="'.$dir.'/'.$img.'" target="_blank">';
				$preview['img'] = $image->thumb_toHtml(600);
			
				$preview['name'] = $img;
				$preview['close_link'] = "</a>";
				
				$prev_img = '';
				$next_img = '';
				$buttonsHTML = array();
				$buttons_real = array();
				$img_index = 0;
				$c_images =  count($images);
				
				for($i = 0; $i < $c_images; $i++ ) {
					$image->load("./".$dir."/".$images[$i]); 
					$image->create_thumb(600);
					
					if($images[$i] == $img) {
						$img_index = $i;
					}
					$buttons_real[$i] = '"./'.$dir."/".$images[$i].'"';
					$buttonsHTML[$i] = '"'. htmlspecialchars($image->get_thumb_address("./".$dir."/".$images[$i], 600)) .'"';
					
				}
				
				$buttons = array();
				$buttons[] = '<span id=gallery_slideshow_prev>prev</span>';
				$buttons[] = '<span id=gallery_slideshow_auto>auto</span>';
				$buttons[] = '<span id=gallery_slideshow_speed>'.
					'<select id=utils_gallery_speed onchange=utils_gallery_set_speed()>'.
					'<option value=500>0.5 second</option>'.
					'<option value=1000>1 second</option>'.
					'<option value=3000>3 seconds</option>'.
					'<option value=5000>5 seconds</option>'.
					'</select></span>';
				$buttons[] = '<span id=gallery_slideshow_down>down</span>';
				$buttons[] = "<a class=utils_gallery_picture_link ".$this->create_unique_href(array('img'=>'')).">Close slideshow</a>";;
				$buttons[] = '<span id=gallery_slideshow_next>next</span>';
				
				
				
				$theme->assign('style', 'slideshow');
				$theme->assign('buttons', $buttons);
				$theme->assign('preview', '<img id=gallery_slideshow_image src=modules/Utils/Gallery/theme/loader.gif>');
				$buttonsHTML_js = '[ ' . join( ',', $buttonsHTML ) . ' ]';
				$buttons_real_js = '[ ' . join( ',', $buttons_real ) . ' ]';
				eval_js(
					"utils_gallery = function() {".
						"utils_gallery_set_data(".$buttonsHTML_js.");".
						"utils_gallery_set_real(".$buttons_real_js.");".
						"utils_gallery_show(".$img_index.");".
					"};"
				);
				eval_js(
					'wait_while_null( "utils_gallery_set_data", "utils_gallery()" );'
				);
				//'wait_while_null( "utils_gallery_set_data", "utils_gallery_set_data('.$buttonsHTML_js.')" );'
				return $this->get_html_of_module($theme);

			// PREVIEW
			} else {
				$ret = '';
				$image = & $this->init_module('Utils/Image');
				
				$images = $this->getListing("./".$dir, "/(\.png$)|(\.jpeg$)|(\.jpg$)|(\.gif$)/i");
				
				$image->load("./".$dir."/".$img); 
				
				$thumb = $image->create_thumb(650, 450);
				$preview = array();
				$tag = md5(date("HMs"));
				$preview['open_link'] = '<a href="'.$thumb.'" rel="lyteshow['.$tag.']">';
				$preview['img'] = $image->thumb_toHtml(350);
			
				$preview['name'] = $img;
				$preview['close_link'] = "</a>";
				
				$prev_img = '';
				$next_img = '';
				$image_list = array();
				
				$prev_list = '';
				$next_list = '';
				$current_part = &$prev_list;
				$c_images =  count($images);
				for($i = 0; $i < $c_images; $i++ ) {
					$image->load("./".$dir."/".$images[$i]); 
				
					$image_list[$i] = array();
					$image_list[$i]['open_link'] = '<a '.$this->create_unique_href(array('img'=>$images[$i])).'>';
					$image_list[$i]['img'] = $image->thumb_toHtml(120);
				
					$image_list[$i]['name'] = $images[$i];
					$image_list[$i]['close_link'] = "</a>";
					
					if($images[$i] == $img) {
						$current_part = &$next_list;
						if($i > 0) 
							$prev_img = $images[$i-1];
						if($i < $c_images - 1) 
							$next_img = $images[$i+1];
					} else {
						$thumb = $image->create_thumb(650, 450);
						$current_part .= '<a href="'.$thumb.'" rel="lyteshow['.$tag.']">XXX</a>';
					}
				}
				$buttons = array();
				if($prev_img != '') 	$buttons[] = '<a class=utils_gallery_picture_link '.$this->create_unique_href(array('img'=>$prev_img)).'>&lt; Prevoius</a> ';
				else					$buttons[] = '&lt; Prevoius';
				//$buttons[] = '<a class=utils_gallery_picture_link '.$this->create_unique_href(array('img'=>$img, 'slideshow'=>'yes')).'>Slideshow</a>';
				$buttons[] = '<a class=utils_gallery_picture_link href="'.$dir.'/'.$img.'" target="_blank">Download</a>';
				$buttons[] = "<a class=utils_gallery_picture_link ".$this->create_unique_href(array('img'=>'')).">Close preview</a>";
				if($next_img != '') 	$buttons[] = '<a class=utils_gallery_picture_link '.$this->create_unique_href(array('img'=>$next_img)).'>Next &gt;</a>';
				else					$buttons[] = 'Next &gt;';
				
				
				$theme->assign('style', 'preview');
				$theme->assign('image_list', $image_list);
				$theme->assign('prev_list', $prev_list);
				$theme->assign('next_list', $next_list);
				$theme->assign('buttons', $buttons);
				$theme->assign('preview', $preview);
				return $this->get_html_of_module($theme);
			}
		}
		
	}
	public function expand() {
		eval_js('wait_while_null("utils_gallery__set_content_height", "utils_gallery__set_content_height(\'utils_gallery__conteiner\')");');
	}
	
	public function body( $dir ) {
		$this->path = $dir;
		print $this->toHtml();
	}
}
?>
