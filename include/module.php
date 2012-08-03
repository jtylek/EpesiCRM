<?php
/**
 * Module file
 *
 * This file defines abstract class Module whose provides basic modules functionality.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license SPL
 * @version 1.0
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides some basic functionality for every epesi module.
 * @package epesi-base
 * @subpackage module
 */
abstract class Module extends ModulePrimitive {
	protected $parent = null;
	protected $children = array();
	private $jses = array();
	private $instance;
	private $children_count_display;
	private $type;
	private $path;
	private $reload = null;
	private $fast_process = false;
	private $frozen_modules = array();
	private $inline_display = false;
	private $displayed = false;
	private $clear_child_vars = false;
	public $display_func = false;

	/**
	 * Constructor. Should not be called directly using new Module('name').
	 * Use $this->pack_module or $this->init_module (inside other module).
	 *
	 * @param string module name
	 */
	public final function __construct($type,$parent,$name,$clear_vars) {
		parent::__construct($type);
		$this->type = $type;
		if($parent) {
			$this->parent = & $parent;
			if(isset($name))
				$this->instance = (string)$name;
			else
				$this->instance = $parent->get_new_child_instance_id($type);
			$parent->register_child($this);
		} elseif(isset($name))
			$this->instance = (string)$name;
		$this->path = null;
		if(!isset($this->instance)) throw new Exception('No instance name or parent specified.');
		$this->children_count_display = 0;
		if($clear_vars) {
			$this->clear_module_variables();
			$this->clear_child_vars = true;
		}
	}

	private final function register_child($ch) {
		$type = $ch->get_type();
		$instance = $ch->get_instance_id();
		if(!isset($this->children[$type]))
			$this->children[$type] = array();
		$this->children[$type][$instance] = & $ch;
		if(DEBUG)
			Epesi::debug('registering '.$this->get_path().'/'.$type.'|'.$instance);
	}

	private final function get_new_child_instance_id($type) {
		return isset($this->children[$type])?count($this->children[$type]):0;
	}

	/**
	 * Gets child module with specified node id.
	 *
	 * @param string $id
	 * @return module object
	 */
	public final function & get_child($id) {
		if($this->fast_process || isset($this->frozen_modules[$id])) {
			$x = false;
			return $x;
		}
		$yy = explode('|',$id);
		return $this->children[$yy[0]][$yy[1]];
	}

	/**
	 * Gets array of children modules.
	 *
	 * @return array node id is a key, module object is value
	 */
	public final function & get_children() {
		if($this->fast_process) return false;
		$ret = array();
		foreach($this->children as $type=>$xx)
			foreach($xx as $inst=>$obj)
					$ret[$obj->get_node_id()] = & $obj;
		return $ret;
	}

	/**
	 * Returns unique path of parent module.
	 * Path contains modules hierarchy information (parent of parent etc.) for the current module.
	 * Each module in the path is described as name and instance id.
	 *
	 * @return string
	 */
	public final function get_parent_path() {
		if($this->parent)
			return $this->parent->get_path();
		return false;
	}

	/**
	 * Get node identifier.
	 *
	 * @return string
	 */
	public final function get_node_id() {
		return $this->type.'|'.$this->instance;
	}

	/**
	 * Returns unique path of calling module.
	 *
	 * Path contains modules hierarchy information (parent of parent etc.) for current module.
	 * Each module in the path is described as name and instance id.
	 *
	 * Example:
	 * Module named Base/Box, instance 1, without parents:
	 * get_path returns '/Base_Box|1'
	 *
	 * @return string unique module name
	 */
	public final function get_path() {
		if(!isset($this->path))
			$this->path = $this->get_parent_path().'/'.$this->get_node_id();
		return $this->path;
	}

