<?php
/**
 * @author Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage FileDownload
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileDownloadCommon extends ModuleCommon {
	public static function destroy($path,$vars) {
		DB::Execute('DELETE FROM utils_filedownload_files WHERE id=%d OR posted_on<%T',array($vars['download_id'],date('Y-m-d G:i:s',time()-120)));
	}

}

?>