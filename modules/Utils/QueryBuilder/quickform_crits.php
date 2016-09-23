<?php
/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2016, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */
require_once("HTML/QuickForm/input.php");

class HTML_QuickForm_crits extends HTML_QuickForm_input {

    function HTML_QuickForm_crits($elementName = null, $elementLabel = null, $attributes=null) {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        if ($this->_caller instanceof HTML_QuickForm) {
            $this->_caller->addFormRule(array($this, 'check_for_error'));
        }
    } //end constructor

    function toHtml()
    {
        $name = $this->getName();
        $str = "<div id=\"{$name}_qb_editor\"></div>";
        $attrs = $this->getAttributes(true);
        $str .= "<input type=\"hidden\" $attrs>";
        $last_value = isset($this->_caller->_submitValues["{$name}_last_valid"]) ? $this->_caller->_submitValues["{$name}_last_valid"] : null;
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
        $value[$this->getName()] = Utils_RecordBrowser_QueryBuilderIntegration::json_to_crits($value[$this->getName()]);
        return $value;
    }

    function getValueInJson()
    {
        $value = parent::exportValue($submitValues, false);
        return $value;
    }
}
