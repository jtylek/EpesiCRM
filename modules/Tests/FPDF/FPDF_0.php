<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-tests
 * @subpackage fpdf
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Tests_FPDF extends Module {
	
	public function body() {
		$pdf = & $this->init_module('Libs/FPDF');
		$pdf->fpdf->AddPage();
		$pdf->fpdf->SetFont('Arial','B',16);
		$pdf->fpdf->SetFillColor(255,255,255);
		$pdf->fpdf->Cell(180,6,'Receive record','',0,'C',1);
		$pdf->fpdf->Ln();

		print('<a href="'.$pdf->get_href().'" target="_blank">TEST</a>');

		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDFInstall.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDF_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/FPDF/FPDFCommon_0.php');
	}
}
?>
