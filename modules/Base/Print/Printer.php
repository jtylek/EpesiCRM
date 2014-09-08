<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Base_Print_Printer
{

    /** @var Base_Print_Document_Document */
    private $document;

    /** @var Base_Print_Template_Template */
    private $template;

    /**
     * Document name is a string used to identify the document type printed
     * by your printer class.
     *
     * @return string NOT translated document name, mark to translate with _M()
     */
    abstract public function document_name();

    /**
     * This method is responsible for printing document.
     *
     * Example code:
     * <code>
     * $section = $this->new_section();
     * $section->assign('data', $data);
     * $this->print_section('section_name', $section);
     * </code>
     * @param mixed $data This is a value that is passed to get_href method
     * @see new_section
     * @see print_section
     * @see set_footer
     * @return null It doesn't have to return value
     */
    abstract protected function print_document($data);

    /**
     * @return array
     */
    public function default_templates()
    {
        return array();
    }

    /**
     * Return array of sample data that may be passed to the printer.
     * This data may be used as example in the future or external modules.
     *
     * @return array Sample data
     */
    public function sample_data()
    {
        return array();
    }

    /**
     * Get href to call a print method
     *
     * Example:
     * <code>
     *  $printer = new Some_Printer_class();
     *  Base_ActionBarCommon::add('print', __('Print'), $printer->get_href($data));
     * </code>
     *
     * @param mixed $data Data that will be passed to print_document method
     *
     * @return mixed|string By default it should be a string with href="...",
     * but you can override returned value by registering your own callback
     * with Base_PrintCommon::set_print_href_callback method
     */
    public function get_href($data)
    {
        $printer = get_class($this);
        $href = Base_PrintCommon::get_print_href($data, $printer);
        return $href;
    }

    /**
     * Create a new section of document.
     *
     * @see print_document
     *
     * @return Smarty
     */
    protected function new_section()
    {
        $smarty = Base_ThemeCommon::init_smarty();
        if (DEMO_MODE || HOSTING_MODE) {
            $smarty->security = true;
            $smarty->security_settings['PHP_TAGS'] = false;
        }
        return $smarty;
    }

    /**
     * Set document object for printer.
     *
     * @param Base_Print_Document_Document $document
     */
    public function set_document(Base_Print_Document_Document $document)
    {
        $this->document = $document;
    }

    /**
     * Get document object used by printer.
     *
     * @return Base_Print_Document_Document
     */
    public function get_document()
    {
        return $this->document;
    }

    /**
     * Set template used by printer.
     *
     * @param Base_Print_Template_Template $template
     */
    public function set_template(Base_Print_Template_Template $template)
    {
        $this->template = $template;
    }

    /**
     * Get template used by printer.
     *
     * @return Base_Print_Template_Template
     */
    public function get_template()
    {
        return $this->template;
    }

    /**
     * Print template section to the document.
     *
     * @see print_document
     *
     * @param string $template Section identifier
     * @param Smarty $section Section object with assigned data
     */
    protected function print_section($template, Smarty $section)
    {
        $text = $this->get_template()->print_section($template, $section);
        $this->print_text($text);
    }

    /**
     * Set footer section. Footer is handled separately from the other sections,
     * because it's built-in support in document object.
     *
     * Example:
     * <code>
     *   $section = $this->new_section();
     *   $section->assign('data', $data); // optional
     *   $this->set_footer($section);
     * </code>
     *
     * @param Smarty $section Section to use as footer
     */
    protected function set_footer(Smarty $section)
    {
        $text = $this->get_template()->print_section('footer', $section);
        $this->get_document()->set_footer($text);
    }

    /**
     * Write text directly to the document.
     *
     * @param $text
     */
    protected function print_text($text)
    {
        $this->get_document()->write_text($text);
    }

    /**
     * Just return file to be used as a filename during download or save as.
     * @return string Printed filename without extension.
     */
    public function get_printed_filename_suffix()
    {
        return '';
    }

    /**
     * Generate document using print_document method and return document object.
     *
     * @param $data Data passed to print_document method
     *
     * @return Base_Print_Document_Document
     */
    public function get_printed_document($data)
    {
        $this->get_document()->init($data);
        $this->check_template();
        $this->print_document($data);
        $file_suffix = $this->get_printed_filename_suffix();
        if ($file_suffix) {
            $filename = $this->get_document()->get_filename();
            $this->get_document()->set_filename($filename . ' ' . $file_suffix);
        }
        return $this->get_document();
    }

    /**
     * Fill the template with default template's sections.
     */
    private function check_template()
    {
        if (!$this->get_template()) {
            $templates = $this->default_templates();
            $first_template = reset($templates);
            $this->set_template($first_template);
        }
    }

}
