<?php
/**
 * Cron Epesi
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_CronCommon extends ModuleCommon {
	public static function admin_caption() {
		return array('label'=>__('Cron'), 'section'=>__('Server Configuration'));
	}	
}

?>
