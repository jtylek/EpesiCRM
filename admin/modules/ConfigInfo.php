<?php

/**
 * Description of ConfigInfo
 *
 * @author ajb
 */
class ConfigInfo extends AdminModule {

    private function startTable() {
        print('<table class="listing">');
    }

    private function closeTable() {
        print('</table>');
    }

// Print 2 columns
    private function printTD($left = '&nbsp;', $right = '&nbsp;', $color = 'green', $strong = true) {
        static $rowclass = null;
        if ($rowclass != 'odd')
            $rowclass = 'odd';
        else
            $rowclass = 'even';
        if ($right == "NO") {
            $color = 'red';
        }
        $tag = $strong ? 'strong' : 'span';
        print("<tr class=\"$rowclass\"><td>$left</td><td><$tag class=\"$color\">$right</$tag></td></tr>");
    }

    private function print_info() {
        print('<div class="title">' . __('PHP environment check') . '</div>');

        $this->startTable();
        $data_dir_ok = is_writable('data');
        $data_writable = $data_dir_ok ? "OK" : '<strong>WARNING!</strong> Please fix privileges for data directory.';
        $color = $data_dir_ok ? 'green' : 'red';
        $this->printTD("Data directory is writeable", $data_writable, $color, $data_dir_ok);

        $version_ok = version_compare(phpversion(), '5.4.0') >= 0;
        $text = $version_ok ? 'OK' : '<strong>WARNING!</strong> You are running an old version of PHP, minimum version 5.4 required.';
        $color = $version_ok ? 'green' : 'red';
        $this->printTD('PHP version: ' . phpversion(), $text, $color, $version_ok);

        $curl_ok = extension_loaded('curl');
        $curl_loaded = $curl_ok ? 'OK' : 'Curl extension not found - Please uncomment <pre><strong>;extension=php_curl.dll</strong></pre> line in your php.ini';
        $color = $curl_ok ? 'green' : 'red';
        $this->printTD("Curl loaded", $curl_loaded, $color, $curl_ok);
        $this->closeTable();

        print('<br/><br/><div class="title">EPESI config.php</div>');

        $this->startTable();
        $this->printTD('epesi version:', EPESI_VERSION);
        $this->printTD('epesi revison:', EPESI_REVISION);
        $this->printTD('Database Name:', DATABASE_NAME);
        $this->printTD('Database Driver:', DATABASE_DRIVER);
        $this->printTD('epesi Local Dir:', EPESI_LOCAL_DIR);
        $this->printTD('epesi Dir:', EPESI_DIR);
        $this->printTD('epesi URL:', get_epesi_url());
        $this->printTD('System Timezone:', SYSTEM_TIMEZONE);

        $this->printTD('Debug:', (DEBUG ? 'YES' : 'NO'));
        $this->printTD('Module Times:', (MODULE_TIMES ? 'YES' : 'NO'));
        $this->printTD('Display sql queries processing times: ', (SQL_TIMES ? 'YES' : 'NO'));
        $this->printTD('Strip output html from comments: ', (STRIP_OUTPUT ? 'YES' : 'NO'));
        $this->printTD('Display additional error info: ', (DISPLAY_ERRORS ? 'YES' : 'NO'));
        $this->printTD('Report all errors (E_ALL): ', (REPORT_ALL_ERRORS ? 'YES' : 'NO'));
        $this->printTD('GZIP client web browser history: ', (GZIP_HISTORY ? 'YES' : 'NO'));

        $this->printTD('Reducing Transfer: ', (REDUCING_TRANSFER ? 'YES' : 'NO'));
        $this->printTD('Minify Encode: ', (MINIFY_ENCODE ? 'YES' : 'NO'));
        $this->printTD('Minify sources: ', (MINIFY_SOURCES ? 'YES' : 'NO'));
        $this->printTD('Suggest Donation: ', (SUGGEST_DONATION ? 'YES' : 'NO'));
        $this->printTD('Check epesi version: ', (CHECK_VERSION ? 'YES' : 'NO'));
        $this->printTD('JS Output: ', (JS_OUTPUT ? 'YES' : 'NO'));
        $this->printTD('Set Session: ', (SET_SESSION ? 'YES' : 'NO'));

        $this->printTD('Read Only Session: ', (READ_ONLY_SESSION ? 'YES' : 'NO'));
        $this->printTD('First Run: ', (FIRST_RUN ? 'YES' : 'NO'));
        $this->printTD('Hosting Mode: ', (HOSTING_MODE ? 'YES' : 'NO'));
        $this->printTD('Trial Mode: ', (TRIAL_MODE ? 'YES' : 'NO'));
        $this->printTD('Demo Mode: ', (DEMO_MODE ? 'YES' : 'NO'));
        $this->closeTable();
    }

    public function body() {
        ob_start();
        $this->print_info();
        return ob_get_clean();
    }

    public function menu_entry() {
        return __("PHP environment & config.php");
    }

}

?>