	/**
	 * Sets variable that will be available only for module instance that called this function.
	 * Note that after page refresh, this variable will preserve its value in contrary to module field variables.
	 * Module variables are hold separately for every client.
	 *
	 * @param string $name key
	 * @param mixed $value value
     * @return mixed variable value
	 */
	public final function set_module_variable($name, $value) {
		return $_SESSION['client']['__module_vars__'][$this->get_path()][$name] = $value;
	}

	/**
	 * Sets variable that will be available only for module instance that called this function.
	 * Note that after page refresh, this variable will preserve its value in contrary to module field variables.
	 * Module variables are hold separately for every client.
	 *
	 * @param string $path module path (returned by get_path() method);
	 * @param string $name key
     * @param mixed $value variable value
	 * @return mixed variable value
	 */
	public final static function static_set_module_variable($path,$name, $value) {
		return $_SESSION['client']['__module_vars__'][$path][$name] = $value;
	}


	/**
	 * Returns value of a module variable. If the variable is not set, function will return value given as second parameter.
	 * For details concerning module variables, see set_module_variable.
	 *
	 * @param string $name key
	 * @param mixed $default default value
	 * @return mixed value
	 */
	public final function & get_module_variable($name, $default=null) {
		$path = $this->get_path();
		if(isset($default) && !$this->isset_module_variable($name))
			$_SESSION['client']['__module_vars__'][$path][$name] = & $default;
		return $_SESSION['client']['__module_vars__'][$path][$name];
	}


	/**
	 * Returns value of a module variable. If the variable is not set, function will return value given as third parameter.
	 * For details concerning module variables, see set_module_variable.
	 *
	 * @param string $path module path (returned by get_path() method);
	 * @param string $name key
	 * @param mixed $default default value
	 * @return mixed value
	 */
	public final static function & static_get_module_variable($path, $name, $default=null) {
		if(isset($default) && !self::static_isset_module_variable($path,$name))
			$_SESSION['client']['__module_vars__'][$path][$name] = & $default;
		return $_SESSION['client']['__module_vars__'][$path][$name];
	}

	/**
	 * Returns href variable.
	 *
	 * If unique href variable, given as first parameter, is not set, function will try to return value of module variable by that same name.
	 * If module variable, given as first parameter, is not set, function will return default value given as second parameter.
	 *
 	 * For details concerning href variables, see create_href.
	 * For details concerning module variables, see set_module_variable.
	 *
	 * @param string $name variable name
     * @param mixed $default_value default value
	 * @return mixed
	 */
	public final function & get_module_variable_or_unique_href_variable($name, $default_value=null) {
		$rid = $this->get_module_variable($name, $default_value);
		if($this->isset_unique_href_variable($name))
			$rid = $this->get_unique_href_variable($name);
		if(isset($rid))
			$this->set_module_variable($name, $rid);
		return $rid;
	}

	/**
	 * Checks if variable exists.
	 * For details concerning module variables, see set_module_variable.
	 *
	 * @param string $name key
	 * @return bool true if variable exists, false otherwise
	 */
	public final function isset_module_variable($name) {
		return isset($_SESSION['client']['__module_vars__'][$this->get_path()][$name]);
	}

	/**
	 * Checks if variable exists.
	 * For details concerning module variables, see set_module_variable.
	 *
	 * @param string $path module path (returned by get_path() method);
	 * @param string $name key
	 * @return bool true if variable exists, false otherwise
	 */
	public final static function static_isset_module_variable($path,$name) {
		return isset($_SESSION['client']['__module_vars__'][$path][$name]);
	}

	/**
	 * Unset module variable.
	 * For details concerning module variables see set_module_variable.
	 *
	 * @param string $name key
	 */
	public final function unset_module_variable($name) {
		if(!isset($name)) trigger_error('unset_module_variable needs one argument',E_USER_ERROR);
		unset($_SESSION['client']['__module_vars__'][$this->get_path()][$name]);
	}

	public final static function static_unset_module_variable($path,$name) {
		unset($_SESSION['client']['__module_vars__'][$path][$name]);
	}

