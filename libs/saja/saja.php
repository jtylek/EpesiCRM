<?php
/*
	Example Usage: (ASP-style PHP tags are used instead of PHP-style tags to avoid bad parsing in editors)
	page url: http://www.yoursite.com/index.php
	saja url: http://www.yoursite.com/path/to/saja.php.
	
	For full documentation see: http://saja.sourceforge.net/
	
	---------- <index.php> -----------
	<%
	include($_SERVER['DOCUMENT_ROOT'].'/path/to/saja.php');
	$saja = new saja;
	$saja->set_path('/path/to/');
	$saja->secure_http(); //uses session variables to encrypt HTTP data (optional)
	%>
	<div id=outputDiv>Some Text</div>
	<input type=text id=myInput>
	<button id=myButton onclick="<%=$saja->run("MyPhpFunction(myInput:value)->outputDiv:innerHTML");%>">do something</button>
	---------- </index.php> -----------

	---------- <saja.functions.php> -----------
	<%
	function MyPhpFunction($myInput)
	{
		echo "You typed: [$myInput]";
		$saja = new saja;
		$saja->hide('myInput');
		$saja->text('Done!','myButton:innerHTML');
		$saja->js("alert('Alert!')");
		return $saja->send();
	}
	%>
	---------- </saja.functions.php> -----------
*/
class saja {
	
	//configurable vars
	var $saja_path = '';								//default SAJA path - this can be set so you never have to call set_path() again
	var $saja_process_file = 'saja.functions.php';		//default process file to use
	var $saja_process_path = '';						//relative or full path to the directory that contains your process files (functions) i.e. "../myfunctions/", "/www/apache/htdocs/public/", etc.
	var $saja_process_class = 'myFunctions';			//default classname to use
	
	//leave these vars alone
	var $functionPadding = 15;							//pad functions names having less than this many characters in their name
	var $actions = array();
	var $salt;
	var $http_key;
	var $historyAvailable;
	
	function saja()
	{
		if(!session_id())
			session_start();
		$this->salt();
	}
	
	function clear_state(){
		unset($_SESSION['SAJA_SALT']);
		unset($_SESSION['SAJA_HTTP_KEY']);
		$this->salt = $this->http_key = null;
		$this->salt();
	}
	
	function salt(){
		$this->salt = $_SESSION['SAJA_SALT'] ? $_SESSION['SAJA_SALT'] : $this->generate_key();
		$_SESSION['SAJA_SALT'] = $this->salt;
	}
	
	function set_path($path)
	{
		$this->saja_path = $path;
	}
	
	function secure_http()
	{
		$this->http_key = $_SESSION['SAJA_HTTP_KEY'] ? $_SESSION['SAJA_HTTP_KEY'] : $this->generate_key();
		$_SESSION['SAJA_HTTP_KEY'] = $this->http_key;
	}
	
	function clear_secure_http()
	{
		$this->http_key = null;
		unset($_SESSION['SAJA_HTTP_KEY']);
	}
	
	function generate_key()
	{
		return md5(uniqid(rand()));
	}
	
	function saja_js()
	{
		$js  = '<script type="text/javascript">var SAJA_PATH="'.$this->saja_path.'"; var SAJA_HTTP_KEY="'.$this->http_key.'"</script>'."\n";
		$js .= '<script type="text/javascript" SRC="'.$this->saja_path.'saja.js"></script>'."\n";;
		return $js;
	}
	
	function saja_status($style='', $string='Working...')
	{
		return "<span id=\"sajaStatus\" style=\"visibility:hidden;$style\">".htmlentities($string)."</span>";	
	}

	function hasActions()
	{
		return (count($this->actions) > 0);
	}
	
	//example: set_process_path('myFunctions/');
	function set_process_path($fpath)
	{
		$this->saja_process_path = $fpath;
	}
	
	function set_process_class($name)
	{
		$this->saja_process_class = $name;
	}
	
	function get_process_class()
	{
		return $this->saja_process_class;
	}
	
	//exaple: set_process_file('myOtherFunctions.php');
	function set_process_file($filename)
	{
		$this->saja_process_file = $filename;
	}
	
	function get_process_file()
	{
		return $this->saja_process_file;
	}

	function run($commands, $process_file=null)
	{
		if(!$this->http_key)
			$this->clear_secure_http();
		
		if(!$process_file)
			$process_file = $this->get_process_file();
		return $this->ParseCommands($commands, $process_file);
	}

