<?php
/**
 * Theme class.
 * 
 * Provides module templating.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * load Smarty library
 */
 define(SMARTY_DIR, 'modules/Base/Theme/smarty/');
 
require_once(SMARTY_DIR.'Smarty.class.php');

/**
 * Provides module templating.
 * @package epesi-base-extra
 * @subpackage theme
 */
class Base_Theme extends Module {
	private static $theme;
	private static $themes_dir = 'data/Base/Theme/templates/';
	public $links = array();
	private $smarty = null;
	private $lang;
//	private static $root = null;
	
	public function construct() {
		$this->smarty = new Smarty();
		
		$this->set_inline_display();
		
//		if (!Base_Theme::$root) Base_Theme::$root = & $this; 
		
		if(!isset(self::$theme)) {
			self::$theme = Variable::get('default_theme');
			if(!is_dir(self::$themes_dir.self::$theme))
				self::$theme = 'default';
		}
				
		$this->smarty->template_dir = self::$themes_dir.self::$theme;
		$this->smarty->compile_dir = 'data/Base/Theme/compiled/';
		$this->smarty->compile_id = self::$theme;
		$this->smarty->config_dir = 'data/Base/Theme/config/';
		$this->smarty->cache_dir = 'data/Base/Theme/cache/';
	}
	
	public function body() {
	}

	public function toHtml($user_template) { // TODO: There have to be something more useful than ob_start()...
		ob_start();
		$this->display($user_template);
		$ret = ob_get_contents();
		ob_end_clean();
		return $ret;		
	}

	public function display($user_template,$fullname=false) {
		$this->smarty->assign('__link', $this->links);
		
		$module_name = $this->parent->get_type();
		if(isset($user_template)) { 
			if (!$fullname)
				$module_name .= '__'.$user_template;
			else 
				$module_name = $user_template;
		} else
			$module_name .= '__default';
		
		$tpl = $module_name.'.tpl';
		
		if($this->smarty->template_exists($tpl)) {
			$this->smarty->assign('theme_dir',$this->smarty->template_dir);
			$this->smarty->display($tpl);
			$css = $this->smarty->template_dir.'/'.$module_name.'.css';
			if(file_exists($css))
		    		load_css($css);
			
			//trigger_error($this->smarty->template_dir.$templ_name, E_USER_ERROR);
		} else {
			$this->smarty->template_dir = self::$themes_dir.'default';
			$this->smarty->compile_id = 'default';
			
			if(!$this->smarty->template_exists($tpl)) {
				trigger_error('Template not found: '.$tpl,E_USER_ERROR);
			}

			$this->smarty->assign('theme_dir',$this->smarty->template_dir);
			$this->smarty->display($tpl);
			$css = $this->smarty->template_dir.'/'.$module_name.'.css';
			if(file_exists($css))
				load_css($css);
			
			$this->smarty->template_dir = self::$themes_dir.self::$theme;
			$this->smarty->compile_id = self::$theme;
		}
		
/*		if (Base_Theme::$root === $this) {
			$this->precache_images($this->smarty->template_dir);
			//if (self::$theme !== 'default') $this->precache_images(self::$themes_dir.'default');
			// uncomment the line above to enable precaching of images from default theme
			Base_Theme::$root = null;
		}
*/		
	}

	public function assign_form($name, & $form){ 
		$renderer = & new HTML_QuickForm_Renderer_TCMSArraySmarty(); 
		$form->accept($renderer); 
		$form_data = $renderer->toArray();
		$this->assign($name.'_name', $form->getAttribute('name')); 
		$this->assign($name.'_data', $form_data);
		$this->assign($name.'_open', $form_data['javascript'].'<form '.$form_data['attributes'].'>'.$form_data['hidden']."\n");
		$this->assign($name.'_close', "</form>\n");
	} 
	
	private function precache_images($dir) {
		$content = scandir($dir);
		foreach ($content as $name){
			if ($name == '.' || $name == '..') continue;
			$file_name = $dir.'/'.$name;
			$ext = strtolower(substr(strrchr($file_name,'.'),1));
			if (is_dir($file_name)) {
				$this->precache_images($file_name);
			} else {
				if ($ext === 'jpg' ||
					$ext === 'jpeg' ||
					$ext === 'gif' ||
					$ext === 'png')
					print('<img style="display:none;" src="'.$file_name.'" />');
			}
		}
	}
	
	public function get_theme_path() {
		$module_name = $this->parent->get_type();
		return self::$themes_dir.'/'.self::$theme.'/'.$module_name.'__';
	}
	
	public function & get_smarty() {
		return $this->smarty;
	}
	
	public function parse_links($key, $val, $flat=true) {
		if (!is_array($val)) {
			$val = trim($val); 
			$i=0;
			$count=0;
			$open="";
			$text="";
			$close="";
			$len = strlen($val);
			if ($val{0}==='<') 
				while ($i<$len) {
					if ($val{$i}==='<') {
						if ($val{$i+1}==='a') {
							if ($count===0) {
								while ($val{$i}!=='>') {
									$open .= $val{$i};
									$i++;
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
	
	public function assign($name, $val) {
		$new_links = $this->parse_links($name, $val);
/*		$this->links[$name] = array_merge($this->links[$name],$new_links);
		print_r($val);
		print('<hr>');*/
		$this->links[$name] = $new_links;
		return $this->smarty->assign($name, $val);
	}
	
	public static function list_themes() {
		$themes = array();
		$inc = dir(self::$themes_dir);
		while (false != ($entry = $inc->read())) {
			if (is_dir(self::$themes_dir.$entry) && $entry!='.' && $entry!='..')
				$themes[$entry] = $entry;
		}
		asort($themes);
		return $themes;		
	}
}
?>
