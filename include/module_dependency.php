<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides dependency requirements
 * @package epesi-base
 * @subpackage module 
 */
class Dependency {

    private $module_name;
    private $version_min;
    private $version_max;

    private function __construct($module_name, $version_min, $version_max) {
        $this->module_name = $module_name;
        $this->version_min = $version_min;
        $this->version_max = $version_max;
    }

    function get_module_name() {
        return $this->module_name;
    }

    function get_version_min() {
        return $this->version_min;
    }

    function get_version_max() {
        return $this->version_max;
    }

    function is_satisfied_by($version) {
        return ($this->version_min === null || $version >= $this->version_min) &&
                ($this->version_max === null || $version <= $this->version_max);
    }

    static function requires($module) {
        return new self($module, null, null);
    }

    static function requires_exact($module, $version) {
        return new self($module, $version, $version);
    }

    static function requires_at_least($module, $version) {
        return new self($module, $version, null);
    }

    static function requires_range($module, $version_min, $version_max) {
        return new self($module, $version_min, $version_max);
    }

}

?>