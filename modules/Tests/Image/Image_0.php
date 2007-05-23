<?php
/**
 * WizardTest class.
 * 
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-utils
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides functions for presenting data in a table (suports sorting 
 * by different columns and splitting results -- showing 10 rows per page).
 * @package tcms-utils
 * @subpackage generic-browse
 */
class Tests_Image extends Module {
	
	public function body($arg) {
		$this->pack_module('Utils/Tooltip', array('Image Test2', 'Test module for new Image module', 'TooltipD'));
		print "<hr>";
		$image = & $this->init_module('Utils/Image');
		print '<table>';
		for($row = 0; $row < 5; $row++) {
			print '<tr>';
			for($column = 0; $column < 4; $column++) {
				$id = $row*5+$column + 1;
				$image->load("modules/Tests/Image/image_".$id.".jpg");
				$attr = $image->get_thumb_attributes();
				print '<td width="'.$attr[0].'" height="'.$attr[1].'">';
				//print_r($attr);
				$image->display_thumb(120);
				print '</td>';
			}
			print '</tr>';
		}
		print '</table>';

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/ImageInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/ImageInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/Image_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Image/ImageCommon_0.php');
	}
}
?>



