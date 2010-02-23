<?php
/**
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2010, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage ModuleDownloader
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ModuleDownloaderCommon extends Base_AdminModuleCommon {
	public static function admin_caption() {
		return 'Download modules';
	}
}
?>
