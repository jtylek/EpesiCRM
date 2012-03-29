<?php

$keys = Variable::get('license_key', false);
if (is_array($keys) && array_key_exists("https://ess.epesibim.com/", $keys)) {
    $keys["https://ess.epe.si/"] = $keys["https://ess.epesibim.com/"];
    unset($keys["https://ess.epesibim.com/"]);
    Variable::set('license_key', $keys);
}
?>
