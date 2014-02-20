<?php defined("_VALID_ACCESS") || die('Direct access forbidden');


class Base_Print_Template_Template
{
    private $sections = array();

    public function print_section($section_name, $section)
    {
        if (!$this->isset_section_template($section_name)) {
            return '';
        }
        $tpl = $this->get_section_template($section_name);
        return $tpl->fetch($section);
    }

    public function get_section_template($name)
    {
        return $this->sections[$name];
    }

    public function set_section_template($name,
                                         Base_Print_Template_Section $template)
    {
        $this->sections[$name] = $template;
    }

    public function isset_section_template($name)
    {
        return isset($this->sections[$name]);
    }

}
