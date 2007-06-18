<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @package apps-gallery
 * @licence SPL
 */
 
//print_r($_REQUEST);
//print_r($_FILES);

$form_name = $_REQUEST['form_name'];
if(!isset($form_name))
	exit();
$doc = $_FILES['xls'];
$ext = strrchr($doc['name'],'.');
$dest_filename  = $doc['name'];
$dest_path  = $dest_filename;
$dest_doc = '../../../'.$_REQUEST['root'].$_REQUEST['target'].$dest_path;
if(!$ext || (!eregi('\.(jpg)$', $ext) && !eregi('\.(jpeg)$', $ext) && !eregi('\.(gif)$', $ext) && !eregi('\.(png)$', $ext))) {
	?>
	<script type="text/javascript">
	<!-- 
	parent.document.getElementById('upload_status').innerHTML='Invalid file extension';
	-->
	</script>
	<?php
} elseif($doc['error']!='0' || !move_uploaded_file($doc['tmp_name'], $dest_doc)) {
	?>
	<script type="text/javascript">
	<!--
	parent.document.getElementById('upload_status').innerHTML='Unable to upload specified file';
	-->
	</script>
	<?php
} else {
	$ok = true;
	?>
	<script type="text/javascript">
	<!--
	parent.document.getElementById('upload_status').innerHTML='uploaded <?=$doc['name']?>';
	parent.document.forms['<?=$form_name?>'].uploaded_file.value='<?=$dest_filename?>';
	-->
	</script>
	<?php
}

?>
<script type="text/javascript">
	<!--
	orig=parent.document.forms['<?=$form_name?>'].xls;
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
