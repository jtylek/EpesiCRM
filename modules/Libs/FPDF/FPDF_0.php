<?php
/**
 * FPDFInstall class.
 * 
 * This module uses FPDF library released as freeware
 * version 1.53
 * author Olivier PLATHEY
 * Modifications by Paul Bukowski (pbukowski@telaxus.com). Do not send bug reports to author.
 * link http://www.fpdf.org homesite
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage fpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_FPDF extends Module {
	public $fpdf;

	public function construct($orientation='P',$unit='mm',$format='A4') {
		require_once('fpdf153.php');
		$this->fpdf = new FPDF($orientation, $unit, $format);
	}	

	public function body() {
	}
	
	public function get_href($filename=null) {
		$pdf_id = $this->get_path();
		$this->set_module_variable('pdf', $this->fpdf->Output('','S'));
		if(!isset($filename)) $filename='download.pdf';
		return 'modules/Libs/FPDF/download.php?'.http_build_query(array('id'=>CID,'pdf'=>$pdf_id,'filename'=>$filename));
	}
}

?>
