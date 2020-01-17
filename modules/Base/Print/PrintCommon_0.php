<?php
/**
 *
 * @author      Janusz Tylek <j@epe.si>
 * @copyright  Janusz Tylek
 * @license    MIT
 * @version    1.5.0
 * @package    epesi-base
 * @subpackage Print
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_PrintCommon extends ModuleCommon
{

    public static function admin_caption()
    {
        return array('label'   => __('Print Templates'),
                     'section' => __('Features Configuration'));
    }

    /**
     * Get print href string. This method calls custom print href, that may
     * return array or string. If array is returned then it will be used
     * to create a leightbox select with buttons and it has to be the array
     * of arrays('href' => .. , 'label' => ..)
     *
     * @param mixed  $data    data to pass to printer
     * @param string $printer Printer classname
     *
     * @return string href to open printed document or to open leightbox
     *                with buttons if multiple templates are enabled
     */
    public static function get_print_href($data, $printer)
    {
        $ret = self::get_print_templates($data,$printer);
        if (is_array($ret)) {
            if (count($ret) == 1) {
                $ret = reset($ret);
                $ret = $ret['href'];
            } else {
                $ret = self::choose_box_href($ret);
            }
        }
        return $ret;
    }

    /**
     * Get print available templates. This method calls custom print href, that should
     * return array with href,label,handler and template keys.
     *
     * @param mixed  $data    data to pass to printer
     * @param string $printer Printer classname
     *
     * @return array with href,label,handler and template keys.
     */
    public static function get_print_templates($data, $printer)
    {
        $ret = array();
        $callback = self::get_print_href_callback();
        if ($callback && is_callable($callback)) {
            $passed_params = func_get_args();
            $ret = call_user_func_array($callback, $passed_params);
        }
        if (is_array($ret)) {
            foreach (self::enabled_templates($printer) as $label => $tpl) {
                $href = self::get_default_print_href($data, $printer, $label);
                $ret[] = array('href' => $href, 'label' => _V($label),
                               'template' => $label,
                               'handler' => 'Base_Print_PrintingHandler');
            }
        }
        return $ret;
    }

    /**
     * Create printer object, but check if classname is a proper printer class.
     *
     * @param string $printer_classname Printer classname
     *
     * @return Base_Print_Printer Printer object
     * @throws ErrorException When wrong classname is supplied
     */
    public static function printer_instance($printer_classname)
    {
        if (!$printer_classname || !class_exists($printer_classname)) {
            throw new ErrorException('Wrong printer classname');
        }
        if (!is_subclass_of($printer_classname, 'Base_Print_Printer')) {
            throw new ErrorException('Printer class has to extend Base_Print_Printer');
        }
        $printer = new $printer_classname();
        return $printer;
    }

    /**
     * Create document object, but check if classname is a proper class.
     *
     * @param string $document_classname Document classname
     * @param mixed  $_                  Optional parameters to the document constructor
     *
     * @return Base_Print_Document_Document Document object
     * @throws ErrorException When wrong classname is supplied
     */
    public static function document_instance($document_classname, $_ = null)
    {
        if (!$document_classname || !class_exists($document_classname)) {
            throw new ErrorException('Wrong document classname');
        }
        if (!is_subclass_of($document_classname, 'Base_Print_Document_Document')) {
            throw new ErrorException('Document class has to extend Base_Print_Document_Document');
        }
        $args = func_get_args();
        array_shift($args); // remove document_classname
        $reflection_obj = new ReflectionClass($document_classname);
        $document = $reflection_obj->newInstanceArgs($args);
        return $document;
    }

    /**
     * Obtain array of enabled templates for specific printer class
     *
     * @param string $printer_class
     *
     * @return Base_Print_Template_Template[] array of enabled templates
     *                                        for specific printer
     */
    public static function enabled_templates($printer_class)
    {
        $printer = self::printer_instance($printer_class);
        $templates = $printer->default_templates();
        foreach ($templates as $name => $tpl) {
            if (self::is_template_disabled($printer_class, $name)) {
                unset($templates[$name]);
            }
        }
        return $templates;
    }

    /**
     * Get href to the printed document.
     *
     * You can use this method with your custom handler class.
     *
     * @param mixed       $data              Data to pass to printer
     * @param string      $printer           Printer's classname
     * @param int         $template          Template's id
     * @param string|null $handler_class     Handler's classname. Has to be a
     *                                       subclass of Base_Print_PrintingHandler
     * @param array       $additional_params Additional parameters to pass in the request
     *
     * @return string href
     */
    public static function get_default_print_href($data, $printer, $template, $handler_class = null, $additional_params = array())
    {
        $dir = self::Instance()->get_module_dir();
        $handler_file = $dir . 'Handle.php';

        $params = array('data'    => $data, 'ut' => time(),
                        'printer' => $printer, 'tpl' => $template);
        if ($handler_class) {
            $params['handler'] = $handler_class;
        }
        foreach ($additional_params as $k => $v) {
            if (!array_key_exists($k, $params)) {
                $params[$k] = $v;
            }
        }
        $url = $handler_file . '?' . http_build_query($params);

        $href = ' target="_blank" href="' . $url . '" ';
        return $href;
    }

    /**
     * Create a leightbox with buttons
     *
     * @param array $links array of array('href' => .. , 'label' => ..)
     *
     * @return string href to open leightbox
     */
    protected static function choose_box_href(array $links)
    {
        $unique_id = md5(serialize($links));
        $popup_id = 'print_choice_popup_' . $unique_id;
        $header = __('Select document template to print');
        $launchpad = array();
        $deactivate = " onclick=\"leightbox_deactivate('$popup_id')\"";
        foreach ($links as $template) {
            $launchpad[] = array(
                'href' => $template['href'] . $deactivate,
                'label' => $template['label']
            );
        }
        $th = Base_ThemeCommon::init_smarty();
        $th->assign('icons', $launchpad);
        ob_start();
        Base_ThemeCommon::display_smarty($th, self::Instance()->get_type(), 'launchpad');
        $content = ob_get_clean();
        Libs_LeightboxCommon::display($popup_id, $content, $header);
        return Libs_LeightboxCommon::get_open_href($popup_id);
    }

    /**
     * Set custom print href callback.
     *
     * @param callable $callback
     */
    public static function set_print_href_callback($callback)
    {
        Variable::set('print_href_callback', $callback);
    }

    /**
     * Get custom print href callback.
     *
     * @return string callback
     */
    public static function get_print_href_callback()
    {
        return Variable::get('print_href_callback', false);
    }

    /**
     * Register a new printer class.
     *
     * You have to register printer to allow managing templates
     *
     * @param Base_Print_Printer $obj
     */
    public static function register_printer(Base_Print_Printer $obj)
    {
        $registered_printers = self::get_registered_printers();
        $registered_printers[get_class($obj)] = $obj->document_name();
        self::set_registered_printers($registered_printers);
    }

    /**
     * Unregister printer.
     *
     * @param Base_Print_Printer|string $string_or_obj Object or classname
     *                                                 of printer
     */
    public static function unregister_printer($string_or_obj)
    {
        if (!is_string($string_or_obj)) {
            $string_or_obj = get_class($string_or_obj);
        }
        $registered_printers = self::get_registered_printers();
        unset($registered_printers[$string_or_obj]);
        self::set_registered_printers($registered_printers);
    }

    /**
     * Get registered printers' classnames => document names.
     *
     * @return string[] Classnames is the key, document name is the value
     */
    public static function get_registered_printers()
    {
        $registered_printers = Variable::get('printers_registered', false);
        if (!is_array($registered_printers)) {
            $registered_printers = array();
        }
        return $registered_printers;
    }

    /**
     * Get registered printers' classnames and translated document names.
     *
     * @return string[] Classnames is the key, translated document name is the value
     */
    public static function get_registered_printers_translated()
    {
        $registered_printers = self::get_registered_printers();
        foreach ($registered_printers as &$v) {
            $v = _V($v);
        }
        return $registered_printers;
    }

    protected static function set_registered_printers($registered_printers)
    {
        ksort($registered_printers);
        Variable::set('printers_registered', $registered_printers);
    }

    /**
     * Register new document type. Default ones are PDF and HTML.
     *
     * @param Base_Print_Document_Document $obj
     */
    public static function register_document_type(Base_Print_Document_Document $obj)
    {
        $document_types = self::get_registered_document_types();
        $document_types[get_class($obj)] = $obj->document_type_name();
        self::set_registered_document_types($document_types);
    }

    /**
     * Unregister document type.
     *
     * @param Base_Print_Document_Document|string $string_or_obj
     */
    public static function unregister_document_type($string_or_obj)
    {
        if (is_object($string_or_obj)) {
            $string_or_obj = get_class($string_or_obj);
        }
        $document_types = self::get_registered_document_types();
        unset($document_types[$string_or_obj]);
        self::set_registered_document_types($document_types);
    }

    /**
     * Get registered document types.
     * @return string[] classname is the key, document type name is the value
     */
    public static function get_registered_document_types()
    {
        $document_types = Variable::get('print_document_types', false);
        if (!is_array($document_types)) {
            $document_types = array();
        }
        return $document_types;
    }

    protected static function set_registered_document_types($document_types)
    {
        ksort($document_types);
        Variable::set('print_document_types', $document_types);
    }

    /**
     * Disable specific template
     *
     * @param string $printer_class
     * @param string $template_name
     * @param bool   $active
     */
    public static function set_template_disabled($printer_class, $template_name, $active = false)
    {
        $disabled_templates = self::get_disabled_templates();
        $id = "$printer_class::$template_name";
        if ($active) {
            unset($disabled_templates[$id]);
        } else {
            $disabled_templates[$id] = true;
        }
        self::set_disabled_templates($disabled_templates);
    }

    /**
     * check if template is disabled
     *
     * @param string $printer_class
     * @param string $template_name
     *
     * @return bool
     */
    public static function is_template_disabled($printer_class, $template_name)
    {
        $disabled_templates = self::get_disabled_templates();
        $id = "$printer_class::$template_name";
        $ret = & $disabled_templates[$id];
        return $ret == true;
    }

    protected static function get_disabled_templates()
    {
        $disabled_templates = Variable::get('print_disabled_templates', false);
        if (!is_array($disabled_templates)) {
            $disabled_templates = array();
        }
        return $disabled_templates;
    }

    protected static function set_disabled_templates($disabled_templates)
    {
        Variable::set('print_disabled_templates', $disabled_templates);
    }
}

?>