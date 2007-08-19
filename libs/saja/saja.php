<?php
/*
	Example Usage:
	page url: http://www.yoursite.com/index.php
	saja url: http://www.yoursite.com/path/to/saja.php.
	
	For full documentation see: http://saja.sourceforge.net/
	
	---------- <index.php> -----------
	<?php
	include($_SERVER['DOCUMENT_ROOT'].'/path/to/saja.php');
	$saja = new saja;
	$saja->set_path('/path/to/');
	$saja->secure_http(); //uses session variables to encrypt HTTP data (optional)
	? >
	<div id=outputDiv>Some Text</div>
	<input type=text id=myInput>
	<button id=myButton onclick="<%=$saja->run("MyPhpFunction(myInput:value)->outputDiv:innerHTML");%>">do something</button>
	---------- </index.php> -----------

	---------- <saja.functions.php> -----------
	<?php
	function MyPhpFunction($myInput)
	{
		echo "You typed: [$myInput]";
		$saja = new saja;
		$saja->hide('myInput');
		$saja->text('Done!','myButton:innerHTML');
		$saja->alert("done!");
	}
	? >
	---------- </saja.functions.php> -----------
*/
class Saja {
	
	//configurable vars
	var $saja_path = '';								//default SAJA path - this can be set so you never have to call set_path() again
	var $saja_process_file = 'saja.functions.php';		//default process file to use
	var $saja_process_path = '';						//relative or full path to the directory that contains your process files (functions) i.e. "../myfunctions/", "/www/apache/htdocs/public/", etc.
	var $saja_process_class = 'myFunctions';			//default classname to use
	var $true_utf8 = false;								//keep set to false unless you understand the implications of attempting true utf8 support with PHP (most european character sets will work with this as false)
	
	//leave these vars alone
	var $functionPadding = 15;							//pad functions names having less than this many characters in their name
	var $actions = array();
	var $salt;
	var $http_key;
	var $argument_separator = '>>>saja_arg<<';			//separator for function arguments
	
