<?php
/**
 * HTML class for a multiselect
 *
 * @author       Arkadiusz Bisaga <abisaga@telaxus.com> based on HTML_QuickForm select.php
 * @license MIT
 * @version 1.0
 * @package epesi-libs
 * @subpackage QuickForm
 */
require_once('HTML/QuickForm/select.php');

class HTML_QuickForm_multiselect extends HTML_QuickForm_element
{

    /**
     * Contains the select options
     *
     * @var       array
     * @access    private
     */
    var $_options = array();

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

	private $list_sep = '__SEP__';
    /**
     * Class constructor
     *
     * @param     string    Select name attribute
     * @param     mixed     Label(s) for the select
     * @param     mixed     Data to be used to populate options
     * @param     mixed     Either a typical HTML attribute string or an associative array
     * @access    public
     * @return    void
     */
    function HTML_QuickForm_multiselect($elementName=null, $elementLabel=null, $options=null, $attributes=null)
    {
        HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'select';
        if (isset($options)) {
            $this->load($options);
        }
    }

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
     * Sets the input field name
     *
     * @param     string    $name   Input field name attribute
     * @access    public
     * @return    void
     */
    function setName($name)
    {
        $this->updateAttributes(array('name' => $name));
    }

    /**
     * Returns the element name
     *
     * @access    public
     * @return    string
     */
    function getName()
    {
        return $this->getAttribute('name');
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

    /**
     * Adds a new OPTION to the SELECT
     *
     * @param     string    $text       Display text for the OPTION
     * @param     string    $value      Value for the OPTION
     * @param     mixed     $attributes Either a typical HTML attribute string
     *                                  or an associative array
     * @access    public
     * @return    void
     */
    function addOption($text, $value, $attributes=null)
    {
        if (null === $attributes) {
            $attributes = array('value' => $value);
        } else {
            $attributes = $this->_parseAttributes($attributes);
            if (isset($attributes['selected'])) {
                // the 'selected' attribute will be set in toHtml()
                $this->_removeAttr('selected', $attributes);
                if (is_null($this->_values)) {
                    $this->_values = array($value);
                } elseif (!in_array($value, $this->_values)) {
                    $this->_values[] = $value;
                }
            }
            $this->_updateAttrArray($attributes, array('value' => $value));
        }
        $this->_options[] = array('text' => $text, 'attr' => $attributes);
    }

    /**
     * Loads the options from an associative array
     *
     * @param     array    $arr     Associative array of options
     * @param     mixed    $values  (optional) Array or comma delimited string of selected values
     * @access    public
     * @return    PEAR_Error on error or true
     * @throws    PEAR_Error
     */
    function loadArray($arr, $values=null)
    {
        if (!is_array($arr)) {
            return PEAR::raiseError('Argument 1 of HTML_Select::loadArray is not a valid array');
        }
        if (isset($values)) {
            $this->setSelected($values);
        }
        foreach ($arr as $key => $val) {
            // Warning: new API since release 2.3
            $this->addOption($val, $key);
        }
        return true;
    }

    /**
     * Loads the options from DB_result object
     *
     * If no column names are specified the first two columns of the result are
     * used as the text and value columns respectively
     * @param     object    $result     DB_result object
     * @param     string    $textCol    (optional) Name of column to display as the OPTION text
     * @param     string    $valueCol   (optional) Name of column to use as the OPTION value
     * @param     mixed     $values     (optional) Array or comma delimited string of selected values
     * @access    public
     * @return    PEAR_Error on error or true
     * @throws    PEAR_Error
     */
    function loadDbResult(&$result, $textCol=null, $valueCol=null, $values=null)
    {
        if (!is_object($result) || !is_a($result, 'db_result')) {
            return PEAR::raiseError('Argument 1 of HTML_Select::loadDbResult is not a valid DB_result');
        }
        if (isset($values)) {
            $this->setValue($values);
        }
        $fetchMode = ($textCol && $valueCol) ? DB_FETCHMODE_ASSOC : DB_FETCHMODE_ORDERED;
        while (is_array($row = $result->fetchRow($fetchMode)) ) {
            if ($fetchMode == DB_FETCHMODE_ASSOC) {
                $this->addOption($row[$textCol], $row[$valueCol]);
            } else {
                $this->addOption($row[0], $row[1]);
            }
        }
        return true;
    }

    /**
     * Queries a database and loads the options from the results
     *
     * @param     mixed     $conn       Either an existing DB connection or a valid dsn
     * @param     string    $sql        SQL query string
     * @param     string    $textCol    (optional) Name of column to display as the OPTION text
     * @param     string    $valueCol   (optional) Name of column to use as the OPTION value
     * @param     mixed     $values     (optional) Array or comma delimited string of selected values
     * @access    public
     * @return    void
     * @throws    PEAR_Error
     */
    function loadQuery(&$conn, $sql, $textCol=null, $valueCol=null, $values=null)
    {
        if (is_string($conn)) {
            require_once('DB.php');
            $dbConn = &DB::connect($conn, true);
            if (DB::isError($dbConn)) {
                return $dbConn;
            }
        } elseif (is_subclass_of($conn, "db_common")) {
            $dbConn = &$conn;
        } else {
            return PEAR::raiseError('Argument 1 of HTML_Select::loadQuery is not a valid type');
        }
        $result = $dbConn->query($sql);
        if (DB::isError($result)) {
            return $result;
        }
        $this->loadDbResult($result, $textCol, $valueCol, $values);
        $result->free();
        if (is_string($conn)) {
            $dbConn->disconnect();
        }
        return true;
    }

    /**
     * Loads options from different types of data sources
     *
     * This method is a simulated overloaded method.  The arguments, other than the
     * first are optional and only mean something depending on the type of the first argument.
     * If the first argument is an array then all arguments are passed in order to loadArray.
     * If the first argument is a db_result then all arguments are passed in order to loadDbResult.
     * If the first argument is a string or a DB connection then all arguments are
     * passed in order to loadQuery.
     * @param     mixed     $options     Options source currently supports assoc array or DB_result
     * @param     mixed     $param1     (optional) See function detail
     * @param     mixed     $param2     (optional) See function detail
     * @param     mixed     $param3     (optional) See function detail
     * @param     mixed     $param4     (optional) See function detail
     * @access    public
     * @return    PEAR_Error on error or true
     * @throws    PEAR_Error
     */
    function load(&$options, $param1=null, $param2=null, $param3=null, $param4=null)
    {
        switch (true) {
            case is_array($options):
                return $this->loadArray($options, $param1);
                break;
            case (is_a($options, 'db_result')):
                return $this->loadDbResult($options, $param1, $param2, $param3);
                break;
            case (is_string($options) && !empty($options) || is_subclass_of($options, "db_common")):
                return $this->loadQuery($options, $param1, $param2, $param3, $param4);
                break;
        }
    }

    /**
     * Returns the SELECT in HTML
     *
     * @access    public
     * @return    string
     */
    function toHtml()
    {
    	//print_r($this->_values);
		$this->updateAttributes(array('multiple' => 'multiple'));
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $tabs    = $this->_getTabs();
            $strHtml = '';

            if ($this->getComment() != '') {
                $strHtml .= $tabs . '<!-- ' . $this->getComment() . " //-->\n";
            }

            $myName = $this->getName();
			$mod = $myName;
			$this->setName($myName . 'from[]');
			$this->_attributes['id'] = $myName . '__from';
			$attrString = $this->_getAttrString($this->_attributes);
			$attrArray = $this->getAttributes();
			$leave_selected = isset($attrArray['leave_selected']) ? $attrArray['leave_selected'] : 0;
			$fromElement = '';
            $fromElement .= $tabs . '<select' . $attrString . ' onkeypress="var key=event.which || event.keyCode;if(key==32)add_selected_'.$mod.'();" ondblclick="add_selected_'.$mod.'()">'."\n";
//			print_r($this->_values);
			if (isset($this->_values[0]) && preg_match('/'.addcslashes($this->list_sep,'/').'/i',$this->_values[0])) {
		        $this->_values = explode($this->list_sep,$this->_values[0]);
		        array_shift($this->_values);
			}
			$i = 0;
            foreach ($this->_options as $k=>$option) {
            	$this->keyhash[$i] = $this->_options[$k]['attr']['value'];
            	$kv = array_search($this->_options[$k]['attr']['value'], $this->_values);
            	$i++;
				if ($leave_selected || !(is_array($this->_values) && in_array((string)$this->_options[$k]['attr']['value'], $this->_values)))
                	$fromElement .= $tabs . "\t<option " . $this->_getAttrString($this->_options[$k]['attr']) . ">" . $this->_options[$k]['text'] . "</option>\n";
            }
			$fromElement .= $tabs . '</select>';

			$toElement = '';
			$this->setName($myName . 'to[]');
			$this->_attributes['id'] = $myName . '__to';
			$attrString = $this->_getAttrString($this->_attributes);
			$toElement .= $tabs . '<select' . $attrString . ' onkeypress="var key=event.which || event.keyCode;if(key==32)remove_selected_'.$mod.'();" ondblclick="remove_selected_'.$mod.'();">'."\n";
			$list = '';
			foreach ($this->_options as $option) {
                if (is_array($this->_values) && in_array((string)$option['attr']['value'], $this->_values)) {
	                $toElement .= $tabs . "\t<option " . $this->_getAttrString($option['attr']) . ">" . $option['text'] . "</option>\n";
	                $list .= '__SEP__'.$option['attr']['value'];
                }
            }
			$toElement .= $tabs . '</select>';

			$buttons = array();
			if ($leave_selected) {
				eval_js('remove_selected_'.$mod.' = function(){'.
									'tolist = document.getElementsByName(\''.$myName.'to[]\')[0];'.
									'list = \'\';'.
									'i=0;'.
									'while (i!=tolist.options.length){'.
									'	if (tolist.options[i].selected) '.
									'		tolist.options[i] = null;'.
									'	else {'.
									'		list += \''.$this->list_sep.'\'+tolist.options[i].value;'.
									'		i++;'.
									'	}'.
									'}'.
									'document.getElementsByName(\''.$myName.'\')[0].value=list; '.
									'}');
				eval_js('add_selected_'.$mod.' = function() {'.
									'tolist = document.getElementsByName(\''.$myName.'to[]\')[0];'.
									'fromlist = document.getElementsByName(\''.$myName.'from[]\')[0];'.
									'list = document.getElementsByName(\''.$myName.'\')[0].value; '.
									'i=0;'.
									'while (i!=fromlist.options.length){'.
									'	if (fromlist.options[i].selected){ '.
									'		j=0;'.
									'		while (j != tolist.options.length){'.
									'			if (tolist.options[j].value == fromlist.options[i].value) break;'.
									'			j++;'.
									'		}'.
									'		if (j == tolist.options.length) {'.
									'			tolist.options[j] = new Option(fromlist.options[i].text);'.
									'			tolist.options[j].value = fromlist.options[i].value;'.
									'			list += \''.$this->list_sep.'\'+tolist.options[j].value;'.
									'		}'.
									'	}'.
									'	i++;'.
									'}'.
									'document.getElementsByName(\''.$myName.'\')[0].value=list; '.
									'}');
				$buttons['remove_all'] = '<input align=center type=button value="<<" onclick="'.
									'tolist = this.form[\''.$myName.'to[]\']; '.
									'this.form[\''.$myName.'\'].value = \'\'; '.
									'while (tolist.options.length!=0)'.
									'	tolist.options[0] = null;'.
									'"/></td>';
				$buttons['remove_selected'] = '<input onFocus="focus_by_id(\''.$myName.'__from\');" type=button value="<" onclick="'.
									'remove_selected_'.$mod.'();'.
									'"/>';
				$buttons['add_selected'] = '<input onFocus="focus_by_id(\''.$myName.'_to\');" type=button value=">" onclick="'.
									'add_selected_'.$mod.'();'.
									'"/>';
				$buttons['add_all'] = '<input type=button value=">>" onclick="'.
									'tolist = this.form[\''.$myName.'to[]\']; '.
									'fromlist = this.form[\''.$myName.'from[]\'];'.
									'list = \'\'; '.
									'while (tolist.options.length!=0)'.
									'	tolist.options[0] = null;'.
									'for (i = 0; i < fromlist.options.length; i++) {'.
									'	tolist.options[i] = new Option(fromlist.options[i].text);'.
									'	tolist.options[i].value = fromlist.options[i].value;'.
									'	list += \''.$this->list_sep.'\'+tolist.options[i].value;'.
									'}'.
									'this.form[\''.$myName.'\'].value=list; '.
									'"/>';
			} else {
				eval_js('remove_selected_'.$mod.' = function(){'.
									'tolist = document.getElementsByName(\''.$myName.'to[]\')[0];'.
									'fromlist = document.getElementsByName(\''.$myName.'from[]\')[0];'.
									'list = \'\';'.
									'k = 0;'.
									'i = 0;'.
									'while (k!=tolist.options.length) {'.
									'	if (tolist.options[k].selected) {'.
									'		while (i!=fromlist.options.length && fromlist.options[i].value<tolist.options[k].value) i++;'.
									'		jj = fromlist.length;'.
									'		fromlist.options[jj] = new Option();'.
									'		for( j = jj; j > i; j-- ) {'.
									'			fromlist.options[j].text = fromlist.options[j-1].text;'.
									'			fromlist.options[j].value = fromlist.options[j-1].value;'.
									'		}'.
									'		fromlist.options[i].value = tolist.options[k].value;'.
									'		fromlist.options[i].text = tolist.options[k].text;'.
									' 	} else {'.
									'		list += \''.$this->list_sep.'\'+tolist.options[k].value;'.
									'	}'.
									'	k++;'.
									'}'.
									'for(i = (tolist.length-1); i >= 0; i--) {'.
									'	if(tolist.options[i].selected == true) {'.
									'		tolist.options[i] = null;'.
									'	}'.
									'}'.
									'document.getElementsByName(\''.$myName.'\')[0].value=list;'.
									'}');
				eval_js('add_selected_'.$mod.' = function(){ '.
									'tolist = document.getElementsByName(\''.$myName.'to[]\')[0];'.
									'fromlist = document.getElementsByName(\''.$myName.'from[]\')[0];'.
									'list = \'\';'.
									'k = 0;'.
									'i = 0;'.
									'while (k!=fromlist.length) {'.
									'	if (fromlist.options[k].selected) {'.
									'		while(i < tolist.length && tolist.options[i].value<fromlist.options[k].value) i++;'.
									'		jj = tolist.length;'.
									'		tolist.options[jj] = new Option();'.
									'		for( j = jj; j > i; j-- ) {'.
									'			tolist.options[j].value = tolist.options[j-1].value;'.
									'			tolist.options[j].text = tolist.options[j-1].text;'.
									'		}'.
									'		tolist.options[i].value = fromlist.options[k].value;'.
									'		tolist.options[i].text = fromlist.options[k].text;'.
									' 	} k++;'.
									'}'.
									'for(i = (fromlist.length-1); i >= 0; i--) {'.
									'	if(fromlist.options[i].selected == true) {'.
									'		fromlist.options[i] = null;'.
									'	}'.
									'}'.
									'k = 0;'.
									'while (k!=tolist.length) { list += \''.$this->list_sep.'\'+tolist.options[k].value; k++; }'.
									'document.getElementsByName(\''.$myName.'\')[0].value=list; '.
									'}');
				$buttons['remove_all'] = '<input id="'.$myName.'__remove_all" align=center type=button value="<<" onclick="'.
									'tolist = document.getElementsByName(\''.$myName.'to[]\')[0];'.
									'fromlist = document.getElementsByName(\''.$myName.'from[]\')[0];'.
									'list = \'\';'.
									'k = 0;'.
									'i = 0;'.
									'while (k!=tolist.options.length) {'.
									'	while (i!=fromlist.options.length && fromlist.options[i].value<tolist.options[k].value) i++;'.
									'	jj = fromlist.length;'.
									'	fromlist.options[jj] = new Option();'.
									'	for( j = jj; j > i; j-- ) {'.
									'		fromlist.options[j].text = fromlist.options[j-1].text;'.
									'		fromlist.options[j].value = fromlist.options[j-1].value;'.
									'	}'.
									'	fromlist.options[i].value = tolist.options[k].value;'.
									'	fromlist.options[i].text = tolist.options[k].text;'.
									'	k++;'.
									'}'.
									'for(i = (tolist.length-1); i >= 0; i--) {'.
									'	tolist.options[i] = null;'.
									'}'.
									'document.getElementsByName(\''.$myName.'\')[0].value=list;'.
									'"/>';
				$buttons['remove_selected'] = '<input onFocus="focus_by_id(\''.$myName.'__from\');" id="'.$myName.'__remove_selected" type=button value="<" onclick="'.
									'remove_selected_'.$mod.'();'.
									'"/>';
				$buttons['add_selected'] = '<input onFocus="focus_by_id(\''.$myName.'__to\');" id="'.$myName.'__add_selected" type=button value=">" onclick="'.
									'add_selected_'.$mod.'();'.
									'"/>';
				$buttons['add_all'] = '<input id="'.$myName.'__add_all" type=button value=">>" onclick="'.
									'tolist = document.getElementsByName(\''.$myName.'to[]\')[0];'.
									'fromlist = document.getElementsByName(\''.$myName.'from[]\')[0];'.
									'list = \'\';'.
									'k = 0;'.
									'i = 0;'.
									'while (k!=fromlist.length) {'.
									'	while(i < tolist.length && tolist.options[i].value<fromlist.options[k].value) i++;'.
									'	jj = tolist.length;'.
									'	tolist.options[jj] = new Option();'.
									'	for( j = jj; j > i; j-- ) {'.
									'		tolist.options[j].value = tolist.options[j-1].value;'.
									'		tolist.options[j].text = tolist.options[j-1].text;'.
									'	}'.
									'	tolist.options[i].value = fromlist.options[k].value;'.
									'	tolist.options[i].text = fromlist.options[k].text;'.
									' 	k++;'.
									'}'.
									'for(i = (fromlist.length-1); i >= 0; i--) {'.
									'	fromlist.options[i] = null;'.
									'}'.
									'k = 0;'.
									'while (k!=tolist.length) { list += \''.$this->list_sep.'\'+tolist.options[k].value; k++; }'.
									'document.getElementsByName(\''.$myName.'\')[0].value=list; '.
									'"/>';
			}
			$strHtml .= $tabs . '<table id="multiselect">';
            $strHtml .= $tabs . '<tr><td class="form-element">' . $fromElement . '</td>';

			$strHtml .= $tabs . '<td class="buttons"><table>' .
						$tabs . '<tr><td class="button">'.$buttons['add_selected'].'</td></tr>' .
						$tabs . '<tr><td class="button">'.$buttons['add_all'].'</td></tr>' .
						$tabs . '<tr><td class="button">'.$buttons['remove_all'].'</td></tr>' .
						$tabs . '<tr><td class="button">'.$buttons['remove_selected'].'</td></tr>' .
						$tabs . '</table></td>';

			$strHtml .= $tabs . '<td class="to-element">' . $toElement . '</td></tr></table>';

			$this->setName($myName);
			$attrString = $this->_getAttrString($this->_attributes);
			$strHtml .= $tabs . '<input type="hidden" name="' . $myName . "\" value=\"".$list."\" />\n";
//			print_r($this->_options);
			return $strHtml;
        }
    }

    /**
     * Returns the value of field without HTML tags
     *
     * @access    public
     * @return    string
     */
    function getFrozenHtml()
    {
    	$html = '';
    	foreach($this->_options as $k=>$v)
	        if (in_array($v['attr']['value'],$this->_values)) $html .= empty($v['text'])? '&nbsp;':'<span>'.$v['text'].'</span><br />';
        return $html;
    }

   /**
    * We check the options and return only the values that _could_ have been
    * selected. We also return a scalar value if select is not "multiple"
    */
    function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        }
        $cleanValue = explode('__SEP__',$value);
        array_shift($cleanValue);
//        foreach($cleanValue as $k=>$v) {
//        	$cleanValue[$k] = $this->_options[$v]['attr']['value'];
//        }
		return $this->_prepareValue($cleanValue, $assoc);
    }

    function onQuickFormEvent($event, $arg, &$caller)
    {
        if ('updateValue' == $event) {
            $value = $this->_findValue($caller->_constantValues);
            if (null === $value) {
                $value = $this->_findValue($caller->_submitValues);
                // Fix for bug #4465 & #5269
                // XXX: should we push this to element::onQuickFormEvent()?
                if (null === $value && (!$caller->isSubmitted())) {
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
?>
