<?php
require_once("HTML/QuickForm/input.php");

/**
 * HTML class for a text field
 * 
 * @author       Adam Daniel <adaniel1@eesus.jnj.com>
 * @author       Bertrand Mansion <bmansion@mamasam.com>
 * @version      1.0
 * @since        PHP4.04pl1
 * @access       public
 */
class HTML_QuickForm_datepicker extends HTML_QuickForm_input
{
                
    function HTML_QuickForm_datepicker($elementName=null, $elementLabel=null, $attributes=null) {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->setType('text');
    } //end constructor
        
	    function toHtml()
	    {
		$str = "";
		if ($this->_flagFrozen) {
			$str .= $this->getFrozenHtml();
		} else {
			$id = $this->getAttribute('id');
			if(!isset($id)) {
				$id = 'dupne_id';
				$this->setAttributes(array('id'=>$id));
			}
			$str .= $this->_getTabs() . '<input' . $this->_getAttrString($this->_attributes) . ' />'.
				Utils_CalendarCommon::show(
					'alert(\'opk\');new Ajax.Updater(\''.Epesi::escapeJS($id,false).'\',\'modules/Utils/Calendar/up.php\','.
					'{method:\'post\', parameters:{date: __YEAR__+\'-\'+__MONTH__+\'-\'+__DAY__}})');
//				'new Ajax.Updater(id,\''.$this->get_module_dir().'refresh.php\',{method:\'post\', parameters:{path: path}});'.
		}
		return $str;
	} //end func toHtml
    
} //end class HTML_QuickForm_text
?>