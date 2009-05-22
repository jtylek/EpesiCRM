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

if(!isset($_REQUEST['form_name']) || !isset($_REQUEST['required']) || !isset($_FILES['file']))
	exit();

define('CID',false);
require_once('../../../include.php');
if(!Acl::is_user())
	exit();
$form_name = $_REQUEST['form_name'];
$doc = $_FILES['file'];
$dest_filename  = 'tmp_'.microtime(true);
$dest_path  = DATA_DIR.'/Utils_FileUpload/'.$dest_filename;
$required = $_REQUEST['required'];

if($doc['error']==UPLOAD_ERR_INI_SIZE || $doc['error']==UPLOAD_ERR_FORM_SIZE) {
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status_<?php print($form_name);?>').innerHTML='Specified file too big';
	-->
	</script>
	<?php
} elseif($doc['error']==UPLOAD_ERR_PARTIAL || $doc['error']==UPLOAD_ERR_EXTENSION) {
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status_<?php print($form_name);?>').innerHTML='Upload failed';
	-->
	</script>
	<?php
} elseif($doc['error']==UPLOAD_ERR_NO_TMP_DIR || $doc['error']==UPLOAD_ERR_CANT_WRITE) {
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status_<?php print($form_name);?>').innerHTML='Invalid server setup: cannot write to temporary directory';
	-->
	</script>
	<?php
} elseif($doc['error']==UPLOAD_ERR_NO_FILE) {
	if($required) {
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status_<?php print($form_name);?>').innerHTML='Please specify file to upload';
	-->
	</script>
	<?php
	} else {
	$ok = true;
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status_<?php print($form_name);?>').innerHTML='File not specified';
	-->
	</script>
	<?php
	}
} else {
	$ok = true;
	move_uploaded_file($doc['tmp_name'], $dest_path);
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status_<?php print($form_name);?>').innerHTML='uploaded <?php print($doc['name']); ?>';
	parent.document.forms['<?php print($form_name); ?>'].uploaded_file.value='<?php print($dest_path); ?>';
	parent.document.forms['<?php print($form_name); ?>'].original_file.value='<?php print($doc['name']); ?>';
	-->
	</script>
	<?php
}

?>
<script type="text/javascript">
	<!--
	orig=parent.document.forms['<?php print($form_name); ?>'].file;
	orig.disabled=false;
	orig.value='';
	//parent.document.forms['<?php print($form_name); ?>'].button.disabled=false;
<?php
	if(isset($ok) && $ok) {
		$sjs = stripslashes($_REQUEST['submit_js']);
		if(get_magic_quotes_gpc())
			$sjs = stripslashes($sjs);
		print($sjs."\n");
	}
?>
	-->
</script>