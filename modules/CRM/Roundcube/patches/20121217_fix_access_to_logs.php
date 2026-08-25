<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

$htaccess = DATA_DIR . '/CRM_Roundcube/.htaccess';
if (!file_exists($htaccess)) {
    $f = fopen($htaccess, 'w');
    if ($f === false) {
        throw new Exception("Cannot create .htaccess file ($htaccess). "
                . "Your Roundcube logs may be available on the internet!");
    } else {
        fwrite($f, "deny from all\n");
        fclose($f);
    }
}
?>