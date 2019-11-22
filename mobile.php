<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 */

//TODO: menu tree
define('MOBILE_DEVICE',1);
define('CID',false);
require_once('include.php');

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
		return call_user_func_array(str_replace('"','',$this->func),$this->args);
	}
}

function mobile_stack_href($func,$args = array(),$caption=null) {
	$s = new LinkEntry($func,$args,$caption);
	$md5 = md5(serialize($s));
	end($_SESSION['stack'])->links[$md5]=$s;
	return 'href="mobile.php?'.http_build_query(array('page'=>$md5)).'"';
}

function sort_menus_cmp($a, $b) {
	global $menus_out_tmp;
	$aw = isset($menus_out_tmp[$a][2]) ? $menus_out_tmp[$a][2]:0;
	$bw = isset($menus_out_tmp[$b][2]) ? $menus_out_tmp[$b][2]:0;
	if(!isset($aw) || !is_numeric($aw)) $aw=0;
	if(!isset($bw) || !is_numeric($bw)) $bw=0;
	if($aw==$bw)
		return strcasecmp($a, $b);
	return $aw-$bw;
}


function mobile_menu() {
	$menus = ModuleManager::call_common_methods('mobile_menu');
	global $menus_out, $menus_out_tmp;
	$menus_out = array();
	foreach($menus as $m=>$r) {
		if(!is_array($r)) continue;
		foreach($r as $cap=>$met) {
			if(is_array($met)) {
				if(!isset($met['func'])) continue;
				$method = array($m.'Common',$met['func']);
				$args = isset($met['args'])?$met['args']:array();				
				$weight = isset($met['weight'])?$met['weight']:0;
				$color = isset($met['color'])?$met['color']:'white';
			} else {
				$method = array($m.'Common',$met);
				$args = array();
				$weight = 0;
				$color='white';
			}
			$menus_out[$cap] = array($method,$args,$weight,$color);
		}
	}
	$menus_out_tmp = $menus_out;
	uksort($menus_out,'sort_menus_cmp');
	foreach($menus_out as $cap=>$met) {
		print('<a '.mobile_stack_href($met[0],$met[1],$cap).' '.(IPHONE?' class="button '.$met[3].'"':'').'>'.$cap.'</a>'.(IPHONE?'':'<br>'));
	}
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
$back_id = 0;
if(IPHONE) {
	$title = end($captions);
	$back_id = key($captions);
	array_pop($captions);
	$back = '';
	$is_back = end($captions);
	if(end($stack)->caption!==$title) {
		if($is_back) {
			$back = '<a href="mobile.php?back='.($back_id+1).'" class="nav" id="backButton">'.$title.'</a>';
			$back_id = key($captions);
			$action = '<a href="mobile.php?back='.($back_id+1).'" class="nav Action">'.$is_back.'</a>';
		} else {
			$back = '<a href="mobile.php?back='.($back_id+1).'" class="nav Action">'.$title.'</a>';
			$action = '';
		}
	} else {
		$action = '';
		if($is_back) {
			$back_id = key($captions);
			$back = '<a href="mobile.php?back='.($back_id+1).'" class="nav" id="backButton">'.$is_back.'</a>';
		}
	}
	
	$title = '<h1>'.$title.'</h1>';
	$caption = $back.$title.$action;
} else {
	$cap = array();
	$last_back_id = 0;
	foreach($captions as $k=>$c) {
		$cap[] = '<a href="mobile.php?back='.($k+1).'">'.$c.'</a>';
		$last_back_id = $back_id;
		$back_id = $k;
	}
	$back_id = $last_back_id;
//	array_pop($cap); //don't display current breadthumb
	$caption = implode($cap,' > ');
}
if(isset($ret) && ($ret===false || is_numeric($ret))) {
	if(is_numeric($ret))
		$b = $back_id+2-$ret;
	else
		$b = $back_id+1;
	$stack = array_slice($stack,0,$b);
	header('Location: mobile.php');
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
	<link rel="apple-touch-icon" href="images/apple-favicon.png" />
	<script type="text/javascript" charset="utf-8">
		function clickclear(thisfield, defaulttext) {
			if (thisfield.value == defaulttext) {
				thisfield.value = "";
			}
		}
		function clickrecall(thisfield, defaulttext) {
			if (thisfield.value == "") {
				thisfield.value = defaulttext;
			}
		}
		window.onload = function() {
		  setTimeout(function(){window.scrollTo(0, 1);}, 100);
		}
	</script>
	<?php
	foreach($csses as $f)
	  	print('<link href="'.$f.'" type="text/css" rel="stylesheet"/>'."\n");
	?>
</head>

<body id="normal">
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
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<title>Epesi</title>
	<link href="mobile.css" type="text/css" rel="stylesheet"/>
	<?php
	foreach($csses as $f)
	  	print('<link href="'.$f.'" type="text/css" rel="stylesheet"/>'."\n");
	?>
	<script type="text/javascript" charset="utf-8">
		function clickclear(thisfield, defaulttext) {
			if (thisfield.value == defaulttext) {
				thisfield.value = "";
			}
		}
		function clickrecall(thisfield, defaulttext) {
			if (thisfield.value == "") {
				thisfield.value = defaulttext;
			}
		}
	</script>
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
        <span class="footer">Copyright &copy; <?php print(date('Y')); ?> &bull; <a href="http://www.telaxus.com">Telaxus LLC</a></span>
		<br>
		<p><a href="http://www.epesi.org"><img src="images/epesi-powered.png" border="0"></a></p>
        </center>
</body>
</html>
<?php
}
?>