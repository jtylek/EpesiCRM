<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek
 *          Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2008, 2014 Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_CustomRecordsetsCommon extends ModuleCommon
{
    public static $sep = '_##_';

    public static function admin_caption()
    {
        return array('label' => __('Custom Recordsets'), 'section' => __('Data'));
    }

    public static function menu()
    {
        $tabs = DB::GetAssoc('SELECT menu, tab FROM recordbrowser_custom_recordsets WHERE active=1');
        $result = array();
        foreach ($tabs as $k => $v) {
            if (!$k) continue;
            if (!Utils_RecordBrowserCommon::get_access($v, 'browse')) continue;
            $k = explode(self::$sep, $k);
            $menu = self::build_menu($k, array('tab' => $v));
            $result = array_merge_recursive($menu, $result);
        }
        return $result;
    }

    public static function build_menu($arr, $vars = array(), $cur = array())
    {
        $label = array_pop($arr);
        if (!empty($cur)) $cur = array($label => array('__submenu__' => 1) + $cur);
        else $cur = array($label => $vars);
        if (empty($arr)) return $cur;
        return self::build_menu($arr, array(), $cur);
    }
}

?>