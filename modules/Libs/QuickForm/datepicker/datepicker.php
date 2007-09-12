<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-libs
 * @subpackage QuickForm
 */
?>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html; charset=WINDOWS-1250">
		<link rel="SHORTCUT ICON" href="icon/favicon.ico" />
		<link rel="stylesheet" type="text/css" href="css/datepicker.css">
		<!-- //-->
		<script type="text/javascript" language="JavaScript1.2" src="js/datepicker.js"></script> 
		<!-- //-->
		<script>
			datepicker = new cDatepicker('<?php print $_GET['field_name']; ?>', '<?php print $_GET['format']; ?>');
		</script>
		
		<title>Calendar</title>
	</head>
	
	<body onload='datepicker.show_month()'>
		<div id="datepicker_header"></div>
		<div id="datepicker_view"></div>
		<a align="center" href="javascript:window.close()" name="clos" value="closewindow">Close Window</a>
	</body>
<html>