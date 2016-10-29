<?php
/**
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * HTML class for static data
 */ 
require_once 'HTML/QuickForm/static.php';

/**
 * A pseudo-element used for adding headers to form
 *
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 */
class HTML_QuickForm_header extends HTML_QuickForm_static
{
   /**
    * Class constructor
    *
    * @param string $elementName    Header name
    * @param string $text           Header text
    */
    function HTML_QuickForm_header($elementName = null, $text = null)
    {
        $this->HTML_QuickForm_static($elementName, null, $text);
        $this->_type = 'header';
    }

   /**
    * Accepts a renderer
    *
    * @param HTML_QuickForm_Renderer    renderer object
    * @param bool $sc1                  unused, for signature compatibility
    * @param bool $sc2                  unused, for signature compatibility
    * @access public
    * @return void 
    */
    function accept(&$renderer, $sc1 = false, $sc2 = null)
    {
        $renderer->renderHeader($this);
    }
}
?>
