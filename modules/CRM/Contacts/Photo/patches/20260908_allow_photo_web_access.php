<?php

/**
 * data/.htaccess (modules/Base/patches/20260908_protect_data_dir.php) denies the whole
 * data/ tree to the webserver, but submit_contact() (PhotoCommon_0.php) assigns photo_src
 * as a plain data/CRM_Contacts_Photo/... path, rendered directly as
 * <img src="{$photo_src}"> (theme/Contact.tpl), so that patch broke contact photos on any
 * install that had one. Grant this one directory back its web access.
 *
 * @package epesi-crm
 * @subpackage contacts-photo
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = DATA_DIR . '/CRM_Contacts_Photo';
if (!is_dir($dir)) mkdir($dir);

$path = $dir . '/.htaccess';
if (!file_exists($path)) {
    file_put_contents($path, <<<'HTACCESS'
<ifModule mod_authz_core.c>
    Require all granted
</ifModule>
<ifModule !mod_authz_core.c>
    Allow from all
</ifModule>

HTACCESS
    );
}
