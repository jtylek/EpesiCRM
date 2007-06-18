<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Displays file content (default: 'TODO').
 * 
 * @package epesi-tests
 * @subpackage fpdf
 */
class Tests_FPDF extends Module {
	
	public function body() {
		$pdf = $this->init_module('Libs/FPDF');
		$pdf->fpdf->AddPage();
		$pdf->fpdf->SetFont('Arial','B',16);
		$pdf->fpdf->SetFillColor(255,255,255);
		$pdf->fpdf->Cell(180,6,'Receive record','',0,'C',1);
		$pdf->fpdf->Ln();

		print('<a href="'.$pdf->get_href().'" target="_blank">TEST</a>');


		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDFInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDFInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDF_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDFCommon_0.php');
	}
	
	public static function menu() {
		return array('FPDF test'=>array());
	}

}
?>
