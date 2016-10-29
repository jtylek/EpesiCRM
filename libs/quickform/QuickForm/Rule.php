<?php
/**
 * @package     HTML_QuickForm
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * Abstract base class for QuickForm validation rules
 *
 * @package     HTML_QuickForm
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 */
class HTML_QuickForm_Rule
{
   /**
    * Name of the rule to use in validate method
    *
    * This property is used in more global rules like Callback and Regex
    * to determine which callback and which regex is to be used for validation
    *
    * @var  string
    * @access   public
    */
    var $name;

   /**
    * Validates a value
    * 
    * @access public
    * @abstract
    */
    function validate($value)
    {
        return true;
    }

    /**
     * Sets the rule name
     *
     * @param  string    rule name
     */
    public function setName($ruleName)
    {
        $this->name = $ruleName;
    }

    /**
     * Returns the javascript test (the test should return true if the value is INVALID)
     *
     * @param     mixed     Options for the rule
     * @return    array     first element is code to setup validation, second is the check itself
     * @abstract
     */
    public function getValidationScript($options = null)
    {
        return array('', '');
    }
}
?>