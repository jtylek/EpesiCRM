<?php
/**
 * Base class for all HTML classes
 *
 * @package     HTML_Common
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @copyright   2001-2009 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * Base class for all HTML classes
 *
 * @package     HTML_Common
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 */
abstract class HTML_Common
{
    /**
     * Associative array of attributes
     *
     * @var     array
     * @access  private
     */
    var $_attributes = array();

    /**
     * Class constructor
     *
     * @param    mixed   $attributes     Associative array of table tag attributes or HTML attributes name="value" pairs
     */
    public function __construct($attributes = null)
    {
        $this->setAttributes($attributes);
    }

    /**
     * Returns the current API version
     *
     * @returns  double
     */
    public function apiVersion()
    {
        return 1.7;
    }

    /**
     * Returns an HTML formatted attribute string
     *
     * @param    array   $attributes
     * @return   string
     * @access   private
     */
    function _getAttrString($attributes)
    {
        $strAttr = '';

        if (is_array($attributes)) {
            $charset = self::charset();
            foreach ($attributes as $key => $value) {
                $strAttr .= ' ' . $key . '="' . htmlspecialchars($value, ENT_COMPAT, $charset) . '"';
            }
        }
        return $strAttr;
    }

    /**
     * Returns a valid atrributes array from either a string or array
     *
     * @param    mixed   $attributes     Either a typical HTML attribute string or an associative array
     * @access   private
     * @return   array
     */
    function _parseAttributes($attributes)
    {
        if (is_array($attributes)) {
            $ret = array();
            foreach ($attributes as $key => $value) {
                if (is_int($key)) {
                    $key = $value = strtolower($value);
                } else {
                    $key = strtolower($key);
                }
                $ret[$key] = $value;
            }
            return $ret;

        } elseif (is_string($attributes)) {
            $preg = "/(([A-Za-z_:]|[^\\x00-\\x7F])([A-Za-z0-9_:.-]|[^\\x00-\\x7F])*)" .
                "([ \\n\\t\\r]+)?(=([ \\n\\t\\r]+)?(\"[^\"]*\"|'[^']*'|[^ \\n\\t\\r]*))?/";
            if (preg_match_all($preg, $attributes, $regs)) {
                for ($counter=0; $counter<count($regs[1]); $counter++) {
                    $name  = $regs[1][$counter];
                    $check = $regs[0][$counter];
                    $value = $regs[7][$counter];
                    if (trim($name) == trim($check)) {
                        $arrAttr[strtolower(trim($name))] = strtolower(trim($name));
                    } else {
                        if (substr($value, 0, 1) == "\"" || substr($value, 0, 1) == "'") {
                            $arrAttr[strtolower(trim($name))] = substr($value, 1, -1);
                        } else {
                            $arrAttr[strtolower(trim($name))] = trim($value);
                        }
                    }
                }
                return $arrAttr;
            }
        }
    }

    /**
     * Returns the array key for the given non-name-value pair attribute
     *
     * @param     string    $attr         Attribute
     * @param     array     $attributes   Array of attribute
     * @since     1.0
     * @access    private
     * @return    bool
     */
    function _getAttrKey($attr, $attributes)
    {
        if (isset($attributes[strtolower($attr)])) {
            return true;
        } else {
            return null;
        }
    }

    /**
     * Updates the attributes in $attr1 with the values in $attr2 without changing the other existing attributes
     *
     * @param    array $attr1 Original attributes array
     * @param    array $attr2 New attributes array
     * @access   private
     * @return bool
     */
    function _updateAttrArray(&$attr1, $attr2)
    {
        if (!is_array($attr2)) {
            return false;
        }
        foreach ($attr2 as $key => $value) {
            $attr1[$key] = $value;
        }
    }

    /**
     * Removes the given attribute from the given array
     *
     * @param     string    $attr           Attribute name
     * @param     array     $attributes     Attribute array
     * @access    private
     * @return    void
     */
    function _removeAttr($attr, &$attributes)
    {
        $attr = strtolower($attr);
        if (isset($attributes[$attr])) {
            unset($attributes[$attr]);
        }
    }

    /**
     * Returns the value of the given attribute
     *
     * @param     string    $attr   Attribute name
     * @return    string|null   returns null if an attribute does not exist
     */
    public function getAttribute($attr)
    {
        $attr = strtolower($attr);
        if (isset($this->_attributes[$attr])) {
            return $this->_attributes[$attr];
        }
        return null;
    }

    /**
     * Sets the value of the attribute
     *
     * @param   string  $name Attribute name
     * @param   string  $value Attribute value (will be set to $name if omitted)
     */
    public function setAttribute($name, $value = null)
    {
        $name = strtolower($name);
        if (is_null($value)) {
            $value = $name;
        }
        $this->_attributes[$name] = $value;
    }

    /**
     * Sets the HTML attributes
     *
     * @param    mixed   $attributes     Either a typical HTML attribute string or an associative array
     */
    public function setAttributes($attributes)
    {
        $this->_attributes = $this->_parseAttributes($attributes);
    }

    /**
     * Returns the assoc array (default) or string of attributes
     *
     * @param     bool    $asString Whether to return the attributes as string
     * @return    mixed   attributes
     */
    public function getAttributes($asString = false)
    {
        if ($asString) {
            return $this->_getAttrString($this->_attributes);
        } else {
            return $this->_attributes;
        }
    }

    /**
     * Updates the passed attributes without changing the other existing attributes
     *
     * @param    mixed   $attributes     Either a typical HTML attribute string or an associative array
     */
    function updateAttributes($attributes)
    {
        $this->_updateAttrArray($this->_attributes, $this->_parseAttributes($attributes));
    }

    /**
     * Removes an attribute
     *
     * @param     string    $attr   Attribute name
     */
    public function removeAttribute($attr)
    {
        $this->_removeAttr($attr, $this->_attributes);
    }

    /**
     * Abstract method.  Must be extended to return the objects HTML
     *
     * @return    string
     */
    abstract function toHtml();

    /**
     * Displays the HTML to the screen
     */
    public function display()
    {
        print $this->toHtml();
    }

    /**
     * Sets the charset to use by htmlspecialchars() function
     *
     * Since this parameter is expected to be global, the function is designed
     * to be called statically:
     * <code>
     * HTML_Common::charset('utf-8');
     * </code>
     * or
     * <code>
     * $charset = HTML_Common::charset();
     * </code>
     *
     * @param   string  $newCharset New charset to use. Omit if just getting the current value. Consult the htmlspecialchars() docs for a list of supported character sets.
     * @return  string  Current charset
     */
    public static function charset($newCharset = null)
    {
        static $charset = 'ISO-8859-1';

        if (!is_null($newCharset)) {
            $charset = $newCharset;
        }
        return $charset;
    }

    public function addClass($addClass)
    {
        $class = $this->getAttribute('class');
        if($class != null) {
            $class = $class.' '.$addClass;
        } else {
            $class = $addClass;
        }
        $this->setAttribute('class', $class);
    }
}
?>
