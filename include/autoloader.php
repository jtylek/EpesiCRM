<?php

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */

/*
 * Use below defines to debug autoloading feature.
 * 'count' - count called includes for specific class name
 * 'backtrace' - stores backtraces to track place which called autoloader
 * 
 * You can see debug info only when it's enabled.
 * Open your browser to this file.
 * EPESI_ADDRESS/include/autoloader.php
 */
//define('DEBUG_AUTOLOADS', 'count');
//define('DEBUG_AUTOLOADS', 'place');

if (defined('DEBUG_AUTOLOADS') && DEBUG_AUTOLOADS
        && !defined("_VALID_ACCESS")) {
    define('CID', false);
    require_once '../include.php';

    print("<pre>");
    if (isset($_GET['clear'])) {
        unset($_SESSION['debug_autoloads']);
        print "Memory cleared\n";
        print "<a href=\"autoloader.php\">Back</a>";
        return;
    }
    print("click <a href=\"?clear=1\">here</a> to clear stored data\n\n");
    ob_start();
    if (isset($_SESSION['debug_autoloads'])) {
        if (DEBUG_AUTOLOADS == 'count') {
            var_dump($_SESSION['debug_autoloads']);
        } elseif (DEBUG_AUTOLOADS == 'place') {
            foreach ($_SESSION['debug_autoloads'] as $class => $places) {
                print($class . "\n");
                foreach ($places as $place) {
                    print "\t$place\n";
                }
                print("<hr>");
            }
        }
    }
    $data = ob_get_clean();
    if (!$data)
        $data = "No data stored. Refresh EPESI.";
    print($data);
    print("</pre>");
    return;
}

defined("_VALID_ACCESS") || die("Direct access forbidden<br>Define DEBUG_AUTOLOADS and come back here.");

class ModulesAutoloader {

    private static $instance = null;

    public static function enable($with_hooks = true) {
        if (self::$instance === null) {
            self::$instance = new ModulesAutoloader();
            if ($with_hooks)
                self::$instance->hooks();
        }
    }

    public static function disable() {
        if (self::$instance)
            unset(self::$instance);
    }

    private function __construct() {
        spl_autoload_register(array($this, 'autoload'));
    }

    public function __destruct() {
        spl_autoload_unregister(array($this, 'autoload'));
    }

    private function hooks() {
        // These are hooks to load, when loading common files at startup
        // is disabled. There is possibility, that more common files should
        // be loaded to proper EPESI run.
        $this->autoload_hook('Base_LangCommon');
        $this->autoload_hook('Libs_QuickFormCommon');
        $this->autoload_hook('Utils_GenericBrowserCommon');
        $this->autoload_hook('Libs_ScriptAculoUsCommon');
        $this->autoload_hook('Base_BoxCommon');
        $this->autoload_hook('Base_HomePageCommon');
        $this->autoload_hook('Develop_MiscUtilsCommon');
    }

    private function autoload_hook($class_name) {
        if (!class_exists($class_name, false))
            $this->autoload($class_name);
    }

    private function get_calling_place() {
        $db = debug_backtrace();
        $autoload_called = false;
        foreach ($db as $entry) {
            if (isset($entry['function']) &&
                    ($entry['function'] == 'spl_autoload_call' || $entry['function'] == 'autoload'))
                $autoload_called = true;
            if ($autoload_called && isset($entry['file']) && $entry['file'] && isset($entry['line'])) {
                return $entry['file'] . ':' . $entry['line'];
            }
        }
        return 'not found';
    }

    private function autoload($class_name) {
        if (defined('DEBUG_AUTOLOADS') && DEBUG_AUTOLOADS) {
            if (!isset($_SESSION['debug_autoloads']))
                $_SESSION['debug_autoloads'] = array();
            if (DEBUG_AUTOLOADS == 'count') {
                if (!isset($_SESSION['debug_autoloads'][$class_name])
                        || !is_numeric($_SESSION['debug_autoloads'][$class_name]))
                    $_SESSION['debug_autoloads'][$class_name] = 0;
                $_SESSION['debug_autoloads'][$class_name] += 1;
            } elseif (DEBUG_AUTOLOADS == 'place') {
                if (!isset($_SESSION['debug_autoloads'][$class_name])
                        || !is_array($_SESSION['debug_autoloads'][$class_name]))
                    $_SESSION['debug_autoloads'][$class_name] = array();
                $_SESSION['debug_autoloads'][$class_name][] = $this->get_calling_place();
            }
        }

        if ($this->module_common($class_name))
            return true;

        if ($this->module_main($class_name))
            return true;

        if ($this->class_from_modules($class_name))
            return true;

        if (isset($this->autoload_hooks[$class_name])) {
            require_once $this->autoload_hooks[$class_name];
            return 'hooked';
        }
        return false;
    }

    private function class_from_modules($class_name) {
        $file = 'modules' . DIRECTORY_SEPARATOR;
        $file .= str_replace('_', DIRECTORY_SEPARATOR, $class_name);
        $file .= '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        return false;
    }

    private function module_common($class_name) {
        if (substr($class_name, -6) != 'Common')
            return false;

        $module_name = substr($class_name, 0, -6);
        if (isset(ModuleManager::$modules[$module_name])) {
            ModuleManager::include_common($module_name, ModuleManager::$modules[$module_name]);
            // here return true to prevent further file searching.
            // it should exists such file in module but it doesn't
            return true;
        }
        return false;
    }

    private function module_main($class_name) {
        if (isset(ModuleManager::$modules[$class_name])) {
            return ModuleManager::include_main($class_name, ModuleManager::$modules[$class_name]);
        }
        return false;
    }

    /*
     * Below are class names with files where they are defined.
     * This is used to locate file and load it if it is in strange place.
     */
    private $autoload_hooks =
            array(
        'HTML_Common' => 'modules/Libs/QuickForm/3.2.11/HTML/Common.php',
        'HTML_QuickForm' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm.php',
        'HTML_QuickForm_Error' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm.php',
        'HTML_QuickForm_Renderer' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm/Renderer.php',
        'HTML_QuickForm_Renderer_TCMSArray' => 'modules/Libs/QuickForm/Renderer/TCMSArray.php',
        'HTML_QuickForm_Renderer_TCMSArraySmarty' => 'modules/Libs/QuickForm/Renderer/TCMSArraySmarty.php',
        'HTML_QuickForm_Renderer_TCMSDefault' => 'modules/Libs/QuickForm/Renderer/TCMSDefault.php',
        'HTML_QuickForm_autocomplete' => 'modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.php',
        'HTML_QuickForm_automulti' => 'modules/Libs/QuickForm/FieldTypes/automulti/automulti.php',
        'HTML_QuickForm_autoselect' => 'modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.php',
        'HTML_QuickForm_element' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm/element.php',
        'HTML_QuickForm_input' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm/input.php',
        'HTML_QuickForm_select' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm/select.php',
        'HTML_QuickForm_text' => 'modules/Libs/QuickForm/3.2.11/HTML/QuickForm/text.php',
    );

}

?>
