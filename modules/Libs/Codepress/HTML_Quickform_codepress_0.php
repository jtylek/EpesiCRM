<?php
/**
 * Codepress editor
 * This module uses CodePress editor released under
 * GNU LESSER GENERAL PUBLIC LICENSE Version 2.1
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-libs
 * @subpackage codepress
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class HTML_Quickform_codepress extends HTML_QuickForm_element {
	private $config = array('lang'=>'php','linenumbers'=>true,'autocomplete'=>true);
	private $_value = null;

	public function HTML_Quickform_codepress($elementName=null, $elementLabel=null, $attributes=null) {
		HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
		$this->_persistantFreeze = true;
		$this->_type = 'codepress';
	}

	/**
	* Sets the input field name
	* 
	* @param     string    $name   Input field name attribute
	* @since     1.0
	* @access    public
	* @return    void
	*/
	public function setName($name) {
		$this->updateAttributes(array('name'=>$name));
	} //end func setName

	/**
	* Returns the element name
	* 
	* @since     1.0
	* @access    public
	* @return    string
	*/
	public function getName() {
		return $this->getAttribute('name');
	} //end func getName

	/**
	* Sets lang
	* 
	* @param     string    $value coding lang
	* @since     1.0
	* @access    public
	* @return    void
	*/
	public function setLang($value) {
		$this->config['lang'] = $value;
	} //end func setLang

	/**
	* Sets line numbers
	* 
	* @param     boolean    $value line numbers
	* @since     1.0
	* @access    public
	* @return    void
	*/
	public function setLineNumbers($value) {
		$this->config['linenumbers'] = $value;
	} //end func setLineNumbers

	/**
	* Sets autocomplete
	* 
	* @param     boolean    $value autocomplete
	* @since     1.0
	* @access    public
	* @return    void
	*/
	public function setAutocomplete($value) {
		$this->config['autocomplete'] = $value;
	} //end func setAutocomplete

	/**
	* Sets value for textarea element
	* 
	* @param     string    $value  Value for textarea element
	* @since     1.0
	* @access    public
	* @return    void
	*/
	public function setValue($value) {
		$this->_value = $value;
	} //end func setValue

	/**
	* Returns the value of the form element
	*
	* @since     1.0
	* @access    public
	* @return    string
	*/
	public function getValue() {
		return $this->_value;
	} // end func getValue

	/**
	* Sets height in rows for textarea element
	* 
	* @param     string    $rows  Height expressed in rows
	* @since     1.0
	* @access    public
	* @return    void
	*/
	public function setRows($rows) {
		$this->updateAttributes(array('rows' => $rows));
	} //end func setRows

	/**
	* Sets width in cols for textarea element
	* 
	* @param     string    $cols  Width expressed in cols
	* @since     1.0
	* @access    public
	* @return    void
	*/ 
	public function setCols($cols) {
		$this->updateAttributes(array('cols' => $cols));
	} //end func setCols

	/**
	* Returns the textarea element in HTML
	* 
	* @since     1.0
	* @access    public
	* @return    string
	*/
	function toHtml() {
		return $this->_getTabs() .
		'<textarea' . $this->_getAttrString($this->_attributes) . ' class="codepress '.$this->config['lang'].' '.($this->config['linenumbers']?'':'linenumbers-off').' '.($this->config['autocomplete']?'':'autocomplete-off').' '.($this->_flagFrozen?'readonly-on':'').'" id="'.$this->getName().'">' .
			   // because we wrap the form later we don't want the text indented
			   preg_replace("/(\r\n|\n|\r)/", '&#010;', htmlspecialchars($this->_value)) .
			   '</textarea>';
	} //end func toHtml
}
?>
