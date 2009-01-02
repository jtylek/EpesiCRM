<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// second select
$select2[0][0] = '--- Artist ---';
$select2[0][1] = 'Red Hot Chil Peppers';
$select2[0][2] = 'The Pixies';

$select2[1][0] = '--- Artist ---';
$select2[1][1] = 'Wagner';
$select2[1][2] = 'Strauss';

$select2[2][0] = '--- Artist ---';
$select2[2][1] = 'Pantheist';
$select2[2][2] = 'Skepticism';

// Create a third select with prices for the cds
$select3[0][0][0] = '--- Choose the artist ---';
$select3[0][1][0] = '15.00$';
$select3[0][2][1] = '17.00$';
$select3[1][0][0] = '--- Choose the artist ---';
$select3[1][1][0] = '15.00$';
$select3[1][2][1] = '17.00$';
$select3[2][0][0] = '--- Choose the artist ---';
$select3[2][1][0] = '15.00$';
$select3[2][2][1] = '17.00$'; 

$ret = '';

$ret = array();
if(isset($_POST['values']->sel11)) {
	if(isset($_POST['values']->sel22))
		$ret = $select3[$_POST['values']->sel11][$_POST['values']->sel22];
	else
		$ret = $select2[$_POST['values']->sel11];
}

print(json_encode($ret));
?>