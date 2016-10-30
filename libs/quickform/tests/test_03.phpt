--TEST--
Test 03 Output of tutoral form
--FILE--
<?php
require_once 'HTML/QuickForm.php';
$form = new HTML_QuickForm('firstForm');
$form->addElement('header', null, 'QuickForm tutorial example');
$form->addElement('text', 'name', 'Enter your name:', array('size' => 50, 'maxlength' => 255));
$form->addElement('submit', null, 'Send');
$form->display();
?>
--EXPECTF--
<form action="%s" method="post" name="firstForm" id="firstForm">
<div>
<table border="0">

	<tr>
		<td style="white-space: nowrap; background-color: #CCCCCC;" align="left" valign="top" colspan="2"><b>QuickForm tutorial example</b></td>
	</tr>
	<tr>
		<td align="right" valign="top"><b>Enter your name:</b></td>
		<td valign="top" align="left">	<input size="50" maxlength="255" name="name" type="text" /></td>
	</tr>
	<tr>
		<td align="right" valign="top"><b></b></td>
		<td valign="top" align="left">	<input value="Send" type="submit" /></td>
	</tr>
</table>
</div>
</form>