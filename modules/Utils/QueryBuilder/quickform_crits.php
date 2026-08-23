<?php
/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2016, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
// require_once disabled — openpsa provides HTML_QuickForm_input via autoload

class HTML_QuickForm_crits extends HTML_QuickForm_input {

    function __construct($elementName = null, $elementLabel = null, $attributes=null) {
        parent::__construct($elementName, $elementLabel, $attributes); // PHP4 ctor → openpsa parent::__construct
        // openpsa/quickform assigns $_caller onto the element after
        // _createElement() returns it, not before - it doesn't exist yet at
        // this point in the constructor. isset() guard only silences the
        // PHP 8.2 undefined-property warning; behavior is unchanged since an
        // unset property was never going to satisfy instanceof anyway.
        if (isset($this->_caller) && $this->_caller instanceof HTML_QuickForm) {
            $this->_caller->addFormRule($this->check_for_error(...));
        }
    } //end constructor

    function toHtml()
    {
        $name = $this->getName();
        $str = "<div id=\"{$name}_qb_editor\"></div>";
        $attrs = $this->getAttributes(true);
        $str .= "<input type=\"hidden\" $attrs>";
        $last_value = $this->_caller->_submitValues["{$name}_last_valid"] ?? null;
        if ($last_value) {
            $last_value = htmlspecialchars($last_value);
            $last_value = " value=\"{$last_value}\"";
        }
        $str .= "<input type=\"hidden\" name=\"{$name}_last_valid\" id=\"{$name}_last_valid\"{$last_value}>";
        return $str;
    }

    public function check_for_error($form_values)
    {
        if (isset($form_values[$this->getName()])
            && $form_values[$this->getName()] == '{}'
        ) {
            return array($this->getName() => __('Please fix query builder rules'));
        }
        return array();
    }

    function setValue($value)
    {
        if (is_array($value)) {
            $value = Utils_RecordBrowser_Crits::from_array($value);
        }
        if (is_object($value) && $value instanceof Utils_RecordBrowser_CritsInterface) {
            $value = Utils_RecordBrowser_QueryBuilderIntegration::crits_to_json($value);
            $value = json_encode($value);
        }
        parent::setValue($value);
    }

    function exportValue(&$submitValues, $assoc = false)
    {
        $value = parent::exportValue($submitValues, $assoc);
        if ($assoc) {
            $value[$this->getName()] = Utils_RecordBrowser_QueryBuilderIntegration::json_to_crits($value[$this->getName()]);
        } else {
            $value = Utils_RecordBrowser_QueryBuilderIntegration::json_to_crits($value);
        }
        return $value;
    }

    function getValueInJson()
    {
        $value = parent::exportValue($submitValues, false);
        return $value;
    }
}