	function Saja()
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
		$this->salt = isset($_SESSION['SAJA_SALT']) ? $_SESSION['SAJA_SALT'] : $this->generate_key();
		$_SESSION['SAJA_SALT'] = $this->salt;
	}
	
	function set_true_utf8($bool=true){
		$this->true_utf8 = $bool;	
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
		$js .= '<script type="text/javascript" src="'.$this->saja_path.'saja.js"></script>'."\n";;
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
		return $this->saja_process_path . $this->saja_process_file;
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
						'UTF8' => $this->true_utf8,
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
				if($i) $inner .= $this->argument_separator;
				if($property)
					$inner .= "'+saja.Get('$id','$property')+'";
				else if($arg || is_numeric($arg))
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
		foreach(explode($seperator, $str) as $val){
			if(is_numeric($val)){
				$vals[] = $val;
			} else if(strlen($val) > 0){
				$vals[] = trim($val);
			}
		}	
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
	function redirect($url='')
	{
		$this->add_action("window.location = '$url'");
	}
	
	//return a javascript alert
	function alert($txt)
	{
		$this->add_action("alert('".str_replace('\'', '\\\'', $txt)."')");
	}
		
	//adds a new saja action to the queue
	function exec($action)
	{
		$this->add_action($this->run($action));
	}

	private function encodeURIComponent($string) {
		$result = "";
		for ($i = 0; $i < strlen($string); $i++) {
			$result .= $this->encodeURIComponentbycharacter(urlencode($string[$i]));
		}
		return $result;
	}

	private function encodeURIComponentbycharacter($char) {
	   if ($char == "+") { return "%20"; }
	   if ($char == "%21") { return "!"; }
	   if ($char == "%27") { return '"'; }
	   if ($char == "%28") { return "("; }
	   if ($char == "%29") { return ")"; }
	   if ($char == "%2A") { return "*"; }
	   if ($char == "%7E") { return "~"; }
	   if ($char == "%80") { return "%E2%82%AC"; }
	   if ($char == "%81") { return "%C2%81"; }
	   if ($char == "%82") { return "%E2%80%9A"; }
	   if ($char == "%83") { return "%C6%92"; }
	   if ($char == "%84") { return "%E2%80%9E"; }
	   if ($char == "%85") { return "%E2%80%A6"; }
	   if ($char == "%86") { return "%E2%80%A0"; }
	   if ($char == "%87") { return "%E2%80%A1"; }
	   if ($char == "%88") { return "%CB%86"; }
	   if ($char == "%89") { return "%E2%80%B0"; }
	   if ($char == "%8A") { return "%C5%A0"; }
	   if ($char == "%8B") { return "%E2%80%B9"; }
	   if ($char == "%8C") { return "%C5%92"; }
	   if ($char == "%8D") { return "%C2%8D"; }
	   if ($char == "%8E") { return "%C5%BD"; }
	   if ($char == "%8F") { return "%C2%8F"; }
	   if ($char == "%90") { return "%C2%90"; }
	   if ($char == "%91") { return "%E2%80%98"; }
	   if ($char == "%92") { return "%E2%80%99"; }
	   if ($char == "%93") { return "%E2%80%9C"; }
	   if ($char == "%94") { return "%E2%80%9D"; }
	   if ($char == "%95") { return "%E2%80%A2"; }
	   if ($char == "%96") { return "%E2%80%93"; }
	   if ($char == "%97") { return "%E2%80%94"; }
	   if ($char == "%98") { return "%CB%9C"; }
	   if ($char == "%99") { return "%E2%84%A2"; }
	   if ($char == "%9A") { return "%C5%A1"; }
	   if ($char == "%9B") { return "%E2%80%BA"; }
	   if ($char == "%9C") { return "%C5%93"; }
	   if ($char == "%9D") { return "%C2%9D"; }
	   if ($char == "%9E") { return "%C5%BE"; }
	   if ($char == "%9F") { return "%C5%B8"; }
	   if ($char == "%A0") { return "%C2%A0"; }
	   if ($char == "%A1") { return "%C2%A1"; }
	   if ($char == "%A2") { return "%C2%A2"; }
	   if ($char == "%A3") { return "%C2%A3"; }
	   if ($char == "%A4") { return "%C2%A4"; }
	   if ($char == "%A5") { return "%C2%A5"; }
	   if ($char == "%A6") { return "%C2%A6"; }
	   if ($char == "%A7") { return "%C2%A7"; }
	   if ($char == "%A8") { return "%C2%A8"; }
	   if ($char == "%A9") { return "%C2%A9"; }
	   if ($char == "%AA") { return "%C2%AA"; }
	   if ($char == "%AB") { return "%C2%AB"; }
	   if ($char == "%AC") { return "%C2%AC"; }
	   if ($char == "%AD") { return "%C2%AD"; }
	   if ($char == "%AE") { return "%C2%AE"; }
	   if ($char == "%AF") { return "%C2%AF"; }
	   if ($char == "%B0") { return "%C2%B0"; }
	   if ($char == "%B1") { return "%C2%B1"; }
	   if ($char == "%B2") { return "%C2%B2"; }
	   if ($char == "%B3") { return "%C2%B3"; }
	   if ($char == "%B4") { return "%C2%B4"; }
	   if ($char == "%B5") { return "%C2%B5"; }
	   if ($char == "%B6") { return "%C2%B6"; }
	   if ($char == "%B7") { return "%C2%B7"; }
	   if ($char == "%B8") { return "%C2%B8"; }
	   if ($char == "%B9") { return "%C2%B9"; }
	   if ($char == "%BA") { return "%C2%BA"; }
	   if ($char == "%BB") { return "%C2%BB"; }
	   if ($char == "%BC") { return "%C2%BC"; }
	   if ($char == "%BD") { return "%C2%BD"; }
	   if ($char == "%BE") { return "%C2%BE"; }
	   if ($char == "%BF") { return "%C2%BF"; }
	   if ($char == "%C0") { return "%C3%80"; }
	   if ($char == "%C1") { return "%C3%81"; }
	   if ($char == "%C2") { return "%C3%82"; }
	   if ($char == "%C3") { return "%C3%83"; }
	   if ($char == "%C4") { return "%C3%84"; }
	   if ($char == "%C5") { return "%C3%85"; }
	   if ($char == "%C6") { return "%C3%86"; }
	   if ($char == "%C7") { return "%C3%87"; }
	   if ($char == "%C8") { return "%C3%88"; }
	   if ($char == "%C9") { return "%C3%89"; }
	   if ($char == "%CA") { return "%C3%8A"; }
	   if ($char == "%CB") { return "%C3%8B"; }
	   if ($char == "%CC") { return "%C3%8C"; }
	   if ($char == "%CD") { return "%C3%8D"; }
	   if ($char == "%CE") { return "%C3%8E"; }
	   if ($char == "%CF") { return "%C3%8F"; }
	   if ($char == "%D0") { return "%C3%90"; }
	   if ($char == "%D1") { return "%C3%91"; }
	   if ($char == "%D2") { return "%C3%92"; }
	   if ($char == "%D3") { return "%C3%93"; }
	   if ($char == "%D4") { return "%C3%94"; }
	   if ($char == "%D5") { return "%C3%95"; }
	   if ($char == "%D6") { return "%C3%96"; }
	   if ($char == "%D7") { return "%C3%97"; }
	   if ($char == "%D8") { return "%C3%98"; }
	   if ($char == "%D9") { return "%C3%99"; }
	   if ($char == "%DA") { return "%C3%9A"; }
	   if ($char == "%DB") { return "%C3%9B"; }
	   if ($char == "%DC") { return "%C3%9C"; }
	   if ($char == "%DD") { return "%C3%9D"; }
	   if ($char == "%DE") { return "%C3%9E"; }
	   if ($char == "%DF") { return "%C3%9F"; }
	   if ($char == "%E0") { return "%C3%A0"; }
	   if ($char == "%E1") { return "%C3%A1"; }
	   if ($char == "%E2") { return "%C3%A2"; }
	   if ($char == "%E3") { return "%C3%A3"; }
	   if ($char == "%E4") { return "%C3%A4"; }
	   if ($char == "%E5") { return "%C3%A5"; }
	   if ($char == "%E6") { return "%C3%A6"; }
	   if ($char == "%E7") { return "%C3%A7"; }
	   if ($char == "%E8") { return "%C3%A8"; }
	   if ($char == "%E9") { return "%C3%A9"; }
	   if ($char == "%EA") { return "%C3%AA"; }
	   if ($char == "%EB") { return "%C3%AB"; }
	   if ($char == "%EC") { return "%C3%AC"; }
	   if ($char == "%ED") { return "%C3%AD"; }
	   if ($char == "%EE") { return "%C3%AE"; }
	   if ($char == "%EF") { return "%C3%AF"; }
	   if ($char == "%F0") { return "%C3%B0"; }
	   if ($char == "%F1") { return "%C3%B1"; }
	   if ($char == "%F2") { return "%C3%B2"; }
	   if ($char == "%F3") { return "%C3%B3"; }
	   if ($char == "%F4") { return "%C3%B4"; }
	   if ($char == "%F5") { return "%C3%B5"; }
	   if ($char == "%F6") { return "%C3%B6"; }
	   if ($char == "%F7") { return "%C3%B7"; }
	   if ($char == "%F8") { return "%C3%B8"; }
	   if ($char == "%F9") { return "%C3%B9"; }
	   if ($char == "%FA") { return "%C3%BA"; }
	   if ($char == "%FB") { return "%C3%BB"; }
	   if ($char == "%FC") { return "%C3%BC"; }
	   if ($char == "%FD") { return "%C3%BD"; }
	   if ($char == "%FE") { return "%C3%BE"; }
	   if ($char == "%FF") { return "%C3%BF"; }
	   return $char;
	}
	
	//used for placing complex / long text into an element
	function text($content, $target)
	{
		$x = $this->texplode(',', $target);
		if(!isset($x[1])) $x[1] = 'r';
		list($target, $action) = $x;
		$x = $this->texplode(':', $target);
		if(!isset($x[1])) $x[1] = 'innerHTML';
		list($targetId, $targetProperty) = $x;
		$action = "saja.Put(".($this->true_utf8 ? 'decodeURIComponent'."('".rawurlencode(utf8_encode($content)) : 'unescape'."('".rawurlencode($content))."'),'$targetId','$action','$targetProperty')";
		//$action = "saja.Put('".str_replace('\'', '\\\'', $content)."','$targetId','$action','$targetProperty')";
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
		//kill magic quotes
		if(get_magic_quotes_gpc()){
			$args = stripslashes($args);
		}
		
		//decode encrypted HTTP data if needed
		if(isset($_SESSION['SAJA_HTTP_KEY'])){
			$this->secure_http();
			$args = $this->rc4($this->http_key, utf8_decode(rawurldecode($args)));
		}
		
		$args = explode($this->argument_separator, $args, 100);//limited to 100 arguments for DNOS attack protection
		//echo 'args: '; print_r($args);
		for($i=0; $i<count($args); $i++){
			if($this->true_utf8)
				$args[$i] = $this->utf8_unserialize(rawurldecode($args[$i]));
			else
				$args[$i] = unserialize(utf8_decode(rawurldecode($args[$i])));
		}

		if(method_exists($this, $function))
			echo call_user_func_array(array(&$this, $function), $args);
		else
			echo "ERROR: [$function] Not validated.";
	}
	
	function utf8_unserialize($str){
		if(preg_match('/^a:[0-9]+:{s:[0-9]+:"/', $str)){
			$ret = array();
			$args = preg_split('/"?;?s:[0-9]+:"/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
			array_shift($args);
			$last = array_pop($args);
			$last = preg_replace('/";}$/', '', $last);
			$args[] = $last;
			for($i=0; $i<count($args); $i+=2){
				$ret[$args[$i]] = $args[$i+1];
			}
			return $ret;
		} else if(preg_match('/^a:[0-9]+:{i:[0-9]+;s:[0-9]+:"/', $str)){
			$args = preg_split('/"?;?i:[0-9]+;s:[0-9]+:"/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
			array_shift($args);
			$last = array_pop($args);
			$last = preg_replace('/";}$/', '', $last);
			$args[] = $last;
			return $args;
		} else {
			$args = preg_split('/^s:[0-9]+:"([\w\W]*?)";$/', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
			return $args[1];
		}
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