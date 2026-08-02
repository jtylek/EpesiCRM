<?php

require_once('include/compatibility_check.php');

/**
 * Description of ConfigInfo
 *
 * @author ajb
 */
class ConfigInfo extends AdminModule {

    private function row($label, $value, $ok = true, $strong = true) {
        // Mirrors the original printTD()'s override: any row whose value is
        // literally "NO" always renders red, regardless of the passed $ok -
        // several config rows below pass a bare YES/NO string with no
        // separate ok flag of their own.
        $class = ($value === 'NO' || !$ok) ? 'text-danger' : 'text-success';
        return array('label' => $label, 'value' => $value, 'class' => $class, 'strong' => $strong);
    }

    private function config_rows() {
        $yn = function($v) { return $v ? 'YES' : 'NO'; };
        return array(
            $this->row('epesi version:', EPESI_VERSION),
            $this->row('epesi revison:', EPESI_REVISION),
            $this->row('Database Name:', DATABASE_NAME),
            $this->row('Database Driver:', DATABASE_DRIVER),
            $this->row('epesi Local Dir:', EPESI_LOCAL_DIR),
            $this->row('epesi Dir:', EPESI_DIR),
            $this->row('epesi URL:', get_epesi_url()),
            $this->row('System Timezone:', SYSTEM_TIMEZONE),
            $this->row('Debug:', $yn(DEBUG)),
            $this->row('Module Times:', $yn(MODULE_TIMES)),
            $this->row('Display sql queries processing times: ', $yn(SQL_TIMES)),
            $this->row('Strip output html from comments: ', $yn(STRIP_OUTPUT)),
            $this->row('Display additional error info: ', $yn(DISPLAY_ERRORS)),
            $this->row('Report all errors (E_ALL): ', $yn(REPORT_ALL_ERRORS)),
            $this->row('GZIP client web browser history: ', $yn(GZIP_HISTORY)),
            $this->row('Reducing Transfer: ', $yn(REDUCING_TRANSFER)),
            $this->row('Minify Encode: ', $yn(MINIFY_ENCODE)),
            $this->row('Minify sources: ', $yn(MINIFY_SOURCES)),
            $this->row('Suggest Donation: ', $yn(SUGGEST_DONATION)),
            $this->row('Check epesi version: ', $yn(CHECK_VERSION)),
            $this->row('JS Output: ', $yn(JS_OUTPUT)),
            $this->row('Set Session: ', $yn(SET_SESSION)),
            $this->row('Read Only Session: ', $yn(READ_ONLY_SESSION)),
            $this->row('Mobile Device: ', $yn(MOBILE_DEVICE)),
            $this->row('First Run: ', $yn(FIRST_RUN)),
            $this->row('Hosting Mode: ', $yn(HOSTING_MODE)),
            $this->row('Trial Mode: ', $yn(TRIAL_MODE)),
            $this->row('Demo Mode: ', $yn(DEMO_MODE)),
        );
    }

    public function body() {
        return $this->render('ConfigInfo.tpl', array(
            'checks' => CompatibilityCheck::environment_checks(),
            'config_rows' => $this->config_rows(),
        ));
    }

    public function menu_entry() {
        return __("PHP Environment & config.php");
    }

    public function icon() {
        return 'bi-clipboard-data';
    }

}

?>
