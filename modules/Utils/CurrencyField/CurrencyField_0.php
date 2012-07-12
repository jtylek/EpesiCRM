<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage CurrencyField
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyField extends Module {
	private static $positions;
	private static $active;
	
	public function construct() {
		self::$positions = array(0=>__('After'), 1=>__('Before'));
		self::$active = array(1=>__('Yes'), 0=>__('No'));
	}
	
	public function admin() {
		if($this->is_back()) {
			if($this->parent->get_type()=='Base_Admin')
				$this->parent->reset();
			else
				location(array());
			return;
		}

		$gb = $this->init_module('Utils_GenericBrowser',null,'currencies');
		$gb->set_table_columns(array(
			array('name'=>__('Code')),
			array('name'=>__('Symbol')),
			array('name'=>__('Symbol position')),
			array('name'=>__('Decimal sign')),
			array('name'=>__('Thousand sign')),
			array('name'=>__('Decimals')),
			array('name'=>__('Active'))
		));
		$ret = DB::Execute('SELECT * FROM utils_currency ORDER BY active DESC, code ASC');
		while($row = $ret->FetchRow()) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_data_array(array(
					$row['code'],
					$row['symbol'],
					self::$positions[$row['pos_before']],
					$row['decimal_sign'],
					$row['thousand_sign'],
					$row['decimals'],
					self::$active[$row['active']]
				));
			$gb_row->add_action($this->create_callback_href(array($this, 'edit_currency'),array($row['id'])),'edit');
		}
		Base_ActionBarCommon::add('add', __('New'), $this->create_callback_href(array($this, 'edit_currency'), array(null)));
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		$this->display_module($gb);
	}
	
	public function edit_currency($id) {
		if ($this->is_back()) return false;
		$form = $this->init_module('Libs_QuickForm');
		$form->addElement('header', 'header', __('Edit currency'));
		$form->addElement('text', 'code', __('Code'));
		$form->addElement('text', 'symbol', __('Symbol'));
		$form->addElement('select', 'position', __('Symbol position'), self::$positions);
		$form->addElement('text', 'decimal_sign', __('Decimal sign'));
		$form->addElement('text', 'thousand_sign', __('Thousand sign'));
		$form->addElement('text', 'decimals', __('Decimals'));
		$form->addElement('select', 'active', __('Active'), self::$active);

		$form->addRule('code', __('Code must be up to 16 characters long'), 'maxlength', 16);
		$form->addRule('symbol', __('Symbol must be up to 8 characters long'), 'maxlength', 8);
		$form->addRule('decimal_sign', __('Decimal sign must be up to 2 characters long'), 'maxlength', 2);
		$form->addRule('thousand_sign', __('Thousand sign must be up to 2 characters long'), 'maxlength', 2);
		$form->addRule('decimals', __('Field must hold numeric value'), 'numeric');

		$form->addRule('code', __('Field required'), 'required');
		$form->addRule('symbol', __('Field required'), 'required');
		$form->addRule('decimal_sign', __('Field required'), 'required');
		$form->addRule('decimals', __('Field required'), 'required');

		if ($id!==null) {
			$defs = DB::GetRow('SELECT * FROM utils_currency WHERE id=%d', array($id));
			$form->setDefaults($defs);
		}
		if ($form->validate()) {
			$vals = $form->exportValues();
			$vals = array(	$vals['code'],
							$vals['symbol'],
							$vals['position'],
							$vals['decimal_sign'],
							$vals['thousand_sign'],
							$vals['decimals'],
							$vals['active']);
			if ($id!==null) {
				$vals[] = $id;
				$sql = 'UPDATE utils_currency SET '.
							'code=%s, '.
							'symbol=%s, '.
							'pos_before=%d, '.
							'decimal_sign=%s, '.
							'thousand_sign=%s, '.
							'decimals=%d, '.
							'active=%d'.
							' WHERE id=%d';
			} else {
				$sql = 'INSERT INTO utils_currency ('.
							'code, '.
							'symbol, '.
							'pos_before, '.
							'decimal_sign, '.
							'thousand_sign, '.
							'decimals, '.
							'active'.
						') VALUES ('.
							'%s, '.
							'%s, '.
							'%d, '.
							'%s, '.
							'%s, '.
							'%d, '.
							'%d'.
						')';
			}
			DB::Execute($sql, $vals);
			return false;
		}
		$form->display();
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		return true;
	}
}

?>
