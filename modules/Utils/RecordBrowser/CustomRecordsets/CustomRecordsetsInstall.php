<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 *         Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, 2014 Telaxus LLC
 * @license MIT
 * @version 1.0
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
            array('name' => 'Base/Lang', 'version' => 0),
            array('name' => 'Utils/RecordBrowser', 'version' => 0));
    }

    public static function info()
    {
        return array(
            'Description' => 'Custom Recordsets Creator',
            'Author' => 'Adam Bukowski, Arkadiusz Bisaga',
            'License' => 'MIT');
    }

    public static function simple_setup()
    {
        return array('package' => __('Custom Recordsets Creator'), 'version' => self::version);
    }

}

?>