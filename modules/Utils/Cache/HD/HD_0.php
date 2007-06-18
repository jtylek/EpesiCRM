<?php
/**
 * Cache class.
 * 
 * Displays file content
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.4
 * @licence SPL
 * @package epesi-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Displays file content
 * 
 * @package epesi-utils
 * @subpackage Cache
 */
class Utils_Cache_HD extends Utils_Cache_Base {
	public function body($arg) {
	}
	
	protected function _in_cache() {
		$file = $this->get_data_dir().$this->id;
		$ret = file_exists($file) && (time()-filemtime($file))<$this->interval;
		return $ret;
	}
	
	protected function _load() {
		$file = $this->get_data_dir().$this->id;
		return file_get_contents($file);
	}

	protected function _save($str) {
		$file = $this->get_data_dir().$this->id;
		file_put_contents($file,$str);
	}
}
?>
