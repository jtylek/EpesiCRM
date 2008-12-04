<?php
/**
 * Theme class.
 *
 * Provides module templating.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage theme
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Provides module templating.
 */
class Base_Theme extends Module {
	private static $theme;
	private static $loaded_csses;
	public $links = array();
	private $smarty = null;
	private $lang;

	/**
	 * For internal use only.
	 */
	public function construct() {
		$this->set_inline_display();

		if(!isset(self::$theme))
			self::$theme = Base_ThemeCommon::get_default_template();

		$this->smarty = Base_ThemeCommon::init_smarty();

		$this->load_image_cache();
	}

	private function load_image_cache() {
		if(isset($_SESSION['client']['image_cache'])) return;
		$_SESSION['client']['image_cache']=true;
		$imgs = array();
		if(Variable::get('preload_image_cache_selected') && file_exists($this->get_data_dir().'templates/'.self::$theme.'/__cache.images'))
			$imgs = explode("\n",file_get_contents($this->get_data_dir().'templates/'.self::$theme.'/__cache.images'));
		if(Variable::get('preload_image_cache_default') && file_exists($this->get_data_dir().'templates/'.'default/__cache.images'))
			$imgs = array_merge($imgs,explode("\n",file_get_contents($this->get_data_dir().'templates/'.'default/__cache.images')));
		if(!empty($imgs))
			eval_js("var cache = document.createElement('div');".
			"cache.style.display='none';".
			"document.body.appendChild(cache);".
			"var current_image = null;".
			"var cache_pause = false;".
			"var images_list = Array('".implode("','",$imgs)."');".
			"cache_images = function() {".
				"if(!cache_pause && (current_image==null || current_image.complete)) {".
					"current_image = document.createElement('img');".
					"current_image.src = images_list.shift();".
					"cache.appendChild(current_image);".
				"}".
				"if(images_list.length)".
					"setTimeout('cache_images()',500);".
			"};".
			"cache_images();");
	}

	public function body() {
	}

	/**
	 * Displays gathered information using a .tpl file and .css file.
	 *
	 * @param string name of theme file to use (without extension)
	 * @param bool if set to true, module name will not be added to the filename and you should then pass a result of get_module_file_name() function as filename
	 */
	public function display($user_template=null,$fullname=false) {
		$this->smarty->assign('__link', $this->links);

		$module_name = $this->parent->get_type();

		Base_ThemeCommon::display_smarty($this->smarty,$module_name,$user_template,$fullname);
	}

	/**
	 * Returns instance of smarty object which is assigned to this Theme instance.
	 *
	 * @return mixed smarty object
	 */
	public function & get_smarty() {
		return $this->smarty;
	}

	/**
	 * For internal use only.
	 */
	public function parse_links($key, $val, $flat=true) {
		if (!is_array($val)) {
			$val = trim($val);
			$i=0;
			$count=0;
			$open="";
			$text="";
			$close="";
			$len = strlen($val);
			if ($len>2 && $val{0}==='<' && $val{1}==='a')
				while ($i<$len-1) {
					if ($val{$i}==='<') {
						if ($val{$i+1}==='a') {
							if ($count===0) {
								while ($i<$len-1 && $val{$i}!=='>') {
									$open .= $val{$i};
									$i++;
									if ($val{$i}==='"') {
										do {
											$open .= $val{$i};
											$i++;
										} while ($i<$len && $val{$i}!=='"');
									}
								}
								$open .= '>';
							} else $text .= $val{$i};
							$count++;
						} else if (substr($val,$i+1,3)==='/a>') {
							$count--;
							if ($count===0) {
								$close = '</a>';
								return array(	'open' => $open,
												'text' => $text,
												'close' => '</a>');
							} else $text .= $val{$i};
						} else $text .= $val{$i};
					} else $text .= $val{$i};
					$i++;
				}
			return array();
		} else {
			$result = array();
			foreach ($val as $k=>$v) {
				$result[$k] = $this->parse_links($k, $v, false);
			}
			return $result;
		}
	}

	/**
	 * Assigns text to a smarty variable.
	 * Also parses the text looking for a link tag and if one is found,
	 * creates additinal smarty variables holding open, label and close for found tag.
	 *
	 * @param string name for smarty variable
	 * @param string variable contents
	 */
	public function assign($name, $val) {
		$new_links = $this->parse_links($name, $val);
		$this->links[$name] = $new_links;
		$this->smarty->assign($name, $val);
	}

	/**
	 * Returns list of available themes.
	 *
	 * @param array list of available themes
	 */
	public static function list_themes() {
		$themes = array();
		$inc = dir('data/Base_Theme/templates/');
		while (false != ($entry = $inc->read())) {
			if (is_dir('data/Base_Theme/templates/'.$entry) && $entry!='.' && $entry!='..')
				$themes[$entry] = $entry;
		}
		asort($themes);
		return $themes;
	}
}
?>
