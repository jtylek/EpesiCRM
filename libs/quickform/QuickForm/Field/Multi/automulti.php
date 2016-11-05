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

class HTML_QuickForm_automulti extends HTML_QuickForm_multi {
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
	 * @var			callback
	 * @access		private
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
    function __construct($elementName=null, $elementLabel=null, $options_callback=null, $options_callback_args=null, $format_callback=null, $attributes=null)
    {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'automulti';
        if ($options_callback) $this->_options_callback = $options_callback;
        if ($options_callback_args) $this->_options_callback_args = $options_callback_args;
        if ($format_callback) $this->_format_callback = $format_callback;
    }
    
    public static function get_autocomplete_suggestbox($string, $callback=null, $args=null, $format=null) {
    	if (!is_array($args)) $args = array();
		$suggestbox_args = $args;
    	array_unshift($suggestbox_args, $string);
    	$result = call_user_func_array($callback, $suggestbox_args);
    	$ret = '<ul>';
    	if (empty($result))
			$ret .= '<li><span style="text-align:center;font-weight:bold;" class="informal">'.__('No records found').'</span></li>';
    	foreach ($result as $k=>$v) {
    		if ($format) $disp = call_user_func($format, $k, $args);
			else $disp = $v;
			if (!$v) $v = $disp;
			$ret .= '<li><span style="display:none;">'.$k.'__'.$disp.'</span><span class="informal">'.$v.'</span></li>';
		}
    	$ret .= '</ul>';
    	return $ret;
    }
    public function set_search_button($html) {
    	$this->search_button = $html;
    }
    public function on_add_js($js) {
    	$this->on_add_js_code .= $js;
    }
    public function on_remove_js($js) {
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
                $el[]= call_user_func($this->_format_callback, $value, $this->_options_callback_args);
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
            $cleanValue = explode('__SEP__',$value);
            array_shift($cleanValue);
        }
		return $this->_prepareValue($cleanValue, $assoc);
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        $this->updateAttributes(array('multiple' => 'multiple'));
        $strHtml = '';

        $myName = $this->getName();
        $this->updateAttributes(array('id' => $myName)); // Workaround for not processing attributes arg properly

        load_js('libs/quickform/QuickForm/Field/Multi/automulti.js');

        $searchElement = '';
        $search = new HTML_QuickForm_autocomplete($myName . '__search', '', array('HTML_QuickForm_automulti', 'get_autocomplete_suggestbox'), array($this->_options_callback, $this->_options_callback_args, $this->_format_callback));
        $search->setAttribute('placeholder', __('Start typing to search...'));
        $search->on_hide_js('if($("__autocomplete_id_' . $myName . '__search").value!=""){automulti_on_hide("' . $myName . '","' . self::$list_sep . '");' . $this->on_add_js_code . '}');

        $searchElement .= $search->toHtml() . "\n";
        if (isset($this->_values[0]) && (preg_match('/' . addcslashes(self::$list_sep, '/') . '/i', $this->_values[0]) || $this->_values[0] == '')) {
            $this->_values = explode(self::$list_sep, $this->_values[0]);
            array_shift($this->_values);
        }

        $this->setName($myName . '__display');

        $mainElement = '';
        $list = '';
        $attrString = $this->_getAttrString($this->_attributes);
        $mainElement .= '<select' . $attrString . ' onclick="automulti_remove_button_update(\'' . $myName . '\');">' . "\n";
        if ($this->_format_callback) foreach ($this->_values as $value) {
            $mainElement .= "\t" . '<option value="' . $value . '">' . call_user_func($this->_format_callback, $value, $this->_options_callback_args) . '</option>' . "\n";
            $list .= '__SEP__' . $value;
        }
        $mainElement .= '</select>';

        $strHtml .= '<table class="automulti">';
        $strHtml .= '<tr>' .
            '<td class="search-element">' . $searchElement . '</td>' .
            ($this->search_button ? '<td class="search">' . $this->search_button . '</td>' : '<td></td>') .
            '<td width="80px;" class="button disabled" id="automulti_button_style_' . $myName . '">' .
            '<input style="width:100%" type="button" onclick="automulti_remove_button_action(\'' . $myName . '\', \'' . self::$list_sep . '\');' . $this->on_remove_js_code . '" value="' . __('Remove') . '">' . '</td>' .
            '</tr>';

        $strHtml .= '<tr><td class="main-element" colspan="3">' . $mainElement . '</td></tr></table>';

        $this->setName($myName);

        $strHtml .= '<input type="hidden" name="' . $myName . '" value="' . $list . '" id="' . $myName . '__var_holder" />' . "\n";
        return $strHtml;
    }

}
?>
