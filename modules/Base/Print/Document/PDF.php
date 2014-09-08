<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Print_Document_PDF extends Base_Print_Document_Document
{
    private $pdf;
    private $content_length;

    public function document_type_name()
    {
        return 'PDF';
    }

    public function init($data)
    {
        $this->pdf = Libs_TCPDFCommon::new_pdf();
        Libs_TCPDFCommon::prepare_header($this->pdf);
        Libs_TCPDFCommon::add_page($this->pdf);
        $this->set_filename_extension('pdf');
    }

    public function write_text($html)
    {
        Libs_TCPDFCommon::writeHTML($this->pdf, $html, false);
    }

    public function get_output()
    {
        $this->append_footers();
        $content = Libs_TCPDFCommon::output($this->pdf);
        $this->content_length = strlen($content);
        return $content;
    }

    protected function append_footers()
    {
        if (!$this->get_footer()) {
            return;
        }
        $margins = $this->pdf->getOriginalMargins();
        $pages_total = $this->pdf->getNumPages();
        $page_width = $this->pdf->getPageWidth();
        $footer_width = $page_width - $margins['left'] - $margins['right'];
        $footer_y = $this->pdf->getPageHeight() - $this->pdf->getFooterMargin() + 5;
        for ($page = 1; $page <= $pages_total; $page++) {
            $this->pdf->SetPage($page);
            $this->pdf->SetAutoPageBreak(false);
            $this->pdf->WriteHTMLCell($footer_width, $this->pdf->getFooterMargin(),
                                      $margins['left'], $footer_y,
                                      $this->get_footer(), false, 0, false);
        }
    }

    protected function find_footer_height()
    {
        $footer_margin_orig = $this->pdf->getFooterMargin();
        $footer_margin = $footer_margin_orig;
        $this->pdf->setFooterMargin(0);
        $this->pdf->SetAutoPageBreak(true, 0);
        $text = $this->get_footer();
        $margins = $this->pdf->getOriginalMargins();
        $page_height = $this->pdf->getPageHeight();
        $page_width = $this->pdf->getPageWidth();
        $footer_width = $page_width - $margins['left'] - $margins['right'];
        $success = false;
        while (!$success) {
            $tmppdf = clone($this->pdf);
            $pages = $tmppdf->getNumPages();
            if ($footer_margin * 2 > $page_height) {
                throw new ErrorException('Your footer is too long');
            }
            $footer_y = $page_height - $footer_margin;
            $tmppdf->SetPage($tmppdf->getNumPages());
            $tmppdf->WriteHTMLCell($footer_width, $footer_margin,
                                   $margins['left'], $footer_y,
                                   $text, false, 0, false);
            if ($pages == $tmppdf->getNumPages()) {
                $success = true;
            } else {
                $footer_margin += 5;
            }
            unset($tmppdf);
        }
        // add some static value to set more space
        $footer_margin += 10;
        $this->pdf->setFooterMargin($footer_margin);
        $this->pdf->SetAutoPageBreak(true, $footer_margin);
    }

    public function set_footer($text)
    {
        parent::set_footer($text);
        $this->find_footer_height();
    }

    public function http_headers()
    {
        parent::http_headers();
        if ($this->content_length === null) {
            throw new ErrorException('Call get_output first to calculate output length');
        }
        $filename = $this->get_filename_with_extension();
        header('Content-Type: application/pdf');
        header('Content-Length: ' . $this->content_length);
        header('Cache-Control: no-cache');
        header('Content-disposition: inline; filename="' . $filename . '"');
    }


}
