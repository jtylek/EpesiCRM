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

    // Live connection charset, not a config.php constant - DB::Connect()
    // (include/database.php) hardcodes MySQL connections to utf8mb4 (see
    // AI-shared/MIGRATION_NOTES.md §68's legacy-utf8/emoji-mangling fix and
    // patches/20260814_utf8mb4_migration.php), so this is what actually
    // catches an install that hasn't had that patch applied yet - a stale
    // "utf8" here means table data can still silently mangle 4-byte
    // characters (emoji) even though the code migrated. Not meaningful for
    // PostgreSQL (DB::Connect() never calls SET NAMES for it - separate
    // bytea_output setting instead), so shown as N/A there rather than
    // querying a driver-specific concept that doesn't apply.
    private function database_charset_row() {
        if (!DB::is_mysql())
            return $this->row('Database Charset:', 'N/A ('.DATABASE_DRIVER.')', true, false);
        $charset = DB::GetCharSet();
        return $this->row('Database Charset:', $charset, strtolower($charset) === 'utf8mb4');
    }

    private function config_rows() {
        $yn = function($v) { return $v ? 'YES' : 'NO'; };
        return array(
            $this->row('epesi version:', EPESI_VERSION),
            $this->row('epesi revison:', EPESI_REVISION),
            $this->row('Database Name:', DATABASE_NAME),
            $this->row('Database Driver:', DATABASE_DRIVER),
            $this->database_charset_row(),
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
            $this->row('Force cache common files: ', $yn(FORCE_CACHE_COMMON_FILES)),
            // Configured vs. actually selected. The two can differ - a pinned driver
            // whose extension is missing degrades down Cache::driver_chain() - and the
            // difference is exactly what you want to see on a "why is this slow" visit,
            // so show the driver in use rather than only what config.php asked for.
            $this->row('Cache driver (configured): ', CACHE_TYPE),
            $this->row('Cache driver (in use): ', Cache::active_driver() ?: 'none'),
            $this->row('Asset version check (stale-tab reload prompt): ', $yn(ASSET_VERSION_CHECK)),
            $this->row('Currency rate auto-fetch: ', $yn(CURRENCY_RATE_AUTO_FETCH)),
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
        $checks = CompatibilityCheck::environment_checks();
        $db_settings = CompatibilityCheck::database_settings_check();
        if ($db_settings) $checks[] = $db_settings;
        return $this->render('ConfigInfo.tpl', array(
            'checks' => $checks,
            'config_rows' => $this->config_rows(),
        ));
    }

    public function menu_entry() {
        return __("Configuration");
    }

    public function icon() {
        return 'bi-clipboard-data';
    }

}

?>
