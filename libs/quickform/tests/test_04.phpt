--TEST--
Test 04 Ouptput of tutorial form with applyFilter() and addRule()
--FILE--
<?php
require_once 'HTML/QuickForm.php';
$form = new HTML_QuickForm('firstForm');
$form->setDefaults(array(
    'name' => 'Joe User'
));
$form->addElement('header', null, 'QuickForm tutorial example');
$form->addElement('text', 'name', 'Enter your name:', array('size' => 50, 'maxlength' => 255));
$form->addElement('submit', null, 'Send');
$form->applyFilter('name', 'trim');
$form->addRule('name', 'Please enter your name', 'required', null, 'client');
$form->display();
?>
--EXPECTF--
<script type="text/javascript">
//<![CDATA[
function validate_firstForm(frm) {
  var value = '';
  var errFlag = new Array();
  var _qfGroups = {};
  _qfMsg = '';

  value = frm.elements['name'].value;
  if (value == '' && !errFlag['name']) {
    errFlag['name'] = true;
    _qfMsg = _qfMsg + '\n - Please enter your name';
  }

  if (_qfMsg != '') {
    _qfMsg = 'Invalid information entered.' + _qfMsg;
    _qfMsg = _qfMsg + '\nPlease correct these fields.';
    alert(_qfMsg);
    return false;
  }
  return true;
}
//]]>
</script>

<form action="%s" method="post" name="firstForm" id="firstForm" onsubmit="try { var myValidator = validate_firstForm; } catch(e) { return true; } return myValidator(this);">
<div>
<table border="0">

	<tr>
		<td style="white-space: nowrap; background-color: #CCCCCC;" align="left" valign="top" colspan="2"><b>QuickForm tutorial example</b></td>
	</tr>
	<tr>
		<td align="right" valign="top"><span style="color: #ff0000">*</span><b>Enter your name:</b></td>
		<td valign="top" align="left">	<input size="50" maxlength="255" name="name" type="text" value="Joe User" /></td>
	</tr>
	<tr>
		<td align="right" valign="top"><b></b></td>
		<td valign="top" align="left">	<input value="Send" type="submit" /></td>
	</tr>
	<tr>
		<td></td>
	<td align="left" valign="top"><span style="font-size:80%; color:#ff0000;">*</span><span style="font-size:80%;"> denotes required field</span></td>
	</tr>
</table>
</div>
</form>