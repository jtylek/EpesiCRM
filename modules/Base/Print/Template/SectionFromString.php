<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Print_Template_SectionFromString extends
    Base_Print_Template_Section
{

    private $template_dir;

    private $template_dir_printing = 'printing';

    private $tpl;

    function __construct($tpl)
    {
        $this->set_tpl($tpl);
    }

    public function set_tpl($tpl)
    {
        $this->tpl = $tpl;
    }

    public function get_tpl()
    {
        return $this->tpl;
    }

    public function fetch($section)
    {
        $this->set_template_dir($section->template_dir);
        $filename = $this->create_section_tpl_file();
        if (DEMO_MODE || HOSTING_MODE) {
            $section->security = true;
            $section->security_settings['PHP_TAGS'] = false;
        }
        $text = $section->fetch($filename);
        return $text;
    }

    private function set_template_dir($dir, $internal_dir = 'printing')
    {
        $this->template_dir_printing = trim($internal_dir, '/\\');
        $this->template_dir = trim($dir, '/\\');
        $final_dir = $this->template_dir . '/' . $this->template_dir_printing;
        if (file_exists($final_dir)) {
            if (!is_dir($final_dir)) {
                $msg =
                    "Cannot create directory $final_dir, because it's a file";
                throw new ErrorException($msg);
            }
        } else {
            if (!mkdir($final_dir, 0777, true)) {
                $msg = "Cannot create directory $final_dir";
                throw new ErrorException($msg);
            }
        }
    }


    private function get_template_dir($full_path = false)
    {
        $ret = '';
        if ($full_path) {
            $ret .= $this->template_dir . '/';
        }
        $ret .= $this->template_dir_printing;
        return $ret;
    }

    private function create_section_tpl_file()
    {
        $filename = sha1($this->get_tpl()) . '.tpl';
        $tpl_file = $this->get_template_dir(true) . "/$filename";
        if (!file_exists($tpl_file)) {
            file_put_contents($tpl_file, $this->get_tpl());
        }
        return $this->get_template_dir() . "/$filename";
    }

}