<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com> and Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
// require_once disabled — openpsa provides HTML_QuickForm_input via autoload (avoids system PEAR HTML_Common conflict)

class HTML_QuickForm_datepicker extends HTML_QuickForm_input {

	function __construct($elementName=null, $elementLabel=null, $attributes=null) {
		parent::__construct($elementName, $elementLabel, $attributes); // PHP4 ctor → openpsa parent::__construct
		$this->_persistantFreeze = true;
		$this->setType('datepicker');
//		$this->updateAttributes(array('readonly'=>1));
	} //end constructor

	function toHtml() {
		$str = "";
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$value = $this->getAttribute('value');
			if (is_numeric($value)) $value = date('Y-m-d', $value);
			$id = $this->getAttribute('id');
			$name = $this->getAttribute('name');
			if ($value) $this->setAttribute('value',Base_RegionalSettingsCommon::time2reg($value,false,true,false));
			if(!isset($id)) {
				$id = 'datepicker_field_'.$name;
				$this->updateAttributes(array('id'=>$id));
			}
			$ex_date = Base_RegionalSettingsCommon::time2reg(null,false,true,false);
			$date_format = Base_RegionalSettingsCommon::date_format();
			$this->setType('text');
            if (!$this->getAttribute('placeholder'))
                $this->setAttribute('placeholder', __('Click to select date'));
            // Browser/password-manager autofill (keyed off name="birth_date"
            // etc.) fills its own stored format (commonly ISO YYYY-MM-DD),
            // which almost never matches this instance's configured
            // date_format() - validate_blur() in datepicker.js then correctly
            // rejects the mismatch and wipes the field, so every autofill of
            // a date field silently loses the value the browser just filled.
            // Opting this field out of autofill entirely avoids the mismatch
            // rather than trying to parse arbitrary autofill formats.
            if (!$this->getAttribute('autocomplete'))
                $this->setAttribute('autocomplete', 'off');
            $js = Utils_PopupCalendarCommon::create_href(md5($id),
                    'jQuery.ajax(\'modules/Utils/PopupCalendar/up.php\','.
                    '{method:\'post\', data:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__}, dataType:\'text\','.
                    'success:function(responseText){e=document.getElementById(\''.Epesi::escapeJS($id,false).'\');if(e) {e.value=responseText;jq(e).change();}}})',
                    null,null,
                    'jQuery(popup).clonePosition(document.getElementById(\''.$id.'\'),{setWidth:false,setHeight:false,offsetTop:document.getElementById(\''.$id.'\').offsetHeight})',$value, $id);
            $str .= $this->_getTabs() . '<input ' . $js . ' ' . $this->_getAttrString($this->_attributes) . ' '.Utils_TooltipCommon::open_tag_attrs(__('Example date: %s',array($ex_date)), false ).' />';
			eval_js('jQuery(document.getElementById(\''.$id.'\')).on(\'keypress\',function(e){Utils_PopupCalendarDatePicker.validate.call(Utils_PopupCalendarDatePicker,e,\''.Epesi::escapeJS($date_format,false).'\')})');
			eval_js('jQuery(document.getElementById(\''.$id.'\')).on(\'blur\',function(e){Utils_PopupCalendarDatePicker.validate_blur.call(Utils_PopupCalendarDatePicker,e,\''.Epesi::escapeJS($date_format,false).'\')})');
		}
		return $str;
	} //end func toHtml

/*	function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } 
		if ($value!='') $cleanValue = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
		else $cleanValue = '';
        return $this->_prepareValue($cleanValue, $assoc);
	}
	
	function onQuickFormEvent($event, $arg, &$caller)
    {
        if ('updateValue' != $event) {
            parent::onQuickFormEvent($event, $arg, $caller);
        } else {
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
					if($this->_flagFrozen) 
						$this->_removeValue($caller->_submitValues);
					else {
	                    $value = $this->_findValue($caller->_submitValues);
						if($value!==null)
							$value = strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
					}
                    if (null === $value) {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                if (null !== $value) {
                    $this->setValue($value);
                }
        }
        return true;
    } // end func onQuickFormEvent

	function setValue($value) {
		if ($value) $this->updateAttributes(array('value'=>Base_RegionalSettingsCommon::time2reg($value,false,true,false)));
		else $this->updateAttributes(array('value'=>''));
	} // end func setValue

*/
	function getValue() {
		$value = $this->getAttribute('value');
		if (!$value) return $value;
//		print('get_value('.$this->getName().')='.$value.' '.Base_RegionalSettingsCommon::time2reg($value,false,true,false).'<hr>');
                if(!is_numeric($value) && is_string($value) && !strtotime($value)) return $value;
		return Base_RegionalSettingsCommon::time2reg($value,false,true,false);
	} // end func setValue

	function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } 
		if ($value=='') return $this->_prepareValue('', $assoc);
		$cleanValue = date('Y-m-d',Base_RegionalSettingsCommon::reg2time($value,false));
		return $this->_prepareValue($cleanValue, $assoc);
	}

    function onQuickFormEvent($event, $arg, &$caller) {
		if($event=='updateValue' && is_callable(array($caller,'applyFilter')))
			$caller->applyFilter($this->getName(),$this->reg2time(...));
		return parent::onQuickFormEvent($event,$arg,$caller);
	}
	
	function reg2time($value) {
		if (!$value) return '';
		return strftime('%Y-%m-%d',Base_RegionalSettingsCommon::reg2time($value,false));
	}
} //end class HTML_QuickForm_datepicker
?>
