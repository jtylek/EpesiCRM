<?php
/**
 *
 * @author     Adam Bukowski <abukowski@telaxus.com>
 * @copyright  Telaxus LLC
 * @license    MIT
 * @version    1.5.0
 * @package    epesi-base
 * @subpackage Print
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_PrintCommon extends ModuleCommon
{

    public static function get_print_href($data, $printer)
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
                $ret[] = array('href' => $href, 'label' => _V($label));
            }
            if (count($ret) == 1) {
                $ret = reset($ret);
                $ret = $ret['href'];
            } else {
                $ret = self::choose_box_href($ret);
            }
        }
        return $ret;
    }

    public static function enabled_templates($printer_class)
    {
        $printer = new $printer_class();
        return $printer->default_templates();
    }

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

    public static function set_print_href_callback($callback)
    {
        Variable::set('print_href_callback', $callback);
    }

    public static function get_print_href_callback()
    {
        return Variable::get('print_href_callback', false);
    }

    public static function register_printer(Base_Print_Printer $obj)
    {
        $registered_printers = self::get_registered_printers();
        $registered_printers[get_class($obj)] = $obj->document_name();
        self::set_registered_printers($registered_printers);
    }

    public static function unregister_printer($string_or_obj)
    {
        if (!is_string($string_or_obj)) {
            $string_or_obj = get_class($string_or_obj);
        }
        $registered_printers = self::get_registered_printers();
        unset($registered_printers[$string_or_obj]);
        self::set_registered_printers($registered_printers);
    }

    public static function get_registered_printers()
    {
        $registered_printers = Variable::get('printers_registered', false);
        if (!is_array($registered_printers)) {
            $registered_printers = array();
        }
        return $registered_printers;
    }

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

    public static function register_document_type(Base_Print_Document_Document $obj)
    {
        $document_types = self::get_registered_document_types();
        $document_types[get_class($obj)] = $obj->document_type_name();
        self::set_registered_document_types($document_types);
    }

    public static function unregister_document_type($string_or_obj)
    {
        if (is_object($string_or_obj)) {
            $string_or_obj = get_class($string_or_obj);
        }
        $document_types = self::get_registered_document_types();
        unset($document_types[$string_or_obj]);
        self::set_registered_document_types($document_types);
    }

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
}

?>