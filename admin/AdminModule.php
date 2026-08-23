<?php

require_once(__DIR__ . '/AdminSmarty.php');

/**
 * Simple admin module to display some html.
 *
 * @author  Janusz Tylek <j@epe.si>
 */
abstract class AdminModule {

    abstract function menu_entry();

    abstract function body();

    // Bootstrap Icons class for this module's sidebar entry (admin/templates/
    // layout.tpl) - each concrete module owns its own icon the same way it
    // already owns menu_entry(), so a new module just overrides this instead
    // of a central lookup table having to know every module by name.
    function icon() {
        return 'bi-tools';
    }

    // Overridden by a module whose sidebar/dashboard entry should link
    // straight to some other URL instead of the normal ?module=<name>
    // in-page routing - e.g. UpdateEpesi, which launches the separate
    // update.php script rather than rendering inside this page's own card.
    function href() {
        return null;
    }

    function required_epesi_modules() {
        return array();
    }

    function access_admin() {
        return false;
    }

    function access_user() {
        return false;
    }

    protected function render($template, array $vars = array()) {
        return AdminSmarty::render($template, $vars);
    }
}

?>