<?php

/**
 * HTML class for a autocomplete-multiselect combo
 *
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @license MIT
 * @version 1.0
 * @package epesi-libs
 * @subpackage QuickForm
 */
class HTML_QuickForm_automulti extends HTML_QuickForm_multi
{
    /**
     * Contains the callback for select options
     *
     * @var       callback
     * @access    private
     */
    var $_options_callback = null;

    /**
     * Contains the callback for option formatting
     *
     * @var           callback
     * @access        private
     */
    var $_format_callback = null;

    /**
     * Contains the arguments for callback for select options
     *
     * @var       callback
     * @access    private
     */
    var $_options_callback_args = null;

    public static $list_sep = '__SEP__';
    private $on_add_js_code = '';
    private $on_remove_js_code = '';
    private $search_button = '';

    /**
     * Class constructor
     *
     * @param     string    Select name attribute
     * @param     mixed     Label(s) for the select
     * @param     mixed     Data to be used to populate options
     * @param     mixed     Either a typical HTML attribute string or an associative array
     * @access    public
     * @return    void
     */
    function __construct($elementName = null, $elementLabel = null, $options_callback = null, $options_callback_args = null, $format_callback = null, $attributes = null)
    {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'automulti';
        if ($options_callback) $this->_options_callback = $options_callback;
        if ($options_callback_args) $this->_options_callback_args = $options_callback_args;
        if ($format_callback) $this->_format_callback = $format_callback;
    }

    public static function get_autocomplete_suggestbox($string, $callback, $args, $format = null)
    {
        if (!is_string($string)) $string = '';
        $suggestbox_args = $args;
        array_unshift($suggestbox_args, $string);
        $result = call_user_func_array($callback, $suggestbox_args);
        $res = [];
        foreach ($result as $id => $description) {
            $res[] = ['id' => $id, 'text' => html_entity_decode($description)];
        }
        return $res;
    }

    public function set_search_button($html)
    {
        $this->search_button = $html;
    }

    public function on_add_js($js)
    {
        $this->on_add_js_code .= $js;
    }

    public function on_remove_js($js)
    {
        $this->on_remove_js_code .= $js;
    }

    /**
     * Returns the value of field without HTML tags
     *
     * @access    public
     * @return    string
     */
    function getFrozenHtml()
    {
        $el = array();
        if ($this->_format_callback) {
            foreach ($this->_values as $value) {
                // code copied from the above - seems that the last param is wrong
                $el[] = call_user_func($this->_format_callback, $value, $this->_options_callback_args);
            }
        }
        return implode('<br>', $el);
    }

    /**
     * We check the options and return only the values that _could_ have been
     * selected. We also return a scalar value if select is not "multiple"
     */
    function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        }
        if (is_array($value)) $cleanValue = $value;
        else {
            $cleanValue = explode('__SEP__', $value);
            array_shift($cleanValue);
        }
        return $this->_prepareValue($cleanValue, $assoc);
    }

    /**
     * Returns the SELECT in HTML
     *
     * @access    public
     * @return    string
     */
    public function getHtml()
    {
        $this->updateAttributes(array('multiple' => 'multiple'));
        $myName = $this->getName();
        $this->updateAttributes(array('id' => $myName)); // Workaround for not processing attributes arg properly

        if (isset($this->_values[0]) && (preg_match('/' . addcslashes(self::$list_sep, '/') . '/i', $this->_values[0]) || $this->_values[0] == '')) {
            $this->_values = explode(self::$list_sep, $this->_values[0]);
            array_shift($this->_values);
        }

        $attrString = $this->_getAttrString($this->_attributes);
        $options = '';
        if ($this->_format_callback) foreach ($this->_values as $value) {
            $options .= "\t" . '<option value="' . $value . '" selected="selected">' . call_user_func($this->_format_callback, $value, $this->_options_callback_args) . '</option>' . "\n";
        }

        $callback = array($this->_options_callback, $this->_options_callback_args, $this->_format_callback);

        $key = md5(serialize($callback) . $this->getAttribute('id'));
        $_SESSION['client']['quickform']['autocomplete'][$key] = array(
            'callback' => array('HTML_QuickForm_automulti', 'get_autocomplete_suggestbox'),
            'field' => 'q',
            'args' => $callback
        );

        //TODO-PJ: Add pagination

        $cid = CID;
        $hint = __('Start typing to search...');
        $select2_js = <<<js
            jQuery("select[name='{$myName}[]']").select2({
                placeholder: "{$hint}",
                ajax: {
                    url: "libs/quickform/autocomplete_update.php",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                      return {
                          q: params.term,
                          page: params.page,
                          cid: "{$cid}",
                          key: "{$key}"
                      }
                    },
                    processResults: function (data, params) {
                          // parse the results into the format expected by Select2
                          // since we are using custom formatting functions we do not need to
                          // alter the remote JSON data, except to indicate that infinite
                          // scrolling can be used
                          params.page = params.page || 1;
                          return {
                            results: data,
                            pagination: {
                              more: (params.page * 30) < data.total_count
                            }
                          };
                        },
                }
            });
js;

        $select2 = <<<html
            <select style="width: 100%" name="{$myName}[]"{$attrString} multiple="multiple">
              {$options}
            </select>
html;

        eval_js($select2_js);
        return $select2;
    }

}

?>
