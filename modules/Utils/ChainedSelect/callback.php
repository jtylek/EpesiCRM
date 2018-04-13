<?php
/**
 * Retrieve ChainedSelect values using callback
 * @author Georgi Hristov
 * @copyright Copyright 2016 Georgi Hristov
 * @license MIT
 * @version 1.0
 * @package Utils/ChainedSelect
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

$vals = (array) $_POST['values'];

$params = array();
foreach($_POST['parameters'] as $k=>$v) {
	$params[$k] = $v;
}

$field = isset($params['__field__'])? $params['__field__']: '';
$callback_hash = isset($params['__callback__'])? $params['__callback__']: '';

$callback = array();
if (isset($_SESSION['client']['utils_chainedselect'][$callback_hash]))
	$callback = $_SESSION['client']['utils_chainedselect'][$callback_hash];

unset($params['__field__']);
unset($params['__callback__']);

$res = array();
if ($field && $callback && is_callable($callback))
	$res = call_user_func($callback, $vals, $params, $field);

if (!is_array($res)) 
	$res = array();

print(json_encode($res));
?>