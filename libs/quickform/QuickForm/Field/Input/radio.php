<?php
/**
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * HTML class for a radio type element
 *
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 */
class HTML_QuickForm_radio extends HTML_QuickForm_input
{
    /**
     * Radio display text
     *
     * @var       string
     * @access    private
     */
    var $_text = '';

    /**
     * Class constructor
     *
     * @param     string    $elementName Input field name attribute
     * @param     mixed     $elementLabel Label(s) for a field
     * @param     string    $text Text to display near the radio
     * @param     string    $value Input field value
     * @param     mixed     $attributes Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName=null, $elementLabel=null, $text=null, $value=null, $attributes=null)
    {
        parent::__construct($elementName, $elementLabel, $attributes);
        if (isset($value)) {
            $this->setValue($value);
        }
        $this->_persistantFreeze = true;
        $this->setType('radio');
        $this->_text = $text;
        $this->_generateId();
    }

    /**
     * Sets whether radio button is checked
     *
     * @param     bool    $checked  Whether the field is checked or not
     */
    public function setChecked($checked)
    {
        if (!$checked) {
            $this->removeAttribute('checked');
        } else {
            $this->updateAttributes(array('checked'=>'checked'));
        }
    }

    /**
     * Returns whether radio button is checked
     *
     * @return    string
     */
    public function getChecked()
    {
        return $this->getAttribute('checked');
    }

    /**
     * Returns the value of field without HTML tags
     *
     * @return    string
     */
    public function getFrozenHtml()
    {
      if ($this->getChecked()) {
          return '<div class="radio_on"></div>' . $this->_getPersistantData() . $this->_text;
      } else {
          return '<div class="radio_off"></div>' . $this->_text;
      }
    }

    /**
     * Sets the radio text
     *
     * @param     string    $text  Text to display near the radio button
     */
    public function setText($text)
    {
        $this->_text = $text;
    }

    /**
     * Returns the radio text
     *
     * @return    string
     */
    public function getText()
    {
        return $this->_text;
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
        switch ($event) {
            case 'updateValue':
                // constant values override both default and submitted ones
                // default values are overriden by submitted
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    if($this->_flagFrozen)
                        $this->_removeValue($caller->_submitValues);
                    else
                        $value = $this->_findValue($caller->_submitValues);
                    if (null === $value) {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                if (!is_null($value) && $value == $this->getValue()) {
                    $this->setChecked(true);
                } else {
                    $this->setChecked(false);
                }
                break;
            case 'setGroupValue':
                if ($arg == $this->getValue()) {
                    $this->setChecked(true);
                } else {
                    $this->setChecked(false);
                }
                break;
            default:
                parent::onQuickFormEvent($event, $arg, $caller);
        }
        return true;
    }

   /**
    * Returns the value attribute if the radio is checked, null if it is not
    */
    public function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (null === $value) {
            $value = $this->getChecked()? $this->getValue(): null;
        } elseif ($value != $this->getValue()) {
            $value = null;
        }
        return $this->_prepareValue($value, $assoc);
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return parent::getHtml() . '<label for="' . $this->getAttribute('id') . '">' . $this->_text . '</label>';
    }
}
?>
