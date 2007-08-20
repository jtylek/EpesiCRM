<?php
/**
 * Uploads file
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-utils
 * @subpackage file-uploader
 */

$form_name = $_REQUEST['form_name'];
if(!isset($form_name))
	exit();
$doc = $_FILES['file'];
$dest_filename  = 'tmp_'.microtime(true);
$dest_path  = 'data/Utils_FileUpload/'.$dest_filename;
$dest_doc = '../../../'.$dest_path;

if($doc['error']!='0') {
	?>
	<script type="text/javascript">
	<!--
	parent.document.getElementById('upload_status').innerHTML='Unable to upload specified file';
	-->
	</script>
	<?php
} else {
	$ok = true;
	move_uploaded_file($doc['tmp_name'], $dest_doc);
	?>
	<script type="text/javascript">
	<!--
	parent.document.getElementById('upload_status').innerHTML='uploaded <?=$doc['name']?>';
	parent.document.forms['<?=$form_name?>'].uploaded_file.value='<?=$dest_path?>';
	parent.document.forms['<?=$form_name?>'].original_file.value='<?=$doc['name']?>';
	-->
	</script>
	<?php
}

?>
<script type="text/javascript">
	<!--
	orig=parent.document.forms['<?=$form_name?>'].file;
	orig.disabled=false;
	orig.value='';
	parent.document.forms['<?=$form_name?>'].button.disabled=false; 
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
