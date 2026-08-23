<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Print_Document_HTML extends Base_Print_Document_Document
{
    private $text;

    public function __construct()
    {
        $this->text = '';
    }

    public function document_type_name()
    {
        return 'HTML';
    }

    public function write_text($html)
    {
        $this->text .= $html;
    }

    public function get_output()
    {

        return $this->html_header() . $this->text . $this->html_footer();
    }

    protected function html_header()
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head><body>';
    }

    protected function html_footer()
    {
        $footer = '<div class="footer">' . $this->get_footer() . '</div>';
        return $footer . '</body></html>';
    }

}
