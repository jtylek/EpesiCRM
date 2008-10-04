<?php
//TODO: menu tree
define('MOBILE_DEVICE',1);
define('CID',false);
require_once('include.php');
if(detect_iphone())
	define('IPHONE',1);
else
	define('IPHONE',0);
ModuleManager::load_modules();

class LinkEntry {
	public $func = null;
	public $args = array();
	public $caption = null;
	
	public function __construct($f,$a=array(),$c) {
		$this->caption = $c;
		$this->func = $f;
		$this->args = $a;
	}
}

class StackEntry {
	public $caption = null;
	public $func = null;
	public $args = array();
	public $links = array();
	
	public function __construct($c,$f=null,$a=array()) {
		if($c instanceof LinkEntry) {
			$this->caption = $c->caption;
			$this->func = $c->func;
			$this->args = $c->args;
		} else {
			if($f===null)
				trigger_error('Invalid StackEntry usage');
			$this->caption = $c;
			$this->func = $f;
			$this->args = $a;
		}
	}

	public function go() {
		return call_user_func_array($this->func,$this->args);
	}
}

function mobile_stack_href($func,$args = array(),$caption=null) {
	$s = new LinkEntry($func,$args,$caption);
	$md5 = md5(serialize($s));
	end($_SESSION['stack'])->links[$md5]=$s;
	return 'href="mobile.php?'.http_build_query(array('page'=>$md5)).'"';
}

function mobile_menu() {
	$menus = ModuleManager::call_common_methods('mobile_menu');
	$menus_out = array();
	foreach($menus as $m=>$r) {
		if(!is_array($r)) continue;
		foreach($r as $cap=>$met) {
			if(is_array($met)) {
				if(!isset($met['func'])) continue;
				$method = array($m.'Common',$met['func']);
				$args = isset($met['args'])?$met['args']:array();				
			} else {
				$method = array($m.'Common',$met);
				$args = array();
			}
			$menus_out[$cap] = array($method,$args);
		}
	}
	ksort($menus_out);
	foreach($menus_out as $cap=>$met)
		print('<a '.mobile_stack_href($met[0],$met[1],$cap).' '.(IPHONE?' class="white button"':'').'>'.$cap.'</a>'.(IPHONE?'':'<br>'));
}


if(!isset($_SESSION['stack']))
	$_SESSION['stack'] = array();
$stack = & $_SESSION['stack'];

//if emtpy push menu
if(empty($stack)) {
	$m = new StackEntry('Menu','mobile_menu');
	$stack[] = $m;
}

//back action
if(isset($_GET['back'])) {
	$stack = array_slice($stack,0,(int)$_GET['back']);
	header('Location: mobile.php');
	exit();
}

//go action
$page = end($stack);
if(isset($_GET['page'])) {
	$l = & $page->links;
	if(isset($l[$_GET['page']])) {
		$page = new StackEntry($l[$_GET['page']]);
		$stack[] = $page;
		header('Location: mobile.php');
		exit();
	}
}

$csses = array();
$page->links = array(); //clear page links
ob_start();
$ret = $page->go();
$body = ob_get_clean();
$captions = array();
foreach($stack as $s)
	if($s->caption) 
		$captions[] = $s->caption;
if(IPHONE) {
	$title = end($captions);
	$back_id = key($captions);
	if(end($stack)->caption!==$title)
		$action = '<a href="mobile.php?back='.($back_id+1).'" class="nav Action">'.$title.'</a>';
	else
		$action = '';
	$title = '<h1>'.$title.'</h1>';
	array_pop($captions);
	$back = end($captions);
	if($back) {
		$back_id = key($captions);
		$back = '<a href="mobile.php?back='.($back_id+1).'" class="nav" id="backButton">'.$back.'</a>';
	}
	
	$caption = $back.$title.$action;
} else {
	$cap = array();
	foreach($captions as $k=>$c)
		$cap[] = '<a href="mobile.php?back='.($k+1).'">'.$c.'</a>';
	$caption = implode($cap,' > ');
}
if(isset($ret) && $ret===false) {
	header('Location: mobile.php?back=1');
	exit();
}

$csses = Epesi::get_csses();

if(IPHONE) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta id="viewport" name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" />
	<title>epesi CRM</title>
	<link rel="stylesheet" href="libs/UiUIKit/stylesheets/iphone.css" />
	<link rel="apple-touch-icon" href="images/apple-touch-icon.png" />
	<script type="text/javascript" charset="utf-8">
		window.onload = function() {
		  setTimeout(function(){window.scrollTo(0, 1);}, 100);
		}
	</script>
	<?php
	foreach($csses as $f)
	  	print('<link href="'.$f.'" type="text/css" rel="stylesheet"/>'."\n");
	?>
</head>

<body>
<div id="header">
		<?php print($caption); ?>
</div>
	
<?php print($body); ?>
        
</body>
</html>


<?php
} else {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
	<title>Epesi</title>
	<link href="mobile.css" type="text/css" rel="stylesheet"/>
	<?php
	foreach($csses as $f)
	  	print('<link href="'.$f.'" type="text/css" rel="stylesheet"/>'."\n");
	?>
</head>
<body>
        <table id="banner" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td class="back"><?php print($caption); ?></td>
            </tr>
        </table>
        <br>
        <center>
        <table id="main" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td>
				<?php print($body); ?>
                </td>
            </tr>
        </table>
        </center>
        <br>
        <center>
        <span class="footer">Copyright &copy; 2008 &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
		<br>
		<p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" border="0"></a></p>
        </center>
</body>
</html>
<?php
}
?>