<?php

/**
 * data/.htaccess (modules/Base/patches/20260908_protect_data_dir.php) denies the whole
 * data/ tree to the webserver, but logo()/login_logo() (MainModuleIndicator_0.php) render
 * the uploaded logo as a plain <img src="data/Base_MainModuleIndicator/..."> URL
 * (theme/logo.tpl, theme/login-logo.tpl), so that patch broke custom logos on any install
 * that had one. Grant this one directory back its web access.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = DATA_DIR . '/Base_MainModuleIndicator';
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
