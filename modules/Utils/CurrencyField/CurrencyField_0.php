<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
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
            array('name'=>__('ID')),
			array('name'=>__('Code')),
			array('name'=>__('Symbol')),
			array('name'=>__('Symbol position')),
			array('name'=>__('Decimal sign')),
			array('name'=>__('Thousand sign')),
			array('name'=>__('Decimals')),
			array('name'=>__('Default')),
			array('name'=>__('Active'))
		));
		$ret = DB::Execute('SELECT * FROM utils_currency ORDER BY id ASC');
		while($row = $ret->FetchRow()) {
			$gb_row = $gb->get_new_row();
			$gb_row->add_data_array(array(
                    $row['id'],
					$row['code'],
					$row['symbol'],
					self::$positions[$row['pos_before']],
					$row['decimal_sign'],
					$row['thousand_sign'],
					$row['decimals'],
					self::$active[$row['default_currency']],
					self::$active[$row['active']]
				));
			$gb_row->add_action($this->create_callback_href($this->edit_currency(...),array($row['id'])),'edit');
		}
		Base_ActionBarCommon::add('add', __('New'), $this->create_callback_href($this->edit_currency(...), array(null)));
		if (CURRENCY_RATE_AUTO_FETCH) {
			Base_ActionBarCommon::add('retry', __('Update currencies exchange rates'), $this->create_confirm_callback_href(__('This will fetch missing daily exchange rates from an external source and may take a moment. Continue?'), array('Utils_CurrencyFieldCommon','fetch_daily_rates')));
			Base_ActionBarCommon::add('view', __('Show exchange rates'), $this->create_callback_href($this->show_rates(...)));
			Base_ActionBarCommon::add('settings', __('Exchange rate settings'), $this->create_callback_href($this->rate_settings(...)));
		}
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		$this->display_module($gb);
	}

	public function rate_settings() {
		if ($this->is_back()) return false;
		$form = $this->init_module('Libs_QuickForm');
		$form->addElement('header', 'header', __('Exchange rate settings'));
		$form->addElement('text', 'backfill_start', __('Fetch rates starting from'));
		$form->addRule('backfill_start', __('Field required'), 'required');
		$form->addRule('backfill_start', __('Invalid date, use YYYY-MM-DD'), 'regex', '/^\d{4}-\d{2}-\d{2}$/');
		if ($form->validate()) {
			$vals = $form->exportValues();
			Utils_CurrencyFieldCommon::set_rate_backfill_start($vals['backfill_start']);
			return false;
		}
		$form->setDefaults(array('backfill_start' => Utils_CurrencyFieldCommon::get_rate_backfill_start()));
		$form->display_as_column();
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		return true;
	}

	public function show_rates() {
		if ($this->is_back()) return false;

		$currencies = Utils_CurrencyFieldCommon::get_all_currencies();

		$from = $this->get_module_variable('filter_from','');
		$to = $this->get_module_variable('filter_to','');
		$form = $this->init_module('Libs_QuickForm',null,'filter');
		$form->setDefaults(array('from'=>$from,'to'=>$to));
		$el_from = $form->addElement('select','from',__('From currency'),array(),array('onChange'=>$form->get_submit_form_js()));
		$el_from->addOption(__('All'),'');
		foreach ($currencies as $cid=>$code) $el_from->addOption($code,$cid);
		$el_to = $form->addElement('select','to',__('To currency'),array(),array('onChange'=>$form->get_submit_form_js()));
		$el_to->addOption(__('All'),'');
		foreach ($currencies as $cid=>$code) $el_to->addOption($code,$cid);
		$form->display_as_row();
		$from = $form->exportValue('from');
		$to = $form->exportValue('to');
		$this->set_module_variable('filter_from',$from);
		$this->set_module_variable('filter_to',$to);

		$gb = $this->init_module('Utils_GenericBrowser',null,'exchange_rates');
		$gb->set_table_columns(array(
			array('name'=>__('From'),'order'=>'cf.code','width'=>15),
			array('name'=>__('To'),'order'=>'ct.code','width'=>15),
			array('name'=>__('Date'),'order'=>'r.rate_date','width'=>15),
			array('name'=>__('Rate'),'order'=>'r.rate','width'=>15),
			array('name'=>__('Source'),'order'=>'r.source','width'=>15),
			array('name'=>__('Fetched'),'order'=>'r.fetched','width'=>25)
		));
		$gb->set_default_order(array(__('Date')=>'DESC'));

		$where = array();
		if ($from) $where[] = 'r.currency_id='.(int)$from;
		if ($to) $where[] = 'r.target_currency_id='.(int)$to;
		$where_sql = $where ? ' WHERE '.implode(' AND ',$where) : '';

		$query = 'SELECT cf.code AS from_code, ct.code AS to_code, r.rate_date, r.rate, r.source, r.fetched'.
					' FROM utils_currency_rate r'.
					' JOIN utils_currency cf ON cf.id=r.currency_id'.
					' JOIN utils_currency ct ON ct.id=r.target_currency_id'.$where_sql;
		$query_qty = 'SELECT COUNT(*) FROM utils_currency_rate r'.$where_sql;

		$ret = $gb->query_order_limit($query, $query_qty);
		if ($ret) while ($row = $ret->FetchRow()) {
			$gb->add_row($row['from_code'], $row['to_code'], $row['rate_date'], rtrim(rtrim(number_format((float)$row['rate'], 6, '.', ''), '0'), '.'), $row['source'], $row['fetched'] ? date('Y-m-d H:i', $row['fetched']) : '');
		}

		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		$this->display_module($gb);
		return true;
	}

	public function edit_currency($id) {
		if ($this->is_back()) return false;
		$form = $this->init_module('Libs_QuickForm');
		$form->addElement('header', 'header', __('Edit currency'));
		$form->addElement('text', 'code', __('Code'));
		$form->addElement('text', 'symbol', __('Symbol'));
		$form->addElement('select', 'pos_before', __('Symbol position'), self::$positions);
		$form->addElement('text', 'decimal_sign', __('Decimal sign'));
		$form->addElement('text', 'thousand_sign', __('Thousand sign'));
		$form->addElement('text', 'decimals', __('Decimals'));
		$form->addElement('select', 'default_currency', __('Default'), self::$active);
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
			if($defs['default_currency']) $form->freeze(array('default_currency'));
		}
		if ($form->validate()) {
			$vals = $form->exportValues();
			if(isset($vals['default_currency']) && $vals['default_currency']) DB::Execute('UPDATE utils_currency SET default_currency=0');
			$vals = array(	htmlspecialchars($vals['code']),
							htmlspecialchars($vals['symbol']),
							htmlspecialchars($vals['pos_before']),
							htmlspecialchars($vals['decimal_sign']),
							htmlspecialchars($vals['thousand_sign']),
							htmlspecialchars($vals['decimals']),
							htmlspecialchars($vals['active']),
							isset($vals['default_currency'])?htmlspecialchars($vals['default_currency']):1);
			if ($id!==null) {
				$vals[] = $id;
				$sql = 'UPDATE utils_currency SET '.
							'code=%s, '.
							'symbol=%s, '.
							'pos_before=%d, '.
							'decimal_sign=%s, '.
							'thousand_sign=%s, '.
							'decimals=%d, '.
							'active=%d,'.
							'default_currency=%d'.
							' WHERE id=%d';
			} else {
				$sql = 'INSERT INTO utils_currency ('.
							'code, '.
							'symbol, '.
							'pos_before, '.
							'decimal_sign, '.
							'thousand_sign, '.
							'decimals, '.
							'active, '.
							'default_currency'.
						') VALUES ('.
							'%s, '.
							'%s, '.
							'%d, '.
							'%s, '.
							'%s, '.
							'%d, '.
							'%d, '.
							'%d'.
						')';
			}
			DB::Execute($sql, $vals);
			return false;
		}
		$form->display_as_column();
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
		return true;
	}
}

?>