	/**
	 * Unset all module variables.
	 * For details concerning module variables see set_module_variable.
	 */
	public final function clear_module_variables() {
		unset($_SESSION['client']['__module_vars__'][$this->get_path()]);
	}

	/**
	 * Share variable passed as first parameter with module passed as second parameter.
	 * Any change of this variable will be visible in both modules.
	 *
	 * @param string $name varaible name
	 * @param object $m module object
	 * @return bool false if module is invalid, true otherwise
	 */
	public final function share_module_variable($name, & $m, $name2=null) {
		if(!is_a($m, 'Module'))
			return false;

		if(!isset($name2)) $name2=$name;
		$_SESSION['client']['__module_vars__'][$m->get_path()][$name2] = & $_SESSION['client']['__module_vars__'][$this->get_path()][$name];
		return true;
	}


	/**
	 * Share href variable passed as first parameter with module passed as second parameter.
	 * Any change of this variable will be visible in both modules.
	 *
	 * @param string $name href variable name
	 * @param object $m module object
     * @param string $name2
	 * @return bool false if module is invalid, true otherwise
	 */
	public function share_unique_href_variable($name, & $m, $name2=null) {
		if(!is_a($m, 'Module'))
			return false;

		if(!isset($name2)) $name2=$name;

		$s = & $m->get_module_variable('__shared_unique_vars__',array());
		$s[$name2] = $this->create_unique_key($name);
		return true;
	}

	/**
	 * Mark module to force its reload or prevent being reloaded.
	 * If this method is not called, module is reloaded by default,
	 * which means that only if output changed reload proceeds.
	 *
	 * @param bool $b true to force reload of whole module, false to suspend reloading
	 */
	 public final function set_reload($b) {
	 	if($this->reload==true) return;
	 	$this->reload = $b;
	 }

	 /**
	  * Returns current reload settings.
	  *
	  * @return bool true - force reload, false - no reload, null - default (reload changes only if module output changed)
	  */
	 public final function get_reload() {
	 	return $this->reload;
	 }

