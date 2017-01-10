<?php
/**
 * @author Kuba Slawinski <kslawinski@telaxus.com>
 *         Paul Bukowski <pbukowski@telaxus.com>
 *         Adam Bukowski <abukowski@telaxus.com
 * @copyright Copyright &copy; 2017, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipCommon extends ModuleCommon
{
    public static function user_settings()
    {
        return array(__('Misc') => array(
            array('name' => 'help_tooltips', 'label' => __('Show help tooltips'), 'type' => 'checkbox', 'default' => 1)
        ));
    }

    private static function show_help()
    {
        static $help_tooltips;
        if ($help_tooltips === null) {
            $help_tooltips = Base_User_SettingsCommon::get(Utils_TooltipCommon::module_name(), 'help_tooltips');
        }
        return $help_tooltips;
    }

    private static function load_files()
    {
        static $loaded = false;
        if (!$loaded) {
            Base_ThemeCommon::load_css(Utils_TooltipCommon::module_name(), 'default');
            load_js('modules/Utils/Tooltip/js/tooltip.js');
            eval_js_once('Utils_Tooltip.enable_tooltips()');
            $loaded = true;
        }
    }

    /**
     * Returns string that when placed as tag attribute
     * will enable tooltip when placing mouse over that element.
     *
     * @param string $tip tooltip text
     * @param boolean $help help tooltip (you can turn off help tooltips)
     * @return string HTML tag attributes
     */
    public static function open_tag_attrs($tip, $help = true)
    {
        self::load_files();
        if ($help && !self::show_help()) return '';

        $tip = htmlspecialchars($tip);

        return "data-toggle=\"tooltip\" data-epesi-tooltip=\"$tip\"";
    }

    /**
     * Returns string that when placed as tag attribute
     * will enable ajax request to set a tooltip when placing mouse over that element.
     *
     * @param callable $callback method that will be called to get tooltip content
     * @param array $args parameters that will be passed to the callback
     * @return string HTML tag attributes
     */
    public static function ajax_open_tag_attrs($callback, $args)
    {
        self::load_files();
        $tooltip_settings = array('callback' => $callback, 'args' => $args);
        $tooltip_id = md5(serialize($tooltip_settings));

        $_SESSION['client']['utils_tooltip']['callbacks'][$tooltip_id] = $tooltip_settings;

        $loading_message = '<img src=' . Base_ThemeCommon::get_template_file('Utils_Tooltip', 'loader.gif') . ' /><br/>' . __('Loading...');
        return "data-toggle=\"tooltip\" data-epesi-tooltip=\"$loading_message\" data-ajaxtooltip=\"$tooltip_id\"";
    }

    /**
     * Returns string that if displayed will create text with tooltip.
     *
     * @param string $text text
     * @param string $tip tooltip text
     * @param boolean $help help tooltip (you can turn off help tooltips)
     * @return string text with tooltip
     */
    public static function create($text, $tip, $help = true)
    {
        if ((!$help || self::show_help()) && is_string($tip) && $tip !== '')
            return '<span ' . self::open_tag_attrs($tip, $help) . '>' . $text . '</span>';
        else
            return $text;
    }

    /**
     * Returns string that if displayed will create text with tooltip loaded via ajax.
     *
     * @param string $text text
     * @param callable $callback callback
     * @param array $args arguments for the callback
     * @return string text with tooltip
     */
    public static function ajax_create($text, $callback, $args = array())
    {
        return '<span ' . self::ajax_open_tag_attrs($callback, $args) . '>' . $text . '</span>';
    }

    /**
     * Check if there is a tooltip code in provided html string
     *
     * @param string $str html string
     * @return bool is tooltip code in provided html string
     */
    public static function is_tooltip_code_in_str($str)
    {
        return strpos($str, 'data-toggle="tooltip"') !== false;
    }

    /**
     * Returns a 2-column formatted table
     *
     * @param array $arg keys are captions, values are values
     * @return string html table
     */
    public static function format_info_tooltip($arg)
    {
        if (!is_array($arg) || empty($arg)) return '';
        $table = '<table>';
        foreach ($arg as $k => $v) {
            $table .= '<tr><td>';
            $table .= $k . '</td><td>';
            $table .= $v; // Value
            $table .= '</td></tr>';
        }
        $table .= '</table>';
        return $table;
    }

    /**
     * Add leightbox mode to tooltip. Use together with open_tag_attrs or ajax_open_tag_attrs.
     *
     * @return string HTML tag attributes
     */
    public static function tooltip_leightbox_mode()
    {
        static $init = null;
        if (!isset($_REQUEST['__location'])) $loc = true;
        else $loc = $_REQUEST['__location'];
        if ($init !== $loc) {
            Base_ThemeCommon::load_css(Utils_TooltipCommon::module_name(), 'leightbox_mode');
            Libs_LeightboxCommon::display('tooltip_leightbox_mode', '<center><div id="tooltip_leightbox_mode_content" ></div></center>');
            $init = $loc;
        }
        return Libs_LeightboxCommon::get_open_href('tooltip_leightbox_mode') . ' onmousedown="Utils_Tooltip.leightbox_mode(this)" ';
    }

}

?>
