<?php

require_once 'HTML/QuickForm/text.php';

/**
 * HTML class for an autocomplete field
 * 
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Arkadiusz Bisaga <abisaga@telaxus.com>
 */
class HTML_QuickForm_autocomplete extends HTML_QuickForm_text {
    private $callback;
    private $args = array();
    private $on_hide_js_code = '';
            
    /**
     * Class constructor
     * 
     * @param string    $elementName    (optional)Input field name attribute
     * @param string    $elementLabel   (optional)Input field label
     * @param mixed 	$callback		(optional)Method callback that will be used to populate table
     * @param mixed     $attributes     (optional)Either a typical HTML attribute string 
     *                                      or an associative array
     * @return void
     */
    function HTML_QuickForm_autocomplete($elementName=null, $elementLabel=null, $callback=null, $args=null, $attributes=null) {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->callback = $callback;
        if (!$args || !is_array($args)) $args = array();
        $this->args = $args;
        $this->_persistantFreeze = true;
        $this->setType('text');
    }
    
    function on_hide_js($js) {
    	$this->on_hide_js_code = $js;
    }

    function toHtml() {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
        	$name = $this->getAttribute('name');
        	$id = $this->getAttribute('id');
        	if (!$id) {
        		$id = '__autocomplete_id_'.$name;
        		$this->setAttribute('id', $id);
        	}
			print('<div id="'.$id.'_suggestbox" class="autocomplete">&nbsp;</div>');
			$key = md5(serialize($this->callback).$id);
			$_SESSION['client']['quickform']['autocomplete'][$key] = array('callback'=>$this->callback, 'field'=>$name, 'args'=>$this->args);
			eval_js('var epesi_autocompleter = new Ajax.Autocompleter(\''.$id.'\', \''.$id.'_suggestbox\', \'modules/Libs/QuickForm/FieldTypes/autocomplete/autocomplete_update.php?'.http_build_query(array('cid'=>CID, 'key'=>$key)).'\', \'\');');

			// TODO: not really neat, need to extend the function automatically
			if ($this->on_hide_js_code) eval_js('epesi_autocompleter.hide=function(){'.
					'this.stopIndicator();'.
				    'if (Element.getStyle(this.update, "display") != "none") {'.
				    '    this.options.onHide(this.element, this.update);'.
				    '}'.
				    'if (this.iefix) {'.
				    '    Element.hide(this.iefix);'.
				    '}'.
					$this->on_hide_js_code.
				'}');

            return $this->_getTabs() . '<input' . $this->_getAttrString($this->_attributes) . ' />';
        }
    } //end func toHtml
	        
}
?>
