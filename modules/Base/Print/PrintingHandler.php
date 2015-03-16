<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Class responsible for reading request variables, creating document and
 * printer objects, and generating printout.
 */
class Base_Print_PrintingHandler
{
    private $printer_classname;
    private $printer;
    private $tpl;
    private $data;

    /**
     * Handle request and output document.
     */
    public function handle_request()
    {
        $this->read_request_variables();
        $this->output_document();
    }

    /**
     * Generate document and output to the stdout with specific to Document
     * http headers.
     */
    public function output_document()
    {
        $document = $this->printed_document();
        $content = $document->get_output();
        $document->http_headers();
        print $content;
    }

    /**
     * Generate and retrieve Document object with printed data.
     *
     * @return Base_Print_Document_Document
     */
    public function printed_document()
    {
        $printer = $this->get_printer();
        $printer->set_document(new Base_Print_Document_PDF($printer->get_document_config('Base_Print_Document_PDF')));
        $this->set_selected_template();

        return $printer->get_printed_document($this->get_data());
    }

    /**
     * Get Printer object, that will be used to print data.
     *
     * @return Base_Print_Printer
     * @throws ErrorException When you won't call set_printer before, then this
     * method will try to create printer object using get_printer_classname.
     * If returned string is not a proper printer class name, then you'll get
     * an exception.
     */
    public function get_printer() {
        if (!isset($this->printer)) {
            $printer_classname = $this->get_printer_classname();
            $printer = Base_PrintCommon::printer_instance($printer_classname);
            $this->set_printer($printer);
        }
        return $this->printer;
    }

    /**
     * Set Printer object, that will be used to print data.
     *
     * @param Base_Print_Printer $printer Printer object
     */
    public function set_printer(Base_Print_Printer $printer)
    {
        $this->printer = $printer;
        $this->printer_classname = get_class($printer);
    }

    /**
     * Read all request variables at once. You may override this method,
     * to read yours. Remember to call parent!
     */
    protected function read_request_variables()
    {
        $this->set_printer_classname($this->request_param('printer', true));
        $this->set_data($this->request_param('data', true));
        $this->set_tpl($this->request_param('tpl'));
    }

    /**
     * Set template in printer according to requested tpl,
     * first default if no one. Also set filename in document
     * @throws ErrorException
     */
    protected function set_selected_template()
    {
        $printer = $this->get_printer();
        $tpl = $this->get_tpl();
        $templates = $printer->default_templates();
        if (!$tpl) {
            $tpl = key($templates);
            $this->set_tpl($tpl);
        }
        if (!isset($templates[$tpl])) {
            throw new ErrorException('Wrong template');
        }
        $template = $templates[$tpl];
        if ($template) {
            $printer->set_template($template);
        }
        $printer->get_document()->set_filename(_V($tpl));
    }

    /**
     * Get parameter from the request.
     *
     * @param string $name Name of request variable.
     * @param bool   $required If not required then null will be returned.
     *                         Otherwise exception will be thrown.
     *
     * @return mixed data obtained from the request.
     * @throws ErrorException
     */
    protected function request_param($name, $required = false)
    {
        $val = null;
        if (!isset($_REQUEST[$name])) {
            if ($required) {
                throw new ErrorException('Invalid usage - missing param: ' . $name);
            }
        } else {
            $val = $_REQUEST[$name];
        }
        return $val;
    }

    /**
     * Set data that will be passed to the printer.
     *
     * @param mixed $data
     */
    public function set_data($data)
    {
        $this->data = $data;
    }

    /**
     * Get data that will be passed to the printer.
     *
     * @return mixed
     */
    public function get_data()
    {
        return $this->data;
    }

    /**
     * Set class name that will be used to create printer object, when set_printer
     * won't be called.
     *
     * @param string $printer Class name
     */
    public function set_printer_classname($printer)
    {
        $this->printer_classname = $printer;
        $this->printer = null;
    }

    /**
     * Get class name
     * @return string
     */
    public function get_printer_classname()
    {
        return $this->printer_classname;
    }

    /**
     * Set template
     */
    public function set_tpl($tpl)
    {
        $this->tpl = $tpl;
    }

    /**
     * Get selected template
     */
    public function get_tpl()
    {
        return $this->tpl;
    }
}
