--TEST--
Test 02 Initialisation of QuickForm class and call of addElement() and toHtml()
--FILE--
<?php
include_once 'HTML/QuickForm.php';
$TestForm = new HTML_QuickForm();
$TestForm->addElement('header', null, 'QuickForm tutorial example');
$TestForm->toHtml(); // Without echo!
?>
--EXPECT--