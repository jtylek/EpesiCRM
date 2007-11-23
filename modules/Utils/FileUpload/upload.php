<?php
/**
 * Uploads file
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-utils
 * @subpackage file-uploader
 */
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // date in the past

if(!isset($_REQUEST['form_name']) || !isset($_REQUEST['required']))
	exit();
$form_name = $_REQUEST['form_name'];
$doc = $_FILES['file'];
$dest_filename  = 'tmp_'.microtime(true);
$dest_path  = 'data/Utils_FileUpload/'.$dest_filename;
$dest_doc = '../../../'.$dest_path;
$required = $_REQUEST['required'];

if($doc['error']!='0') {
	if($required &&  $name=='') {
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status').innerHTML='Please specify file to upload';
	-->
	</script>
	<?php
	} elseif($required) {
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status').innerHTML='Unable to upload specified file';
	-->
	</script>
	<?php
	} else {
	$ok = true;
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status').innerHTML='File not specified';
	-->
	</script>
	<?php
	}
} else {
	$ok = true;
	move_uploaded_file($doc['tmp_name'], $dest_doc);
	?>
	<script type="text/javascript">
	<!--
	parent.$('upload_status').innerHTML='uploaded <?php print($doc['name']); ?>';
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
	parent.document.forms['<?php print($form_name); ?>'].button.disabled=false;
<?php
	if($ok) {
		if(get_magic_quotes_gpc())
			print(stripslashes($_REQUEST['submit_js'])."\n");
		else
			print($_REQUEST['submit_js']."\n");
	}
?>
	-->
</script>
