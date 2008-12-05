<?php
/**
 * Utils_Gallery
 * One-method module for displaying images from one directory.
 *
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage gallery
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Gallery extends Module {
	private $path;

	public function construct( $arg ) {
		$this->path = $arg;
	}

	private function getListing($p_dirpath, $pattern = '0') {
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

	private function getDirs($p_dirpath, $pattern = '0') {
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

	/**
	 * Sets directory, where images to show are located.
	 *
	 * @param string path to the images directory.
	 */
	public function set_directory( $dir ) {
		$this->path = $dir;
	}

	/**
	 * This methodreturns HTML code of the gallery.
	 */
	public function toHtml(  ) {
		$dir = $this->path;
		$img = $this->get_unique_href_variable('img', '');
		$slideshow = $this->get_unique_href_variable('slideshow', 'no');
		$theme = & $this->init_module('Base/Theme');

		if($img == '') {

			$ret = '';

			$images = $this->getListing("./".$dir, "/(\.png$)|(\.jpeg$)|(\.jpg$)|(\.gif$)/i");


			$image_list = array();
			for($i = 0; $i < count($images); $i++ ) {
				$image_list[$i] = array();
				$image_list[$i]['open_link'] = "<a ".$this->create_unique_href(array('img'=>$images[$i])).">";
				$image_list[$i]['img'] = Utils_ImageCommon::get_thumb_html("./".$dir.'/'.$images[$i],120);

				$image_list[$i]['name'] = $images[$i];
				$image_list[$i]['close_link'] = "</a>";
			}
			$theme->assign('style', 'show_all');
			$theme->assign('image_list', $image_list);

			$ret = $this->get_html_of_module($theme,null,'display');

			return $ret;
		} else {

			{
				$ret = '';
				$images = $this->getListing("./".$dir, "/(\.png$)|(\.jpeg$)|(\.jpg$)|(\.gif$)/i");

				$thumb = Utils_ImageCommon::create_thumb("./".$dir."/".$img,650, 450);
				$preview = array();
				$tag = md5(date("HMs"));
				$preview['open_link'] = '<a href="'.$thumb['thumb'].'" rel="lyteshow['.$tag.']">';
				$preview['img'] = Utils_ImageCommon::get_thumb_html("./".$dir."/".$img,350);

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
					$image_list[$i] = array();
					$image_list[$i]['open_link'] = '<a '.$this->create_unique_href(array('img'=>$images[$i])).'>';
					$image_list[$i]['img'] = Utils_ImageCommon::get_thumb_html("./".$dir."/".$images[$i],120);

					$image_list[$i]['name'] = $images[$i];
					$image_list[$i]['close_link'] = "</a>";

					if($images[$i] == $img) {
						$current_part = &$next_list;
						if($i > 0)
							$prev_img = $images[$i-1];
						if($i < $c_images - 1)
							$next_img = $images[$i+1];
					} else {
						$thumb = Utils_ImageCommon::create_thumb("./".$dir."/".$images[$i],650,450);
						$current_part .= '<a href="'.$thumb['thumb'].'" rel="lyteshow['.$tag.']">XXX</a>';
					}
				}
				$buttons = array();
				if($prev_img != '') 	$buttons[] = '<a class=utils_gallery_picture_link '.$this->create_unique_href(array('img'=>$prev_img)).'><img src="/images/prev.png"> Previous</a> ';
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
				return $this->get_html_of_module($theme,null,'display');
			}
		}

	}

	/**
	 * This method displays the gallery.
	 */
	public function body( $dir ) {
		$this->path = $dir;
		print $this->toHtml();
	}
}
?>
