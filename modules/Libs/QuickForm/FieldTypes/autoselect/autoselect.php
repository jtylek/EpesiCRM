<?php

require_once 'HTML/QuickForm/select.php';
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

    public static function get_autocomplete_suggestbox($string, $callback, $args, $format=null) {
		if (!is_string($string)) $string = '';
		$suggestbox_args = $args;
    	array_unshift($suggestbox_args, $string);
    	$result = call_user_func_array($callback, $suggestbox_args);
    	$res =[];
    	foreach ($result as $id => $description) {
    	    $res[] = ['id'=>$id, 'text' => html_entity_decode($description)];
        }
    	return $res;
    }

    function toHtml()
    {
	$val = $this->getValue();
	if (isset($val[0]) && $val[0]!='' && !isset($this->__options[$val[0]]) && $this->more_opts_format) {
		$label = call_user_func_array($this->more_opts_format, array($val[0], $this->more_opts_args));
		if ($label!==null) $this->addOption(strip_tags($label), $val[0]);
	}
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $tabs    = $this->_getTabs();

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

            $strValues = is_array($this->_values)? array_map('strval', $this->_values): array();

            $options = '';
            foreach ($this->_options as $option) {
                if (!empty($strValues) && in_array($option['attr']['value'], $strValues, true)) {
                    $option['attr']['selected'] = 'selected';
                }
                $options .= $tabs . "\t<option" . $this->_getAttrString($option['attr']) . '>' .
                            $option['text'] . "</option>\n";
            }
			$callback = array($this->more_opts_callback, $this->more_opts_args, $this->more_opts_format);

            $key = md5(serialize($callback).$this->getAttribute('id'));
            $_SESSION['client']['quickform']['autocomplete'][$key] = array(
                'callback'=>array('HTML_QuickForm_autoselect','get_autocomplete_suggestbox'),
                'field'=>'q',
                'args'=> $callback
            );

            //TODO-PJ: Add pagination

            $cid = CID;
            $hint = __('Start typing to search...');
            $select2_js = <<<js
                jQuery("select[name='{$myName}']").select2({
                    placeholder: "{$hint}",
                    ajax: {
                        url: "modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete_update.php",
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
                    <!--{$this->getComment()}-->
                    <select style="width: 100%" {$attrString}>
                      {$options}
                    </select>
html;

            eval_js($select2_js);
            return 	$select2;
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