<?php
/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 0.1
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
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

    /**
     * Required module name.
     * @return string module name with slashes. eg Base/ActionBar
     */
    function get_module_name() {
        return $this->module_name;
    }

    /**
     * Minimum version of required module
     * @return string alphabetical comparable version string
     */
    function get_version_min() {
        return $this->version_min;
    }

    /**
     * Maximum version of required module
     * @return string alphabetical comparable version string
     */
    function get_version_max() {
        return $this->version_max;
    }

    /**
     * Check if version satisfies dependency
     * @param string $version alphabetical comparable version string
     * @return bool does version satisfy dependency 
     */
    function is_satisfied_by($version) {
        return ($this->version_min === null || $version >= $this->version_min) &&
                ($this->version_max === null || $version <= $this->version_max);
    }

    /**
     * Create dependency of module in any version
     * @param string $module module name
     * @return Dependency
     */
    static function requires($module) {
        return new self($module, null, null);
    }

    /**
     * Create dependency of module in exact version
     * @param string $module module name
     * @param string $version alphabetical comparable version string
     * @return Dependency
     */
    static function requires_exact($module, $version) {
        return new self($module, $version, $version);
    }

    /**
     * Create dependency of module in at least version supplied
     * @param string $module module name
     * @param string $version alphabetical comparable version string
     * @return Dependency
     */
    static function requires_at_least($module, $version) {
        return new self($module, $version, null);
    }

    /**
     * Create dependency of module in range of versions inclusive
     * @param string $module module name
     * @param string $version_min alphabetical comparable version string
     * @param string $version_max alphabetical comparable version string
     * @return Dependency
     */
    static function requires_range($module, $version_min, $version_max) {
        return new self($module, $version_min, $version_max);
    }

}

?>