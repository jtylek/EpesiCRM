<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Print_Template_SectionFromFile extends
    Base_Print_Template_Section
{

    private $tpl_file;

    function __construct($tpl_file)
    {
        $this->set_tpl_file($tpl_file);
    }

    public function set_tpl_file($tpl_file)
    {
        $this->tpl_file = $tpl_file;
    }

    public function get_tpl_file()
    {
        return $this->tpl_file;
    }

    public function fetch($section)
    {
        $filename = $this->get_tpl_file();
        ob_start();
        Base_ThemeCommon::display_smarty($section, '', $filename, true);
        $text = ob_get_clean();
        return $text;
    }

    public function get_tpl()
    {
        $file = $this->get_tpl_file() . '.tpl';
        $file = Base_ThemeCommon::get_template_file($file);
        return $file ? file_get_contents($file) : '';
    }

}
