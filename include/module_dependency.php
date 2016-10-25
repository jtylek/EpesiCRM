<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 0.1
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
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
    private $compare_max;

    private function __construct($module_name, $version_min, $version_max, $version_max_is_ok = true) {
        $this->module_name = $module_name;
        $this->version_min = $version_min;
        $this->version_max = $version_max;
        $this->compare_max = $version_max_is_ok ? '<=' : '<';
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
     * @param string $version version string comparable with version_compare
     * @return bool does version satisfy dependency 
     */
    function is_satisfied_by($version) {
        return ($this->version_min === null
                || version_compare($version, $this->version_min, '>='))
                && ($this->version_max === null
                || version_compare($version, $this->version_max, $this->compare_max));
    }

    /**
     * Create dependency of module starting from version specified to last major
     * revision of this version.
     * 
     * Dependency::requires('Base', '1.2.1') - means all starting from 1.2.1
     * to 1.x.y are ok. 1.99.99 is ok but 2.0 is not.
     * @param string $module module name
     * @return Dependency
     */
    static function requires($module, $version) {
        $last_version = null;
        if (preg_match('(\d+)', $version, $matches)) {
            $last_version = (intval($matches[0])+1) . '.smallest';
        }
        return new self($module, $version, $last_version, false);
    }

    /**
     * Create dependency of module in exact version
     * @param string $module module name
     * @param string $version version string comparable with version_compare
     * @return Dependency
     */
    static function requires_exact($module, $version) {
        return new self($module, $version, $version);
    }

    /**
     * Create dependency of module in at least version supplied
     * @param string $module module name
     * @param string $version version string comparable with version_compare
     * @return Dependency
     */
    static function requires_at_least($module, $version) {
        return new self($module, $version, null);
    }

    /**
     * Create dependency of module in range of versions inclusive
     * @param string $module module name
     * @param string $version_min version string comparable with version_compare
     * @param string $version_max version string comparable with version_compare
     * @param bool $version_max_is_ok when true version specified in previous
     * argument is last working. When false version_max is first which does not
     * work.
     * @return Dependency
     */
    static function requires_range($module, $version_min, $version_max, $version_max_is_ok = true) {
        return new self($module, $version_min, $version_max, $version_max_is_ok);
    }

}