	function ParseCommands($commands, $process_file)
	{
		$commands = $this->texplode(';', $commands);
		$all_commands = '';
		$request_id = '';
		foreach($commands as $command)
		{
			$inputType = '';
            $targets = '';
			
			$tmp = $this->texplode('->', $command);
			if(isset($tmp[0]))
				$functions = $tmp[0];
			if(isset($tmp[1]))
				$targets = $tmp[1];

			if(strstr($functions, '('))
			{
				$action = '';
				$target = '';
                $targetProperty = '';
                
				$inputArray = explode('(', $functions, 2);
				list($function, $args) = $inputArray;
				$args = substr($args, 0, -1);
				
				$tmp = $this->texplode(',', $targets);
				if(isset($tmp[0]))
					$target = $tmp[0];
				if(isset($tmp[1]))
					$action = $tmp[1];
				
				$tmp = $this->texplode(':', $target);
				if(isset($tmp[0]))
					$targetId = $tmp[0];
				if(isset($tmp[1]))
					$targetProperty = $tmp[1];
				
				if(!$action)
					$action = 'r';
				if(!$targetProperty)
					$targetProperty = 'innerHTML';
				if(!$targets)
					$action = $targetProperty = $targetId = '';
				
				if($function)
				{
					$request_id = md5($function . $this->salt);
					$_SESSION['SAJA_PROCESS']['REQUESTS'][$request_id] = array(
						'FUNCTION' => $function,
						'PROCESS_FILE' => $process_file ? $process_file : $this->get_process_file(),
						'CLASS' => $this->get_process_class()
					);
					
					$session_id = session_id();
					$all_commands .= "saja.run('".$this->parseArgs($args, 'PHP')."','$targetId','$action','$targetProperty','$session_id','$request_id');";
				}
			}
		}
		
		return $all_commands;
	}

	function parseArgs($args, $getType)
	{
		$i = 0;
		$inner = '';
		$args = $this->texplode(',',$args);
		if($args)
		foreach($args as $arg)
		{
			$id = $property = '';
			
			//shortcut for element:property syntax
			if(strstr($arg,':'))
			{
				$tmp = $this->texplode(':', $arg);
				if(isset($tmp[0]))
					$id = $tmp[0];
				if(isset($tmp[1]))
					$property = $tmp[1];
				$arg = '';
			}
			if($getType == 'PHP')
			{
				if($i) $inner .= ',';
				if($property)
					$inner .= "'+saja.Get('$id','$property')+'";
				else if($arg)
					$inner .= "'+saja.Get($arg)+'";
				else
					$inner .= "'+saja.Get($id)+'";
			}
			$i++;
		}
		return $inner;
	}

	function texplode($seperator, $str)
	{
		$vals = array();
		foreach(explode($seperator, $str) as $val)
			if($val!=='')
				$vals[] = trim($val);
		return $vals;
	}

	
################################################################################
#
#			SAJA RESPONSE FUNCTIONS
#
	//execute raw javascript code
	function js($js)
	{
		$this->add_action($js);
	}
	
	//redirect the browser to a URL
	function redirect($url)
	{
		$this->add_action("window.location = '$url'");
	}
	
	//adds a new saja action to the queue
	function exec($action)
	{
		$this->add_action($this->run($action));
	}
	
	//used for placing complex / long text into an element
	function text($content, $target)
	{
		$action = '';
        $targetProperty = '';
		list($target, $action) = $this->texplode(',', $target);
		list($targetId, $targetProperty) = $this->texplode(':', $target);
		if(!$action) $action = 'r';
		if(!$targetProperty) $targetProperty = 'innerHTML';
		$action = "saja.Put(unescape('".rawurlencode($content)."'),'$targetId','$action','$targetProperty')";
		$this->add_action($action);
	}
	
	//hide an element
	function hide($element)
	{
		$this->add_action("saja.Put('none','$element','r','style.display')");	
	}
	
	//show an element
	function show($element)
	{
		$this->add_action("saja.Put('','$element','r','style.display')");
	}
	
	//set style for an element
	function style($element, $styleString)
	{
		$this->add_action("saja.SetStyle('$element', '$styleString')");
	}
	
	//return response actions to javascript for execution
	function send()
	{
		$ret = $this->get_actions();
		$this->actions = array();
		return $ret;
	}
	
	function add_action($js){
		$this->actions[] = $js;
	}
	
	function get_actions(){
		return ($this->hasActions() ? '<saja_split>' : '') . implode(';', $this->actions);
	}

################################################################################
#
#			REQUEST HANDLING
#

	function runFunc($function, $args)
	{	
		//decode encrypted HTTP data if needed
		if(isset($_SESSION['SAJA_HTTP_KEY'])){
			$this->secure_http();
			$args = $this->rc4($this->http_key, rawurldecode($args));
		}
		
		$args = explode(',', $args, 100);//limited to 100 arguments for DNOS attack protection
		for($i=0; $i<count($args); $i++){
			$args[$i] = unserialize(rawurldecode($args[$i]));
		}
		
		if(method_exists($this, $function))
			echo call_user_func_array(array(&$this, $function), $args);
		else
			echo "ERROR: [$function] Not validated.";
	}

	//RC4 Encryption from http://sourceforge.net/projects/rc4crypt
	function rc4($pwd, $data)
	{
		$cipher = '';
		$pwd_length = strlen($pwd);
		$data_length = strlen($data);
		for ($i = 0; $i < 256; $i++){
			$key[$i] = ord($pwd[$i % $pwd_length]);
			$box[$i] = $i;
		}
		for ($j = $i = 0; $i < 256; $i++){
			$j = ($j + $box[$i] + $key[$i]) % 256;
			$tmp = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}
		for ($a = $j = $i = 0; $i < $data_length; $i++){
			$a = ($a + 1) % 256;
			$j = ($j + $box[$a]) % 256;
			$tmp = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp;
			$k = $box[(($box[$a] + $box[$j]) % 256)];
			$cipher .= chr(ord($data[$i]) ^ $k);
		}
		return $cipher;
	}
}
?>