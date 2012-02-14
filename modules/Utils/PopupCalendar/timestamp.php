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
 * @category	HTML
 * @package	 HTML_QuickForm
 * @author	  Alexey Borzov <avb@php.net>
 * @copyright   2001-2007 The PHP Group
 * @license	 http://www.php.net/license/3_01.txt PHP License 3.01
 * @version	 CVS: $Id: date.php,v 1.60 2007/06/04 19:22:23 avb Exp $
 * @link		http://pear.php.net/package/HTML_QuickForm
 */

/**
 * Class for a group of form elements
 */
require_once 'HTML/QuickForm/group.php';
/**
 * Class for <select></select> elements
 */
require_once 'datepicker.php';
require_once 'HTML/QuickForm/date.php';

/**
 * Class for a group of elements used to input dates (and times).
 * 
 * Inspired by original 'date' element but reimplemented as a subclass
 * of HTML_QuickForm_group
 * 
 * @category	HTML
 * @package	 HTML_QuickForm
 * @author	  Alexey Borzov <avb@php.net>
 * @version	 Release: 3.2.9
 * @since	   3.1
 */
class HTML_QuickForm_timestamp extends HTML_QuickForm_group
{
	private $_elementName;
	
	// }}}
	// {{{ constructor

	function HTML_QuickForm_timestamp($elementName = null, $elementLabel = null, $options = array(), $attributes = null) {
		$this->HTML_QuickForm_element($elementName, $elementLabel, $attributes);
		$this->_elementName = $elementName;
		$this->_persistantFreeze = true;
		$this->_appendName = true;
		$this->_type = 'group';
		$this->_options = $options;
	}

	// }}}
	// {{{ _createElements()

	function _createElements() {
		$time_format = Base_RegionalSettingsCommon::time_12h()?'h:i a':'H:i';
		$lang_code = Base_LangCommon::get_lang_code();
		$this->_options['format'] = $time_format;
		if (!isset($this->_options['optionIncrement'])) $this->_options['optionIncrement'] = array('i' => 5);
		$this->_options['language'] = $lang_code;
		if (!isset($this->_options['date'])) $this->_options['date'] = true;

		$this->_elements['__date'] = new HTML_QuickForm_date('__date', null, $this->_options, $this->getAttributes());
		if ($this->_options['date'])
			$this->_elements['__datepicker'] = new HTML_QuickForm_datepicker('__datepicker', null, $this->getAttributes());
	}

	// }}}
	// {{{ _createOptionList()

   /**
	* Creates an option list containing the numbers from the start number to the end, inclusive
	*
	* @param	int	 The start number
	* @param	int	 The end number
	* @param	int	 Increment by this value
	* @access   private
	* @return   array   An array of numeric options.
	*/
	function _createOptionList($start, $end, $step = 1) {
		for ($i = $start, $options = array(); $start > $end? $i >= $end: $i <= $end; $i += $step) {
			$options[$i] = sprintf('%02d', $i);
		}
		return $options;
	}

	// }}}
	// {{{ setValue()

	// }}}
	// {{{ toHtml()

	function toHtml() {
		include_once('HTML/QuickForm/Renderer/Default.php');
		$renderer = new HTML_QuickForm_Renderer_Default();
		$renderer->setElementTemplate('{element}');
		$renderer->setGroupElementTemplate('<div>{element}</div>', $this->_elementName);
		parent::accept($renderer);
		return str_replace('&nbsp;','',$renderer->toHtml());
	}

	function onQuickFormEvent($event, $arg, &$caller) {
		if ('updateValue' == $event) {
				// we need to call setValue(), 'cause the default/constant value
				// may be in fact a timestamp, not an array
			$this->_createElementsIfNotExist();
			return HTML_QuickForm_element::onQuickFormEvent($event, $arg, $caller);
		} else {
			return parent::onQuickFormEvent($event, $arg, $caller);
		}
	}

	function exportValue(&$submitValues, $assoc = false) {
		if ($this->_options['date']) {
			$dpv = $this->_elements['__datepicker']->exportValue($submitValues);
			if ($dpv=='') return $this->_prepareValue('', $assoc);
			$dv = $this->_elements['__date']->exportValue($submitValues);
			$result = recalculate_time(date('Y-m-d'),$dv);
			$cleanValue = date('Y-m-d H:i:s',Base_RegionalSettingsCommon::reg2time($dpv.' '.date('H:i:s', $result),!isset($this->_options['regional_settings_tz']) || $this->_options['regional_settings_tz']==true)); //tz trans - last arg changed from false...
		} else {
			$dv = $this->_elements['__date']->exportValue($submitValues);
			$result = recalculate_time(date('Y-m-d'),$dv);
			$cleanValue = date('1970-01-01 H:i:s',Base_RegionalSettingsCommon::reg2time('1970-01-01 '.date('H:i:s', $result),!isset($this->_options['regional_settings_tz']) || $this->_options['regional_settings_tz']==true)); //tz trans - last arg changed from false...
		}
		return $this->_prepareValue($cleanValue, $assoc);
	}

	// }}}
	// {{{ accept()

	function accept(&$renderer, $required = false, $error = null) {
		$renderer->renderElement($this, $required, $error);
	}

	function setValue($value)
	{
		$this->_createElementsIfNotExist();
		if(is_array($value)) {
			if ($this->_options['date']) {
				if($value['__datepicker']!=='')
					$value['__datepicker'] = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value['__datepicker'],false));
				$this->_elements['__datepicker']->setValue($value['__datepicker']);
			}
			$this->_elements['__date']->setValue(isset($value['__date'])?$value['__date']:'');
		} else {
			if (!$value) return;
			if (!is_numeric($value)) $value = Base_RegionalSettingsCommon::reg2time($value,false);
			$value -= (date('i',$value) % $this->_options['optionIncrement']['i'])*60;
			//tz trans begin
			if(!isset($this->_options['regional_settings_tz']) || $this->_options['regional_settings_tz']==true)
				$value = Base_RegionalSettingsCommon::time2reg($value,true,true,true,false);
			//tz trans end
			foreach ($this->_elements as & $v)
				$v->setValue($value);
		}
	} //end func setValue
	
}
?>