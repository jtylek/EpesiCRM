<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']) || !isset($_REQUEST['path']))
	die('Invalid usage');
$cid = $_REQUEST['cid'];
$path = $_REQUEST['path'];
$id = $_REQUEST['id'];
$disposition = (isset($_REQUEST['view']) && $_REQUEST['view'])?'inline':'attachment';

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$public = Module::static_get_module_variable($path,'public',false);
$protected = Module::static_get_module_variable($path,'protected',false);
$private = Module::static_get_module_variable($path,'private',false);
if(!Acl::is_user())
	die('Permission denied');
$row = DB::GetRow('SELECT uaf.id as id, uaf.attach_id, uaf.original,ual.local,ual.permission,ual.permission_by FROM utils_attachment_file uaf INNER JOIN utils_attachment_link ual ON ual.id=uaf.attach_id WHERE uaf.id='.DB::qstr($id));
$original = $row['original'];
$local = $row['local'];
$filename = $local.'/'.$row['id'];

if(!Base_AclCommon::i_am_admin() && $row['permission_by']!=Acl::get_user()) {
	if(($row['permission']==0 && !$public) ||
		($row['permission']==1 && !$protected) ||
		($row['permission']==2 && !$private))
		die('Permission denied');
}

require_once('mime.php');

if(headers_sent())
	die('Some data has already been output to browser, can\'t send file');

$t = time();
$remote_address = $_SERVER['REMOTE_ADDR'];
$remote_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
DB::Execute('INSERT INTO utils_attachment_download(attach_file_id,created_by,created_on,download_on,description,ip_address,host_name) VALUES (%d,%d,%T,%T,%s,%s,%s)',array($id,Acl::get_user(),$t,$t,$disposition,$remote_address,$remote_host));

if (isset($_REQUEST['thumbnail'])) {
    $o_filename = DATA_DIR.'/Utils_Attachment/'.$filename;
    $f_filename = $o_filename.'_thumbnail';
    if(!file_exists($f_filename)) {
	if(!file_exists($o_filename))
    	    die('File doesn\'t exists');
    	$image_info = getimagesize($o_filename);
    	$image_type = $image_info[2];
    	$image = false;
    	switch ($image_type) {
	    case IMAGETYPE_JPEG: $image = imagecreatefromjpeg($o_filename); break;
	    case IMAGETYPE_GIF: $image = imagecreatefromgif($o_filename); break;
	    case IMAGETYPE_PNG: $image = imagecreatefrompng($o_filename); break;
	    default: $buffer = file_get_contents($o_filename);
    	}
    	if ($image) {
    	    $img_w = imagesx($image);
    	    $img_h = imagesy($image);
    	    $max = 300;
    	    $w = ($img_w>=$img_h)?$max:floor($max*$img_w/$img_h);
    	    $h = ($img_h>=$img_w)?$max:floor($max*$img_h/$img_w);
    	    $new_image = imagecreatetruecolor($w, $h);
    	    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $w, $h, $img_w, $img_h);
    	    switch ($image_type) {
	        case IMAGETYPE_JPEG: imagejpeg($new_image,$f_filename,95); break;
		case IMAGETYPE_GIF: imagegif($new_image,$f_filename); break;
		case IMAGETYPE_PNG: imagepng($new_image,$f_filename); break;
    	    }
	    $buffer = file_get_contents($f_filename);
    	}
    } else {
	$buffer = file_get_contents($f_filename);
    }
} else {
    $f_filename = DATA_DIR.'/Utils_Attachment/'.$filename;
    if(!file_exists($f_filename))
    	die('File doesn\'t exists');
    $buffer = file_get_contents($f_filename);
}

$expires = 24*60*60;
header('Pragma: public');
header('Cache-Control: maxage='.(24*60*60));
header('Expires: ' . gmdate('D, d M Y H:i:s', time()+(24*60*60)) . ' GMT');
header('Content-Type: '.get_mime_type($f_filename,$original));
header('Content-Length: '.strlen($buffer));
header('Content-disposition: '.$disposition.'; filename="'.$original.'"');
echo $buffer;

?>