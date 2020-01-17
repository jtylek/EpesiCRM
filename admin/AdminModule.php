<?php

/**
 * Simple admin module to display some html.
 * 
 * @author  Janusz Tylek <j@epe.si>
 */
abstract class AdminModule {

    abstract function menu_entry();

    abstract function body();
    
    function required_epesi_modules() {
        return array();
    }
    
    function access_admin() {
        return false;
    }
    
    function access_user() {
        return false;
    }
}

?>