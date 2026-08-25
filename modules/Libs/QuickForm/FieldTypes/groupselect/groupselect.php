<?php

/**
 * HTML_QuickForm_select variant that renders <optgroup> blocks.
 *
 * Takes the same flat value=>text array as the stock select when 'values'
 * is flat, but if a top-level entry is itself an array (group label =>
 * array(value=>text)) that entry is rendered as an <optgroup>. Used by the
 * timezone picker (Base_RegionalSettingsCommon::get_grouped_timezones())
 * to group ~400 zone identifiers by continent instead of one long flat
 * list - see AI-shared for context.
 *
 * @category    HTML
 * @package     HTML_QuickForm
 */
class HTML_QuickForm_groupselect extends HTML_QuickForm_select {

	// Parallel to $this->_options: group label for the option at the same
	// index, or null for an ungrouped option.
	private $_optionGroups = array();

	public function addOption($text, $value, $attributes=null, $group=null) {
		parent::addOption($text, $value, $attributes);
		$this->_optionGroups[] = $group;
	}

	public function loadArray(array $arr, $values=null) {
		if (isset($values)) {
			$this->setSelected($values);
		}
		foreach ($arr as $key => $val) {
			if (is_array($val)) {
				foreach ($val as $optValue => $optText) {
					$this->addOption($optText, $optValue, null, $key);
				}
			} else {
				$this->addOption($val, $key);
			}
		}
		return true;
	}

	public function toHtml() {
		if ($this->_flagFrozen) {
			return $this->getFrozenHtml();
		}
		$tabs = $this->_getTabs();
		$strHtml = '';

		if ($this->getComment() != '') {
			$strHtml .= $tabs . '<!-- ' . $this->getComment() . " //-->\n";
		}

		if (!$this->getMultiple()) {
			$attrString = $this->_getAttrString($this->_attributes);
		} else {
			$myName = $this->getName();
			$this->setName($myName . '[]');
			$attrString = $this->_getAttrString($this->_attributes);
			$this->setName($myName);
		}
		$strHtml .= $tabs . '<select' . $attrString . ">\n";

		$strValues = is_array($this->_values) ? array_map('strval', $this->_values) : array();
		$openGroup = null;
		foreach ($this->_options as $i => $option) {
			$group = $this->_optionGroups[$i] ?? null;
			if ($group !== $openGroup) {
				if ($openGroup !== null) $strHtml .= $tabs . "</optgroup>\n";
				if ($group !== null) $strHtml .= $tabs . '<optgroup label="' . htmlspecialchars($group) . "\">\n";
				$openGroup = $group;
			}
			if (!empty($strValues) && in_array($option['attr']['value'], $strValues, true)) {
				$option['attr']['selected'] = 'selected';
			}
			$strHtml .= $tabs . "\t<option" . $this->_getAttrString($option['attr']) . '>' .
						$option['text'] . "</option>\n";
		}
		if ($openGroup !== null) $strHtml .= $tabs . "</optgroup>\n";

		return $strHtml . $tabs . '</select>';
	}
}

?>
