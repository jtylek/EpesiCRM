<?php

require_once 'HTML/QuickForm/select.php';
require_once('modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete.php');
load_js('modules/Libs/QuickForm/FieldTypes/autoselect/autoselect.js');

/**
 * HTML class for an autoselect field
 * 
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Arkadiusz Bisaga <abisaga@telaxus.com>
 */
class HTML_QuickForm_autoselect extends HTML_QuickForm_select {
	private $more_opts_callback = null;
	private $more_opts_args = null;
	private $more_opts_format = null;
    private $on_hide_js_code = '';
	private $__options = array();
	
    /**
     * Class constructor
     * 
     * @param     string    Select name attribute
     * @param     mixed     Label(s) for the select
     * @param     mixed     Data to be used to populate options
     * @param     mixed     Either a typical HTML attribute string or an associative array
     * @since     1.0
     * @access    public
     * @return    void
     */
    function HTML_QuickForm_autoselect($elementName=null, $elementLabel=null, $options=null, $more_opts_callback=null, $format=null, $attributes=null) {
        HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'select';
        if (isset($options)) {
			$this->load($options);
			$this->__options = $options;
        }
		$this->more_opts_callback = $more_opts_callback[0];
		$this->more_opts_args = $more_opts_callback[1];
		$this->more_opts_format = $format;
    } //end constructor

    function on_hide_js($js) {
    	$this->on_hide_js_code = $js;
    }

    public static function get_autocomplete_suggestbox($string, $callback, $args) {
		if (!is_string($string)) $string = '';
    	array_unshift($args, $string);
    	$result = call_user_func_array($callback, $args);
    	$ret = '<ul style="width:auto;">';
    	if (empty($result)) {
			$ret .= '<li><span style="text-align:center;font-weight:bold;" class="informal">'.__('No records founds').'</span></li>';
    	}
		if (is_array($result)) {
			foreach ($result as $k=>$v) {
				$ret .= '<li><span style="display:none;">'.$k.'__'.$v.'</span><span class="informal">'.str_replace(' ','&nbsp;',$v).'</span></li>';
			}
			$ret .= '</ul>';
		} else {
			$ret = $result;
		}
    	return $ret;
    }

    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $tabs    = $this->_getTabs();
            $strHtml = '';

            if ($this->getComment() != '') {
                $strHtml .= $tabs . '<!-- ' . $this->getComment() . " //-->\n";
            }

            $myName = $this->getName();
			$this->updateAttributes(array('id'=>$myName));
			eval_js('Event.observe("'.$myName.'", "keydown", function(ev){autoselect_start_searching("'.$myName.'", ev.keyCode)});');
            if (!$this->getMultiple()) {
                $attrString = $this->_getAttrString($this->_attributes);
            } else {
                $this->setName($myName . '[]');
                $attrString = $this->_getAttrString($this->_attributes);
                $this->setName($myName);
            }
            $strHtml .= $tabs . '<select' . $attrString . ">\n";
			$mode = Base_User_SettingsCommon::get('Libs_QuickForm','autoselect_mode');

			$val = $this->getValue();
			if (isset($val[0]) && $val[0]!='' && !isset($this->__options[$val[0]])) {
				$label = call_user_func_array($this->more_opts_format, array($val[0], $this->more_opts_args));
				if ($label!==null) $this->addOption(strip_tags($label), $val[0]);
			}
				
            $strValues = is_array($this->_values)? array_map('strval', $this->_values): array();
			$hint = __('Start typing to search...');
			$strHtml .= '<option value="">'.$hint.'</option>';
//			eval_js('set_style_for_search_tip = function(el){if($(el).value=="__SEARCH_TIP__")$(el).className="autoselect_search_tip";else $(el).className=""}');
//			eval_js('set_style_for_search_tip("'.$myName.'");');
//			eval_js('Event.observe("'.$myName.'", "change", function (){set_style_for_search_tip("'.$myName.'");});');
            foreach ($this->_options as $option) {
                if (!empty($strValues) && in_array($option['attr']['value'], $strValues, true)) {
                    $option['attr']['selected'] = 'selected';
                }
                $strHtml .= $tabs . "\t<option" . $this->_getAttrString($option['attr']) . '>' .
                            $option['text'] . "</option>\n";
            }
			$strHtml .= $tabs . '</select>';

			$text_attrs = array('placeholder'=>$hint);
			$search = new HTML_QuickForm_autocomplete($myName.'__search','', array('HTML_QuickForm_autoselect','get_autocomplete_suggestbox'), array($this->more_opts_callback, $this->more_opts_args), $text_attrs);
			$search->on_hide_js('autoselect_on_hide("'.$myName.'",'.($mode?'1':'0').');'.$this->on_hide_js_code);

			if ($mode==0) eval_js('Event.observe("'.$myName.'","change",function(){if($("'.$myName.'").value=="")autoselect_start_searching("'.$myName.'");});');
			
			if (isset($val[0]) && $val[0]!='')
				$mode=1;
			
            return 	'<span id="__'.$myName.'_select_span"'.($mode==0?' style="display:none;"':'').'>'.
						$strHtml.
					'</span>'.
					'<span id="__'.$myName.'_autocomplete_span"'.($mode==1?' style="display:none;"':'').'>'.
						$search->toHtml().
					'</span>';
        }
    } //end func toHtml

    function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } elseif(!is_array($value)) {
            $value = array($value);
        }
		$cleanValue = $value;
        if (is_array($cleanValue) && !$this->getMultiple()) {
			if (!isset($cleanValue[0])) $cleanValue[0] = '';
            return $this->_prepareValue($cleanValue[0], $assoc);
        } else {
            return $this->_prepareValue($cleanValue, $assoc);
        }
	}
}

?>