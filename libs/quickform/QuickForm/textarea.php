<?php
/**
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * Base class for form elements
 */ 
require_once 'HTML/QuickForm/element.php';

/**
 * HTML class for a textarea type field
 *
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 */
class HTML_QuickForm_textarea extends HTML_QuickForm_element
{
    /**
     * Field value
     *
     * @var       string
     * @access    private
     */
    var $_value = null;

    /**
     * Class constructor
     *
     * @param     string    Input field name attribute
     * @param     mixed     Label(s) for a field
     * @param     mixed     Either a typical HTML attribute string or an associative array
     */
    function HTML_QuickForm_textarea($elementName=null, $elementLabel=null, $attributes=null)
    {
        HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'textarea';
    }

    /**
     * Sets the input field name
     *
     * @param     string    $name   Input field name attribute
     */
    function setName($name)
    {
        $this->updateAttributes(array('name'=>$name));
    }

    /**
     * Returns the element name
     *
     * @return    string
     */
    function getName()
    {
        return $this->getAttribute('name');
    }

    /**
     * Sets value for textarea element
     *
     * @param     string    $value  Value for textarea element
     */
    function setValue($value)
    {
        $this->_value = $value;
    }

    /**
     * Returns the value of the form element
     *
     * @return    string
     */
    function getValue()
    {
        return $this->_value;
    }

    /**
     * Sets wrap type for textarea element
     *
     * @param     string    $wrap  Wrap type
     */
    function setWrap($wrap)
    {
        $this->updateAttributes(array('wrap' => $wrap));
    }

    /**
     * Sets height in rows for textarea element
     *
     * @param     string    $rows  Height expressed in rows
     */
    function setRows($rows)
    {
        $this->updateAttributes(array('rows' => $rows));
    }

    /**
     * Sets width in cols for textarea element
     *
     * @param     string    $cols  Width expressed in cols
     */
    function setCols($cols)
    {
        $this->updateAttributes(array('cols' => $cols));
    }

    /**
     * Returns the textarea element in HTML
     *
     * @return    string
     */
    function toHtml()
    {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            return $this->_getTabs() .
                   '<textarea' . $this->_getAttrString($this->_attributes) . '>' .
                   // because we wrap the form later we don't want the text indented
                   preg_replace("/(\r\n|\n|\r)/", '&#010;', htmlspecialchars($this->_value)) .
                   '</textarea>';
        }
    }

    /**
     * Returns the value of field without HTML tags (in this case, value is changed to a mask)
     *
     * @return    string
     */
    function getFrozenHtml()
    {
        $value = htmlspecialchars($this->getValue());
        if ($this->getAttribute('wrap') == 'off') {
            $html = $this->_getTabs() . '<pre>' . $value."</pre>\n";
        } else {
            $html = nl2br($value)."\n";
        }
        return $html . $this->_getPersistantData();
    }
}
?>
