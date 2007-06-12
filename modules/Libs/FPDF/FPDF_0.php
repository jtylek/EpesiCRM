<?php
/**
 * FPDF class.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package tcms-libs
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * @package tcms-libs
 * @subpackage fpdf
 */
 
class Libs_FPDF extends Module {
	public $fpdf;

	public function construct($orientation='P',$unit='mm',$format='A4') {
		require_once("modules/Libs/FPDF/fpdf153.php");
		$this->fpdf = new FPDF($orientation, $unit, $format);
	}	

	public function body($arg) {
	}
	
	public function get_href($filename) {
		global $base;
		$pdf_id = $this->get_path();
		$this->set_module_variable('pdf', $this->fpdf->Output('','S'));
		if(!isset($filename)) $filename='download.pdf';
		return 'modules/Libs/FPDF/download.php?'.http_build_query(array('id'=>$base->get_client_id(),'pdf'=>$pdf_id,'filename'=>$filename));
	}
}

?>
