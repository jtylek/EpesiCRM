<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$keys = Variable::get('license_key', false);
if (is_array($keys) && array_key_exists("https://ess.epe.si/", $keys)) {
    $keys["https://ess.epe.si/"] = $keys["https://ess.epesibim.com/"];
    unset($keys["https://ess.epesibim.com/"]);
    Variable::set('license_key', $keys);
}
?>
