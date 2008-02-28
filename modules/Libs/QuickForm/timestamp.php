<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Class for a group of elements used to input dates (and times).
 * 
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 * @copyright   2001-2007 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 * @version     CVS: $Id: date.php,v 1.60 2007/06/04 19:22:23 avb Exp $
 * @link        http://pear.php.net/package/HTML_QuickForm
 */

/**
 * Class for a group of form elements
 */
require_once 'HTML/QuickForm/group.php';
/**
 * Class for <select></select> elements
 */
require_once 'modules/Utils/PopupCalendar/PopupCalendarCommon_0.php';
require_once 'modules/Utils/PopupCalendar/datepicker.php';
require_once 'HTML/QuickForm/date.php';

/**
 * Class for a group of elements used to input dates (and times).
 * 
 * Inspired by original 'date' element but reimplemented as a subclass
 * of HTML_QuickForm_group
 * 
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 * @version     Release: 3.2.9
 * @since       3.1
 */
class HTML_QuickForm_timestamp extends HTML_QuickForm_group
{
	private $_elementName;
	
    // }}}
    // {{{ constructor

    function HTML_QuickForm_timestamp($elementName = null, $elementLabel = null, $options = array(), $attributes = null)
    {
        $this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_elementName = $elementName;
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'timestamp';
        $this->_options = $options;
    }

    // }}}
    // {{{ _createElements()

    function _createElements()
    {
		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i:a':'H:i';
		$lang_code = Base_LangCommon::get_lang_code();
		$this->_options['format'] = $time_format;
		$this->_options['optionIncrement'] = array('i' => 5);
		$this->_options['language'] = $lang_code;

        $this->_elements['datepicker'] =& new HTML_QuickForm_datepicker('datepicker', null, array(), $this->getAttributes());
        $this->_elements['date'] =& new HTML_QuickForm_date('date', null, $this->_options, $this->getAttributes());
    }

    // }}}
    // {{{ _createOptionList()

   /**
    * Creates an option list containing the numbers from the start number to the end, inclusive
    *
    * @param    int     The start number
    * @param    int     The end number
    * @param    int     Increment by this value
    * @access   private
    * @return   array   An array of numeric options.
    */
    function _createOptionList($start, $end, $step = 1)
    {
        for ($i = $start, $options = array(); $start > $end? $i >= $end: $i <= $end; $i += $step) {
            $options[$i] = sprintf('%02d', $i);
        }
        return $options;
    }

    // }}}
    // {{{ setValue()

    // }}}
    // {{{ toHtml()

    function toHtml()
    {
        include_once('HTML/QuickForm/Renderer/Default.php');
        $renderer =& new HTML_QuickForm_Renderer_Default();
        $renderer->setElementTemplate('{element}');
        parent::accept($renderer);
        return $renderer->toHtml();
    }

	function recalculate_time($time) {
		if (isset($time['a'])) {
			$result = 60*($time['i']+60*($time['h']));
			if ($time['a']=='pm') $result += 43200;
			if ($time['h']==12) {
				if ($time['a']=='pm') $result -= 43200; else $result -= 43200;
			}
		} else $result = 60*($time['i']+60*($time['H']));
		$result -= 60*60;
		return $result;
	}

	function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        }
        if ($value['datepicker']=='') return $this->_prepareValue('', $assoc);
		$result = $this->recalculate_time($value['date']);
		$cleanValue = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value['datepicker'])).' '.strftime('%H:%M:%S',Base_RegionalSettingsCommon::reg2time(date('H:i:s', $result), true, false));
        return $this->_prepareValue($cleanValue, $assoc);
	}

    // }}}
    // {{{ accept()

    function accept(&$renderer, $required = false, $error = null)
    {
        $renderer->renderElement($this, $required, $error);
    }

    // }}}
    // {{{ onQuickFormEvent()

    function onQuickFormEvent($event, $arg, &$caller)
    {
        if ('updateValue' == $event) {
            // we need to call setValue(), 'cause the default/constant value
            // may be in fact a timestamp, not an array
            return HTML_QuickForm_element::onQuickFormEvent($event, $arg, $caller);
        } else {
            return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    // }}}
    
    function setValue($value)
    {
        $this->_createElementsIfNotExist();
        foreach (array_keys($this->_elements) as $key) {
            $elementName = $this->_elements[$key]->getName();
            $index       = strlen($elementName) ? $elementName : $key;
            if (is_array($value)) {
            	$v = $value[$index];
            } else {
            	$v = $value;
            }
            if (is_array($v)) $v = $this->recalculate_time($v);
            else $v = strtotime($v);
            $this->_elements[$key]->onQuickFormEvent('setGroupValue', Base_RegionalSettingsCommon::time2reg($v), $this);
        }
    } //end func setValue
    
}
?>