	/**
	 * Create onClick action string destined for js code.
	 * Use variables passed as first parameter, to generate variables accessible by $_REQUEST array.
	 *
	 * <xmp>
	 * print('<a '.$this->create_href(array('somekey'=>'somevalue'))).'>Link</a>');
	 * </xmp>
	 *
	 * @param array $variables variables to pass along with href
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final static function create_href_js(array $variables = array (), $indicator=null, $mode=null) {
		$ret = http_build_query($variables);
		if(!isset($indicator)) $indicator='';
		return '_chj(\''.$ret.'\', \''.addslashes($indicator).'\', \''.$mode.'\');';
	}

	/**
	 * Create onClick action string (with href="javascript:void(0);").
	 * Use variables passed as first parameter, to generate variables accessible by $_REQUEST array.
	 *
	 * <xmp>
	 * print('<a '.$this->create_href(array('somekey'=>'somevalue'))).'>Link</a>');
	 * </xmp>
	 *
	 * @param array $variables variables to pass along with href
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final static function create_href(array $variables = array (),$indicator=null, $mode=null) {
		return ' href="javascript:void(0)" onClick="'.self::create_href_js($variables,$indicator,$mode).'" ';
	}

	/**
	 * Create onClick action string (with href="javascript:void(0);").
	 * Use variables passed as first parameter, to generate variables accessible by $_REQUEST array.
	 * This function will trigger js confirm dialog before launching processing.
	 * If cancelled, no processing will be done.
	 *
	 * <xmp>
	 * print('<a '.$this->create_href(array('somekey'=>'somevalue'))).'>Link</a>');
	 * </xmp>
	 *
	 * @param string $confirm question displayed in confirmation box
	 * @param array $variables variables to pass along with href
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final static function create_confirm_href($confirm, array $variables = array (), $indicator=null, $mode=null) {
		return ' href="javascript:void(0)" onClick="if(confirm(\''.addslashes($confirm).'\')) {'.self::create_href_js($variables,$indicator,$mode).'}"';
	}

	/**
	 * Similar to create_href, but variables passed to this function will only be accessible in module that called this function.
	 * Those variables can be accessed with get_unique_href_variable.
	 *
	 * @param array $variables variables to pass along with href
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final function create_unique_href(array $variables = array (),$indicator=null,$mode=null) {
		$uvars = array('__action_module__'=>$this->get_path());
		foreach ($variables as $a => $b)
			$uvars[$this->create_unique_key($a)] = $b;
		return $this->create_href($uvars,$indicator,$mode);
	}
	/**
	 * Create onClick action string destined for js code.
	 * Similar to create_href, but variables passed to this function will only be accessible in module that called this function.
	 * Those variables can be accessed with get_unique_href_variable.
	 *
	 * @param array $variables variables to pass along with href
	 * @param string $indicator status bar indicator text
 	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final function create_unique_href_js(array $variables = array (),$indicator=null,$mode=null) {
		$uvars = array('__action_module__'=>$this->get_path());
		foreach ($variables as $a => $b)
			$uvars[$this->create_unique_key($a)] = $b;
		return $this->create_href_js($uvars,$indicator,$mode);
	}
	/**
	 * Similar to create_href, but variables passed to this function will only be accessible in module that called this function.
	 * Those variables can be accessed with get_unique_href_variable.
	 * This function will trigger js confirm dialog before launching processing.
	 * If cancelled, no processing will be done.
	 *
	 * @param string $confirm question displayed in confirmation box
	 * @param array $variables variables to pass along with href
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final function create_confirm_unique_href($confirm,array $variables = array (),$indicator=null,$mode=null) {
		$uvars = array('__action_module__'=>$this->get_path());
		foreach ($variables as $a => $b)
			$uvars[$this->create_unique_key($a)] = $b;
		return $this->create_confirm_href($confirm, $uvars,$indicator,$mode);
	}

	/**
	 * Returns variable passed with create_unique_href.
	 *
	 * @param string $key key
	 * @return mixed value
	 */
	public final function get_unique_href_variable($key) {
		$rkey = $this->create_unique_key($key);
		if(isset($_REQUEST[$rkey])) return $_REQUEST[$rkey];
		return null;
	}

	/**
	 * Unsets *unique_href variable.
	 *
	 * @param string $key key
	 */
	public final function unset_unique_href_variable($key) {
		$rkey = $this->create_unique_key($key);
		if(isset($_REQUEST[$rkey])) unset($_REQUEST[$rkey]);
	}

	/**
	 * Checks if variable given as first parameter was passed with create_unique_href function.
	 *
	 * @param string $key key
	 * @return bool true if variable was declared, false otherwise
	 */
	public final function isset_unique_href_variable($key) {
		$rkey = $this->create_unique_key($key);
		return isset($_REQUEST[$rkey]);
	}
	
	private final function create_callback_name($func, $args) {
		if(is_string($func))
			return md5(serialize(array($func,$args)));
		if(!is_array($func) || count($func)!=2)
			trigger_error('Invalid function passed to create_callback_{*}.',E_USER_ERROR);
		if(is_string($func[0]))
			return md5(serialize(array($func,$args)));
		if(!($func[0] instanceof Module))
			trigger_error('Invalid function passed to create_callback_{*}.',E_USER_ERROR);
		return md5(serialize(array(array($func[0]->get_path(),$func[1]),$args)));
	}

