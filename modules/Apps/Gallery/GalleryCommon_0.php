<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_GalleryCommon {
	public static function menu() {
		return array('Apps'=>array('__submenu__'=>1,'Gallery'=>array()));
	}
}
?>