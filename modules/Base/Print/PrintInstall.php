<?php
/**
 *
 * @author      Janusz Tylek <j@epe.si>
 * @copyright  Janusz Tylek
 * @license    MIT
 * @version    1.5.0
 * @package    epesi-base
 * @subpackage Print
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_PrintInstall extends ModuleInstall
{
    const version = '1.5.0';

    public function install()
    {
        Base_ThemeCommon::install_default_theme($this->get_type());
        ModuleManager::include_common($this->get_type(), 0);
        Base_PrintCommon::register_document_type(new Base_Print_Document_HTML());
        Base_PrintCommon::register_document_type(new Base_Print_Document_PDF());
        return true;
    }

    public function uninstall()
    {
        Base_ThemeCommon::uninstall_default_theme($this->get_type());
        Variable::delete('printers_registered', false);
        Variable::delete('print_document_types', false);
        Variable::delete('print_href_callback', false);
        Variable::delete('print_disabled_templates', false);
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
            array('name' => Base_ThemeInstall::module_name(), 'version' => 0),
            array('name' => Libs_TCPDFInstall::module_name(), 'version' => 0)
        );
    }

    public static function info()
    {
        return array(
            'Description' => 'Printing mechanism',
            'Author'      => ' Janusz Tylek <j@epe.si>',
            'License'     => 'MIT');
    }

    public static function simple_setup()
    {
        return false;
    }

}

?>