	/**
	 * Creates link similar to link created with create_href.
	 *
	 * The link, when used, will lead to calling of function which name is given as first parameter.
	 * Callback returns true if you use this link again after page refresh.
	 *
	 * WARNING: id of callback is generated using arguments passed to this function, so if you want to create
	 * callback that run on every page reload, with different arguments, use create_callback_href_with_id
	 *
	 * @param mixed $func function
	 * @param mixed $args arguments
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final function create_callback_href($func,$args=array(),$indicator=null,$mode=null) {
		$name = $this->create_callback_name($func,$args);
		return $this->create_callback_href_with_id($name,$func,$args,$indicator,$mode);
	}

	/**
	 * Creates link similar to link created with create_href.
	 *
	 * The link, when used, will lead to calling of function which name is given as first parameter.
	 * Callback returns true if you use this link again after page refresh.
	 *
	 * WARNING: id of callback is generated using arguments passed to this function, so if you want to create
	 * callback that run on every page reload, with different arguments, use create_callback_href_with_id
	 *
	 * @param mixed $func function
	 * @param mixed $args arguments
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final function create_callback_href_js($func,$args=array(),$indicator=null,$mode=null) {
		$name = $this->create_callback_name($func,$args);
		return $this->create_callback_href_with_id_js($name,$func,$args,$indicator,$mode);
	}

	public final function call_callback_href($func,$args=array()) {
		$name = 'callback_'.$this->create_callback_name($func,$args);
		$this->set_callback($name,$func,$args);
		location(array($this->create_unique_key($name)=>1));
	}

	/**
	 * Creates link similar to link created with create_href.
	 *
	 * The link, when used, will lead to calling of function which name is given as first parameter.
	 * Callback returns true if you use this link again after page refresh.
	 *
	 * This function will trigger js confirm dialog before launching processing.
	 * If cancelled, no processing will be done.
	 *
	 * WARNING: id of callback is generated using arguments passed to this function, so if you want to create
	 * callback that run on every page reload, with different arguments, use create_callback_href_with_id
	 *
	 * @param string $confirm question displayed in confirmation box
	 * @param mixed $func function
	 * @param mixed $args arguments
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string href string
	 */
	public final function create_confirm_callback_href($confirm, $func, $args=array(),$indicator=null,$mode=null) {
		$name = $this->create_callback_name($func,$args);
		return $this->create_confirm_callback_href_with_id($name, $confirm, $func,$args,$indicator,$mode);
	}

	private final function set_callback($name,$func,$args) {
		if(!is_string($func)) {
			if(is_array($func) && count($func)==2 && is_string($func[1]) &&
				(is_string($func[0]) || $func[0] instanceof Module)) {
					if(!is_callable($func))
						trigger_error('Callback not callable: '.print_r($func,true),E_USER_ERROR);
					if(!is_string($func[0])) $func[0]=null;
			} else
				trigger_error('Invalid callback function', E_USER_ERROR);
		}
		if(!is_array($args)) $args = array($args);
		$callbacks = & $this->get_module_variable('__callbacks__',array());
		if(isset($callbacks[$name])) unset($callbacks[$name]); //it is required, because we need to place [$name] at the end of array
		$callbacks[$name] = array('func'=>$func,'args'=>$args);
	}

	/**
	 * Creates link similar to links created with create_href.
	 *
	 * The link, when used, will lead to calling of function which name is given as first parameter.
	 * Callback returns true if you use this link again after page refresh.
	 *
	 * @param string $name callback id (name)
	 * @param mixed $func function
	 * @param mixed $args arguments
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string
	 */
	public final function create_callback_href_with_id($name, $func, $args=array(),$indicator=null,$mode=null) {
		$name = 'callback_'.$name;
		$this->set_callback($name,$func,$args);
		return $this->create_unique_href(array($name=>1),$indicator,$mode);
	}

	/**
	 * Creates link similar to links created with create_href.
	 *
	 * The link, when used, will lead to calling of function which name is given as first parameter.
	 * Callback returns true if you use this link again after page refresh.
	 *
	 * @param string $name callback id (name)
	 * @param mixed $func function
     * @param mixed $args function arguments
     * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string
	 */
	public final function create_callback_href_with_id_js($name, $func, $args=array(),$indicator=null,$mode=null) {
		$name = 'callback_'.$name;
		$this->set_callback($name,$func,$args);
		return $this->create_unique_href_js(array($name=>1),$indicator,$mode);
	}

