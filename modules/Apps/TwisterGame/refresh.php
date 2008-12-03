<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage twistergame
 */
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

define('JS_OUTPUT',1);
define('SET_SESSION',1);
define('CID',false);
require_once('../../../include.php');
ModuleManager::load_modules();
$colors = array("red","green","blue","yellow");
$hand = array("hand","foot");
$dir = array("right","left");
$who = array("Player 1","Player 2");
if(!isset($_SESSION['twister'])) $_SESSION['twister']=0;
print('$("twister_who").innerHTML="'.$who[($_SESSION['twister']++)%2].'";');
print('$("twister_color").style.backgroundColor="'.$colors[mt_rand(0,3)].'";');
print('$("twister_hand").innerHTML="'.$dir[mt_rand(0,1)].' '.$hand[mt_rand(0,1)].'";');
?>
