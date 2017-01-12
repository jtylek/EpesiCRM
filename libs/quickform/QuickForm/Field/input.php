<?php
/**
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * Base class for <input /> form elements
 *
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @abstract
 */
class HTML_QuickForm_input extends HTML_QuickForm_element
{
    use toHtml;

    /**
     * Sets the element type
     *
     * @param     string    $type   Element type
     */
    public function setType($type)
    {
        $this->_type = $type;
        $this->updateAttributes(array('type'=>$type));
    }

    /**
     * Sets the value of the form element
     *
     * @param     string    $value      Default value of the form element
     */
    public function setValue($value)
    {
        $this->updateAttributes(array('value'=>$value));
    }

    /**
     * Returns the value of the form element
     *
     * @return    string
     */
    public function getValue()
    {
        return $this->getAttribute('value');
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param     string    $event  Name of event
     * @param     mixed     $arg    event arguments
     * @param     object    &$caller calling object
     * @return true
     */
    public function onQuickFormEvent($event, $arg, &$caller)
    {
        // do not use submit values for button-type elements
        $type = $this->getType();
        if (('updateValue' != $event) ||
            ('submit' != $type && 'reset' != $type && 'image' != $type && 'button' != $type)) {
            parent::onQuickFormEvent($event, $arg, $caller);
        } else {
            $value = $this->_findValue($caller->_constantValues);
            if (null === $value) {
                $value = $this->_findValue($caller->_defaultValues);
            }
            if (null !== $value) {
                $this->setValue($value);
            }
        }
        return true;
    }

   /**
    * We don't need values from button-type elements (except submit) and files
    */
    public function exportValue(&$submitValues, $assoc = false)
    {
        $type = $this->getType();
        if ('reset' == $type || 'image' == $type || 'button' == $type || 'file' == $type) {
            return null;
        } else {
            return parent::exportValue($submitValues, $assoc);
        }
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return '<input' . $this->_getAttrString($this->_attributes) . ' />';
    }
}
?>