	/**
	 * Creates link similar to links created with create_href.
	 *
	 * The link, when used, will lead to calling of function which name is given as first parameter.
	 * Callback returns true if you use this link again after page refresh.
	 *
	 * This function will trigger js confirm dialog before launching processing.
	 * If cancelled, no processing will be done.
	 *
	 * @param string $name callback id (name)
	 * @param string $confirm question displayed in confirmation box
	 * @param mixed $func function
	 * @param mixed $args arguments
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string
	 */
	public final function create_confirm_callback_href_with_id($name, $confirm, $func, $args=array(), $indicator=null,$mode=null) {
		$name = 'callback_'.$name;
		$this->set_callback($name,$func,$args);
		return $this->create_confirm_unique_href($confirm,array($name=>1),$indicator,$mode);
	}

	/**
	 * Creates link that will lead back to previous page content.
	 * Use is_back to check it was called.
	 *
	 * @param integer $i number of times isback() should return true after this link is used
	 * @param string $indicator status bar indicator text
	 * @param string $mode block, allow, queue click on simutanous click
	 * @return string string that should be placed inside html <pre><a></pre> tag. See create_href for example.
	 */
	public final function create_back_href($i=1,$indicator=null,$mode=null) {
		return $this->create_unique_href(array('back'=>$i));
	}

	public final function create_back_href_js($i=1,$indicator=null,$mode=null) {
		return $this->create_unique_href_js(array('back'=>$i));
	}

	/**
	 * Sets reload location to previous page display.
	 * Use is_back to control when this method was called.
	 */
	public final function set_back_location($i=1) {
		location(array($this->create_unique_key('back')=>$i,'__action_module__'=>$this->get_path()));
	}

	/**
	 * Checks if set_back_location was used.
	 *
	 * @return bool true if back link was used, false otherwise
	 */
	public final function is_back() {
		$rkey = $this->create_unique_key('back');
		if(isset($_REQUEST[$rkey])) {
			$i = intval($_REQUEST[$rkey]);
			if($this->display_func && $i>0) { //pass to parent only in main display method
				$pkey = $this->parent->create_unique_key('back');
				$_REQUEST[$pkey]=$i;
			}
			if($i<=0) return false;
			$i--;
			$_REQUEST[$rkey] = $i;
			return true;
		}
		return false;
	}

	/**
	 * Creates module instance which name is given as first parameter.
	 *
	 * Created module instance will be a child to the module which called this function.
	 *
	 * @param string $module_type module name
	 * @param mixed $args arguments for module constructor
	 * @param string $name unique name for the instance, will be assigned automatically by default
     * @param boolean $clear_vars clean module variables
	 * @return mixed if access denied returns null, else child module object
	 */
	public final function init_module($module_type, $args = null, $name=null,$clear_vars=false) {
		$module_type = str_replace('/','_',$module_type);
		$m = & ModuleManager::new_instance($module_type,$this,$name,($clear_vars || $this->clear_child_vars));

		if($args===null) $args = array();
		elseif(!is_array($args)) $args = array($args);

		if(method_exists($m,'construct')) {
			ob_start();
			call_user_func_array(array($m,'construct'),$args);
			ob_end_clean();
		}

		return $m;
	}

	/**
	 * Call method of the module passed as first parameter,
	 * which name is passed as third parameter.
	 * You can pass additional arguments.
	 *
	 * @param module $m child module
	 * @param mixed $args arguments
	 * @param string $function_name function to call (get output from), if user has enought privileges.
	 * @return mixed if access denied returns false, else true
	 */
	public final function display_module(& $m, $args=null, $function_name = null) {
		$ret = $this->get_html_of_module($m,$args,$function_name);
		if($ret===false) return false;
		print($ret);
		return true;
	}

