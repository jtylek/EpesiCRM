<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage image
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_Image extends Module {
	
	public function body() {
		print '<table>';
		for($row = 0; $row < 5; $row++) {
			print '<tr>';
			for($column = 0; $column < 4; $column++) {
				$id = $row*5+$column + 1;
				print '<td>';
				//print_r($attr);
				Utils_ImageCommon::display_thumb("modules/Tests/Image/image_".$id.".jpg",120);
				print '</td>';
			}
			print '</tr>';
		}
		print '</table>';

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/ImageInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/Image_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/ImageCommon_0.php');
	}
}
?>



