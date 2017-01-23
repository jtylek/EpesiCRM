<?php

/**
 * @author  Paul Bukowski <pbukowski@telaxus.com>
 *          Arkadiusz Bisaga <abisaga@telaxus.com>
 *          Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2017, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
class HTML_QuickForm_datepicker extends HTML_QuickForm_text
{

    function getMomentJsDateFormat()
    {
        $trans = array(
            '%Y-%m-%d' => 'YYYY-MM-DD',
            '%m/%d/%Y' => 'MM/DD/YYYY',
            '%d %B %Y' => 'DD[ ]MMMM YYYY',
            '%d %b %Y' => 'DD MMM YYYY',
            '%b %d, %Y' => 'MMM DD, YYYY');
        $date_format = Base_RegionalSettingsCommon::date_format();
        return isset($trans[$date_format]) ? $trans[$date_format] : 'YYYY-MM-DD';
    }

    function getHtml()
    {
        $value = $this->getAttribute('value');
        if (is_numeric($value)) $value = date('Y-m-d', $value);
        $id = $this->getAttribute('id');
        $name = $this->getAttribute('name');
        if (!isset($id)) {
            $id = 'datepicker_field_' . $name;
            $id = str_replace(['[', ']'], '_', $id);
            $this->updateAttributes(array('id' => $id));
        }
        if (!$this->getAttribute('placeholder'))
            $this->setAttribute('placeholder', __('Click to select date'));
        load_js('libs/moment-with-locales.min.js');
        load_js('libs/bootstrap-datetimepicker.min.js');
        load_css('libs/bootstrap-datetimepicker.min.css');

        $options = $this->getDatetimepickerOptions();
        eval_js('jq(\'#' . $id . '\').datetimepicker(' . json_encode($options) . ');');
        if ($value) {
            eval_js('jq(\'#' . $id . '\').data("DateTimePicker").date(moment(\'' . $value . '\'));');
        }
        return parent::getHtml();
    }

    public function getDatetimepickerOptions()
    {
        $date_format = $this->getMomentJsDateFormat();
        $lang = Base_LangCommon::get_lang_code();
        $options = array(
            'format' => $date_format,
            'locale' => $lang,
            'showTodayButton' => true,
            'showClear' => true,
            'useCurrent' => false,
        );
        return $options;
    }

    function getValue()
    {
        $value = $this->getAttribute('value');
        if (!$value) return $value;
//		print('get_value('.$this->getName().')='.$value.' '.Base_RegionalSettingsCommon::time2reg($value,false,true,false).'<hr>');
        if (!is_numeric($value) && is_string($value) && !strtotime($value)) return $value;
        $time2reg = Base_RegionalSettingsCommon::time2reg($value, false, true, false);
        return $time2reg;
    } // end func setValue

    function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        }
        if ($value == '') return $this->_prepareValue('', $assoc);
        $cleanValue = date('Y-m-d', Base_RegionalSettingsCommon::reg2time($value, false));
        return $this->_prepareValue($cleanValue, $assoc);
    }

    function onQuickFormEvent($event, $arg, &$caller)
    {
        if ($event == 'updateValue' && is_callable(array($caller, 'applyFilter')))
            $caller->applyFilter($this->getName(), array($this, 'reg2time'));
        return parent::onQuickFormEvent($event, $arg, $caller);
    }

    function reg2time($value)
    {
        if (!$value) return '';
        return strftime('%Y-%m-%d', Base_RegionalSettingsCommon::reg2time($value, false));
    }
}