	/**
	 * Call method of the module passed as first parameter,
	 * which name is passed as third parameter.
	 * You can pass additional arguments.
	 * Attention: do not pass the result of this function by one module to another module.
	 *
	 * @param module $m child module
	 * @param mixed $args arguments
	 * @param string $function_name function to call (get output from), if user has enought privileges.
	 * @return mixed if access denied returns false, else string
	 */
	public final function get_html_of_module(& $m, $args=null, $function_name = null) {
		$this_path = $this->get_path();

		if(!$m) trigger_error('Arument 0 for display_module is null.',E_USER_ERROR);
		if($this_path!=$m->get_parent_path()) return false;

		if(!isset($function_name)) $function_name = 'body';
		if (!method_exists($m, $function_name))
			trigger_error('Invalid method name ('.get_class($m).'::'.$function_name.') given as argument 2 for display_module.',E_USER_ERROR);

		if($m->displayed())
			trigger_error('You can\'t display the same module twice, path:'.$m->get_path().'.',E_USER_ERROR);

		if (!$m->check_access($function_name))
			return false;
			//we cannot trigger error here, couse logout doesn't work
			//trigger_error('Method given as argument 2 for display_module inaccessible.<br>$'.$this->get_type().'->display_module(\''.$m->get_type().'\','.$args.',\''.$function_name.'\');',E_USER_ERROR);

		$s = & $m->get_module_variable('__shared_unique_vars__',array());
		foreach($s as $k=>$v) {
			$_REQUEST[$m->create_unique_key($k)] = & $_REQUEST[$v];
		}

		if(MODULE_TIMES)
			$time = microtime(true);
		//define key in array so it is before its children
		$path = $m->get_path();
		if($this->is_inline_display()) $m->set_inline_display();
		if(!$m->is_inline_display()) {
			Epesi::$content[$path]['span'] = $this_path.'|'.$this->children_count_display.'content';
			$this->children_count_display++;
		}
		Epesi::$content[$path]['module'] = & $m;

		if(!REDUCING_TRANSFER || 
			(!$m->is_fast_process() || (isset($_REQUEST['__action_module__']) && strpos($_REQUEST['__action_module__'],$path)===0) || !isset($_SESSION['client']['__module_content__'][$path]))) {
			if($args===null) $args = array();
			elseif(!is_array($args)) $args = array($args);

			ob_start();

			$callbacks = array_reverse($m->get_module_variable('__callbacks__',array()),true);
			$skip_display = false;
			foreach($callbacks as $name=>$c) {
				$ret = $m->get_module_variable_or_unique_href_variable($name);
				if($ret=='1') {
					$func = $c['func'];
					if(is_array($func)) {
						if($func[0]===null)
							$func[0] = & $m;
						if(!method_exists($func[0],$func[1])) trigger_error('Invalid method passed as callback: '.(is_string($func[0])?$func[0]:$func[0]->get_type()).'::'.$func[1],E_USER_ERROR);
					}
					$r = call_user_func_array($func,$c['args']);
					if($r) {
						$skip_display = true;
						break;
					} else
						$m->unset_module_variable($name);
				}
			}

			if(!$skip_display) {
				$m->display_func=true;
				call_user_func_array(array($m,$function_name),$args);
				$m->display_func=false;
			}

			if(STRIP_OUTPUT) {
				require_once('libs/minify/Minify/HTML.php');
				Epesi::$content[$path]['value'] = Minify_HTML::minify(ob_get_contents());
			} else
				Epesi::$content[$path]['value'] = ob_get_contents();
			ob_end_clean();
			Epesi::$content[$path]['js'] = $m->get_jses();
		} else {
			Epesi::$content[$path]['value'] = $_SESSION['client']['__module_content__'][$path]['value'];
			Epesi::$content[$path]['js'] = $_SESSION['client']['__module_content__'][$path]['js'];
			if(DEBUG)
				Epesi::debug('Fast process of '.$path);
		}
		if(MODULE_TIMES)
			Epesi::$content[$path]['time'] = microtime(true)-$time;

		$m->mark_displayed();

		
		if($m->is_inline_display())
			return Epesi::$content[$path]['value'];
		return '<span id="'.Epesi::$content[$path]['span'].'"></span>';
	}

