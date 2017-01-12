--TEST--
Test 05 Test of addGroup()
--DESCTIPTION--
See http://pear.php.net/manual/en/package.html.html-quickform.html-quickform.addgroup.php
--FILE--
<?php
require_once 'HTML/QuickForm.php';
$form = new HTML_QuickForm('firstForm');
$group[] =& HTML_QuickForm::createElement('text', 'first', 'First');
$group[] =& HTML_QuickForm::createElement('text', 'last', 'Last');
$form->addGroup($group, 'name', 'Name:', ',&nbsp;');
$form->addElement('submit');
$form->display();
?>
--EXPECTF--
<form action="%s" method="post" name="firstForm" id="firstForm">
<div>
<table border="0">

	<tr>
		<td align="right" valign="top"><b>Name:</b></td>
		<td valign="top" align="left">	<input name="name[first]" type="text" />,&nbsp;<input name="name[last]" type="text" /></td>
	</tr>
	<tr>
		<td align="right" valign="top"><b></b></td>
		<td valign="top" align="left">	<input value="" type="submit" /></td>
	</tr>
</table>
</div>
</form>