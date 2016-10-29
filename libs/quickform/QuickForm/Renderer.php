<?php
/**
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * An abstract base class for QuickForm renderers
 *
 * The class implements a Visitor design pattern
 *
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Alexey Borzov <avb@php.net>
 */
class HTML_QuickForm_Renderer
{
   /**
    * Constructor
    *
    * @access public
    */
    function HTML_QuickForm_Renderer()
    {
    }

   /**
    * Called when visiting a form, before processing any form elements
    *
    * @param    HTML_QuickForm  a form being visited
    */
    function startForm(&$form)
    {
        return;
    }

   /**
    * Called when visiting a form, after processing all form elements
    *
    * @param    HTML_QuickForm  a form being visited
    */
    function finishForm(&$form)
    {
        return;
    }

   /**
    * Called when visiting a header element
    *
    * @param    HTML_QuickForm_header   a header element being visited
    */
    function renderHeader(&$header)
    {
        return;
    }

   /**
    * Called when visiting an element
    *
    * @param    HTML_QuickForm_element  form element being visited
    * @param    bool                    Whether an element is required
    * @param    string                  An error message associated with an element
    */
    function renderElement(&$element, $required, $error)
    {
        return;
    }

   /**
    * Called when visiting a hidden element
    *
    * @param    HTML_QuickForm_element  a hidden element being visited
    * @param    bool                    Whether an element is required
    * @param    string                  An error message associated with an element
    */
    function renderHidden(&$element)
    {
        return;
    }

   /**
    * Called when visiting a raw HTML/text pseudo-element
    *
    * Only implemented in Default renderer. Usage of 'html' elements is
    * discouraged, templates should be used instead.
    *
    * @param    HTML_QuickForm_html     a 'raw html' element being visited
    */
    function renderHtml(&$data)
    {
        return;
    }

   /**
    * Called when visiting a group, before processing any group elements
    *
    * @param    HTML_QuickForm_group    A group being visited
    * @param    bool                    Whether a group is required
    * @param    string                  An error message associated with a group
    */
    function startGroup(&$group, $required, $error)
    {
        return;
    }

   /**
    * Called when visiting a group, after processing all group elements
    *
    * @param    HTML_QuickForm_group    A group being visited
    * @return   void
    */
    function finishGroup(&$group)
    {
        return;
    }
}
?>