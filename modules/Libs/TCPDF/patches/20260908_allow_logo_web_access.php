<?php

/**
 * data/.htaccess (modules/Base/patches/20260908_protect_data_dir.php) denies the whole
 * data/ tree to the webserver, but admin()'s logo preview (TCPDF_0.php) renders the uploaded
 * PDF logo as a plain <img src="data/Libs_TCPDF/company_logo.png?..."> URL, so that patch broke
 * it on any install with a custom PDF logo uploaded. Grant just that one filename back its web
 * access - unlike MainModuleIndicator/CRM_Contacts_Photo's equivalent fix, this directory also
 * holds generated PDFs (TCPDF_0.php's get_href()/full_path() - already served safely through
 * download.php, never linked directly) whose names are only an md5 of path+user+CID+session_id,
 * not a real secret. A directory-wide allow (like the other two fixes) would make those openly
 * fetchable too, so this scopes the allow to the logo filename specifically via <Files>.
 *
 * @package epesi-libs
 * @subpackage tcpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$dir = DATA_DIR . '/Libs_TCPDF';
if (!is_dir($dir)) mkdir($dir);

$path = $dir . '/.htaccess';
if (!file_exists($path)) {
    file_put_contents($path, <<<'HTACCESS'
<Files "company_logo.png">
    <ifModule mod_authz_core.c>
        Require all granted
    </ifModule>
    <ifModule !mod_authz_core.c>
        Allow from all
    </ifModule>
</Files>

HTACCESS
    );
}
