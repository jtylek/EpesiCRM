<?php
/**
 * Uploads file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage file-uploader
 */
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_REQUEST['form_name']) || !isset($_REQUEST['required'])) {
	?>
	<script type="text/javascript">
	<!--
	parent.Epesi.append_js('Epesi.procOn--;Epesi.updateIndicator();');
	alert('Invalid request or postmaxsize exceeded');
	-->
	</script>
	<?php
	exit();
}

if(!isset($_REQUEST['cid']))
	die('Client ID not defined');

define('CID',$_REQUEST['cid']);
require_once('../../../include.php');
ModuleManager::load_modules();
if(!Acl::is_user())
	exit();
$form_name = $_REQUEST['form_name'];
if (isset($_FILES['file'])) {
	$doc = $_FILES['file'];
	$ext = strrchr($doc['name'],'.');
	$dest_filename  = 'tmp_'.microtime(true).$ext;
	$dest_path  = DATA_DIR.'/Utils_FileUpload/'.$dest_filename;
	$required = $_REQUEST['required'];

	if($doc['error']==UPLOAD_ERR_INI_SIZE || $doc['error']==UPLOAD_ERR_FORM_SIZE) {
		?>
		<script type="text/javascript">
		<!--
		alert('Specified file is too big');
		-->
		</script>
		<?php
	} elseif($doc['error']==UPLOAD_ERR_PARTIAL || $doc['error']==UPLOAD_ERR_EXTENSION) {
		?>
		<script type="text/javascript">
		<!--
		alert('Upload failed');
		-->
		</script>
		<?php
	} elseif($doc['error']==UPLOAD_ERR_NO_TMP_DIR || $doc['error']==UPLOAD_ERR_CANT_WRITE) {
		?>
		<script type="text/javascript">
		<!--
		alert('Invalid server setup: cannot write to temporary directory');
		-->
		</script>
		<?php
	} elseif($doc['error']==UPLOAD_ERR_NO_FILE) {
		if($required) {
		?>
		<script type="text/javascript">
		<!--
		alert('Please specify file to upload');
		-->
		</script>
		<?php
		} else {
			$ok = true;
			$_SESSION['client']['uploaded_file'] = false;
			$_SESSION['client']['uploaded_original_file'] = false;
			session_commit();
		}
	} else {
		$ok = true;
		move_uploaded_file($doc['tmp_name'], $dest_path);
		$_SESSION['client']['uploaded_file'] = $dest_path;
		$_SESSION['client']['uploaded_original_file'] = $doc['name'];
		session_commit();
	}
} else {
	$ok = true;
	$_SESSION['client']['uploaded_file'] = false;
	$_SESSION['client']['uploaded_original_file'] = false;
	session_commit();
}
?>
<script type="text/javascript">
	<!--
	parent.Epesi.append_js('Epesi.procOn--;Epesi.updateIndicator();document.forms[\'<?php print($form_name); ?>\'].file.value=\'\';');
<?php
	if(isset($ok) && $ok) {
		$sjs = stripslashes($_REQUEST['submit_js']);
		if(get_magic_quotes_gpc())
			$sjs = stripslashes($sjs);
		print('parent.Epesi.append_js(\''.addcslashes($sjs,"\\'").'\');'."\n");
	}
?>
	-->
</script>
