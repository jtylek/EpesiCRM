<?php

abstract class HTML_QuickForm_multi extends HTML_QuickForm_element
{
    use toHtml;

    /**
     * Default values of the SELECT
     *
     * @var       string
     * @access    private
     */
    var $_values = array();

    /**
     * Hash table to hold original keys of given options
     *
     * @var       string
     * @access    private
     */
    var $keyhash = array();

    /**
     * Returns the current API version
     *
     * @access    public
     * @return    double
     */
    function apiVersion()
    {
        return 2.3;
    }

    /**
     * Sets the default values of the select box
     *
     * @param     mixed    $values  Array or comma delimited string of selected values
     * @access    public
     * @return    void
     */
    function setSelected($values)
    {
    	if (!is_array($this->_values)) $this->_values = array();
        if (!is_array($values)) {
            $values = array($values);
        }
    	foreach($values as $k=>$v)
        	if (!in_array($v,$this->_values)) $this->_values[] = $v;
    }

    /**
     * Returns an array of the selected values
     *
     * @access    public
     * @return    array of selected values
     */
    function getSelected()
    {
        return $this->_values;
    }

    function getMultiple()
    {
        return true;
    }

    /**
     * Returns the element name (possibly with brackets appended)
     *
     * @access    public
     * @return    string
     */
    function getPrivateName()
    {
        return $this->getName();
    }

    /**
     * Sets the value of the form element
     *
     * @param     mixed    $values  Array or comma delimited string of selected values
     * @access    public
     * @return    void
     */
    function setValue($value)
    {
        $this->setSelected($value);
    }

    /**
     * Returns an array of the selected values
     *
     * @access    public
     * @return    array of selected values
     */
    function getValue()
    {
        return $this->_values;
    }

    /**
     * Sets the select field size
     *
     * @param     int    $size  Size of select  field
     * @access    public
     * @return    void
     */
    function setSize($size)
    {
        $this->updateAttributes(array('size' => $size));
    }

    /**
     * Returns the select field size
     *
     * @access    public
     * @return    int
     */
    function getSize()
    {
        return $this->getAttribute('size');
    }

    function onQuickFormEvent($event, $arg, &$caller)
    {
        if ('updateValue' == $event) {
            $value = $this->_findValue($caller->_constantValues);
            if (null === $value) {
                $value = $this->_findValue($caller->_submitValues);
                // Fix for bug #4465 & #5269
                // XXX: should we push this to element::onQuickFormEvent()?
                if (null === $value && ((is_callable(array($caller,'isSubmitted')) && !$caller->isSubmitted()) || $this->isFrozen())) {
                    $value = $this->_findValue($caller->_defaultValues);
                }
            }
            if (null !== $value) {
                $this->setValue($value);
            }
            return true;
        } else {
            return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }
}