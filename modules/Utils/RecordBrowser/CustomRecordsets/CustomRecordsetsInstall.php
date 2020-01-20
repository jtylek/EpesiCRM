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

class Utils_RecordBrowser_CustomRecordsetsInstall extends ModuleInstall
{
    const version = '1.0';

    public function install()
    {
        DB::CreateTable('recordbrowser_custom_recordsets',
            'id I AUTO KEY,' .
            'active I1,' .
            'tab C(64),' .
            'menu C(255) DEFAULT \'\'',
            array('constraints' => ''));
        return true;
    }

    public function uninstall()
    {
        DB::DropTable('recordbrowser_custom_recordsets');
        return true;
    }

    public function version()
    {
        return array(self::version);
    }

    public function requires($v)
    {
        return array(
            array('name' => Base_LangInstall::module_name(), 'version' => 0),
            array('name' => Utils_RecordBrowserInstall::module_name(), 'version' => 0));
    }

    public static function info()
    {
        return array(
            'Description' => 'Custom Recordsets Creator',
            'Author' => 'Janusz Tylek, Arkadiusz Bisaga',
            'License' => 'MIT');
    }

    public static function simple_setup()
    {
        return array('package' => __('EPESI Core'));
    }

}

?>