	/**
	 * Returns whether this module instance was already displayed.
	 *
	 * @return true if this module instance was aready displayed, false otherwise
	 */
	public final function displayed() {
		return $this->displayed===(isset($_REQUEST['__location'])?$_REQUEST['__location']:null);
	}

	/**
	 * Marks this module instance as it was displayed.
	 */
	public final function mark_displayed() {
		$this->displayed = isset($_REQUEST['__location'])?$_REQUEST['__location']:null;;
	}

	/**
	 * Returns whether this module instance has fast processing turned on.
	 *
	 * @return true if this module instance has fast processing turned on, false otherwise
	 */
	public final function is_fast_process() {
		return $this->fast_process;
	}

	/**
	 * Enable fast processing for this module instance.
	 */
	public final function set_fast_process() {
		$this->fast_process = true;
	}

	/**
	 * Returns whether this module instance is displayed inline.
	 *
	 * @return true if this module instance is displayed inline, false otherwise
	 */
	public final function is_inline_display() {
		return $this->inline_display || !REDUCING_TRANSFER;
	}

	/**
	 * Changes display behavior for this module instance to inline.
	 */
	public final function set_inline_display() {
		$this->inline_display = true;
	}

	/**
	 * Creates instance of module given as first parameter as a child of the module that has called this function.
	 * Also, this function will call newly created module's method, which name is passed as second parameter.
	 * You can pass additional arguments as next parameters.
	 *
	 * @param string $module_type child module name
     * @param mixed $display_args function_name arguments
	 * @param string $function_name function to call
     * @param mixed $construct_args module's construct arguments
     * @param string $name module name
	 * @return mixed if access denied returns null, otherwise returns child module object
	 */
	public final function pack_module($module_type, $display_args=null, $function_name = null, $construct_args=null, $name=null) {
		$m = $this->init_module($module_type,$construct_args,$name);
		$this->display_module($m, $display_args, $function_name);
		return $m;
	}

	/**
	 * Appends js code to list of jses to evaluate.
	 *
	 * @param string $js js code
	 */
	public final function js($js) {
		$this->jses[] = $js;
	}

	/**
	 * Returns list of jses to evaluate.
	 *
	 * @return array list of js commands
	 */
	public final function get_jses() {
		return $this->jses;
	}

	/**
	 * Returns name(type) of parent module.
	 *
	 * @return string module name
	 */
	public final function get_parent_type() {
		if($this->parent)
			return $this->parent->get_type();
		return false;
	}

	/**
	 * Returns id of module instance.
	 *
	 * @return mixed instance id
	 */
	public final function get_instance_id() {
		return $this->instance;
	}

	/**
	 * Returns unique key name, generated from unique name of this module (function get_path) and string parameter.
	 *
	 * This function is called inside create_unique_href function and should not be used directly.
	 *
	 * @param string $name
	 * @return string
	 */
	public final function create_unique_key($name) {
		return $this->get_path() . '_' . $name;
	}

	/**
	 * Makes child module to not loose its module variables
	 *
	 * @param string $module_type module
	 */
	public final function freeze_module($module_type,$name=null) {
		if($this->clear_child_vars) return;
		$module_type = str_replace('/','_',$module_type);
		if(!isset($name)) $name = $this->get_new_child_instance_id($module_type);
		$this->frozen_modules[$module_type.'|'.$name] = 1;
	}
	
	/////////////////////////
	// registered methods
	private static $registered_methods = array();
	
	public static function register_method($name, $func) {
		self::$registered_methods[$name] = $func;
	}
	
	public function & __call($func_name, array $args=array()) {
		if(isset(self::$registered_methods[$func_name]))
			$ret = call_user_func_array(self::$registered_methods[$func_name], array_merge(array($this),$args));
		else
			$ret = false;
		return $ret;
	}
}

?>
