<?php
/**
 * @author      Janusz Tylek <j@epe.si>
 * @copyright  Copyright &copy; 2015, Janusz Tylek
 * @version    1.0
 * @license    MIT
 * @package    epesi-utils
 * @subpackage QueryBuilder
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_QueryBuilderInstall extends ModuleInstall
{
    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function version()
    {
        return array('1.0.0');
    }

    public function requires($v)
    {
        return array();
    }
}
