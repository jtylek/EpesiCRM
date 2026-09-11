<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage CurrencyField
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyFieldCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-cash-coin'; }

	public static function format($val, $currency=null) {
		if (!isset($currency) || !$currency) {
			$val = self::get_values($val);
			$currency = $val[1];
			if(!$currency) return '';
			$val = $val[0];
		}
		$params = self::$cache[$currency];
		$dec_delimiter = $params['decimal_sign'];
		if(!$dec_delimiter) trigger_error(print_r(self::$cache,true));
		$thou_delimiter = $params['thousand_sign'];
		$dec_digits = $params['decimals'];
		$currency = $params['symbol'];
		$pos_before = $params['pos_before'];
		
		if (!$val) $val = '0';
		$val = str_replace(array('.',','),$dec_delimiter,$val);
		if (!strrchr($val,(string) $dec_delimiter)) $val .= $dec_delimiter; 
		$cur = explode($dec_delimiter, $val);
		if (!isset($cur[1])) $cur[1] = ''; 
		$cur[1] = str_pad($cur[1], $dec_digits, '0');
		$val = $cur[0].'.'.$cur[1];
		$ret = number_format($val, $dec_digits, $dec_delimiter, $thou_delimiter);
		if ($pos_before) $ret = $currency.'&nbsp;'.$ret;
		else $ret = $ret.'&nbsp;'.$currency;
		return $ret;
	}

    public static function is_empty($p) {
        return str_starts_with($p, '__');
    }
	
	public static function get_values($p) {
		if (!is_array($p)) $p = explode('__', $p);
                if(!is_numeric($p[0]) && $p[0]!='') return false;
		if (!isset($p[1])) $p[1] = Base_User_SettingsCommon::get('Utils_CurrencyField', 'default_currency');
        $p[0] = str_replace(array(',', self::get_decimal_point($p[1])), '.', $p[0]);
		return $p;
	}
	
	public static function format_default($v, $c=null) {
        $values = ($c === null ? self::get_values($v) : self::get_values(array($v, $c)));
        $c = $values[1];
        $v = round($values[0], self::get_precission($c));
		return $v.'__'.$c;
	}

	public static function user_settings() {
		$currency_options = DB::GetAssoc('SELECT id, code FROM utils_currency WHERE active=1');
		$def = self::get_default_currency();
		return array(__('Regional Settings')=>array(
				array('name'=>'currency_header', 'label'=>__('Currency'), 'type'=>'header'),
				array('name'=>'default_currency','label'=>__('Default currency'),'type'=>'select','values'=>$currency_options,'default'=>$def['id']),
					));
	}
	
	public static function get_decimal_point($arg = null) {
        self::load_cache();
		if($arg===null) $arg = Base_User_SettingsCommon::get('Utils_CurrencyField','default_currency');
		if (!isset(self::$cache[$arg])) return null;
		return self::$cache[$arg]['decimal_sign'];
	}
	
	public static function get_thousand_point($arg) {
        self::load_cache();
		if (!isset(self::$cache[$arg])) return null;
		return self::$cache[$arg]['thousand_sign'];
	}
	
	public static function get_id_by_code($code) {
		static $cache;
		if(!isset($cache)) $cache = array();
		if(!isset($cache[$code]))
			$cache[$code] = DB::GetOne('SELECT id FROM utils_currency WHERE code=%s', array($code));
		return $cache[$code];
	}
	
	public static function get_code($arg) {
        self::load_cache();
		if (!isset(self::$cache[$arg])) return null;
		return self::$cache[$arg]['code'];
	}
	
	public static function get_precission($arg) {
        self::load_cache();
		if (!isset(self::$cache[$arg])) return null;
		return self::$cache[$arg]['decimals'];
	}
	
	public static function get_currencies() {
		static $cache=null;
		if ($cache===null) $cache = DB::GetAssoc('SELECT id, code FROM utils_currency WHERE active=1');
		return $cache;
	}

	public static function get_all_currencies() {
		static $cache=null;
		if ($cache===null) $cache = DB::GetAssoc('SELECT id, code FROM utils_currency');
		return $cache;
	}
	
	public static function get_default_currency() {
		static $cache=null;
		if ($cache===null) $cache = DB::GetRow('SELECT * FROM utils_currency WHERE default_currency=1');
		return $cache;
	}

	/**
	 * Every currency-typed field that exists anywhere, core or Premium alike -
	 * RecordBrowser field metadata (recordbrowser_table_properties + each tab's own
	 * <tab>_field table) is itself DB-driven, so this needs no source-code awareness of
	 * which modules exist. Shared by find_currency_usage() and count_currency_usage_by_tab().
	 *
	 * Also resolves, once per tab, that tab's own "Exchange Rate"/"Exchanged Amount"
	 * companion fields if it has them (by caption - Premium_Accounts/Expenses/Timesheet/
	 * Vehicles already store these next to their currency field, filled in by their own
	 * recalculate_missing_amounts() hooks; this report just displays whatever they stored,
	 * not computing a rate itself). A tab without such fields gets null columns.
	 *
	 * @return array of array('tab', 'column', 'field_caption', 'rate_column', 'exchanged_column')
	 */
	private static function currency_field_columns() {
		$out = array();
		if (ModuleManager::is_installed('Utils_RecordBrowser') < 0) return $out;
		$tabs = DB::GetCol('SELECT tab FROM recordbrowser_table_properties') ?: array();
		foreach ($tabs as $tab) {
			Utils_RecordBrowserCommon::check_table_name($tab);
			$fields = DB::GetAssoc('SELECT field, caption FROM '.$tab.'_field WHERE type=%s', array('currency')) ?: array();
			if (!$fields) continue;
			$rate_field = DB::GetOne('SELECT field FROM '.$tab.'_field WHERE caption=%s', array(__('Exchange Rate')));
			$exchanged_field = DB::GetOne('SELECT field FROM '.$tab.'_field WHERE caption=%s', array(__('Exchanged Amount')));
			$rate_column = $rate_field ? 'f_'.Utils_RecordBrowserCommon::get_field_id($rate_field) : null;
			$exchanged_column = $exchanged_field ? 'f_'.Utils_RecordBrowserCommon::get_field_id($exchanged_field) : null;
			foreach ($fields as $field => $caption) {
				$out[] = array(
					'tab'=>$tab,
					'column'=>'f_'.Utils_RecordBrowserCommon::get_field_id($field),
					'field_caption'=>$caption ?: $field,
					'rate_column'=>$rate_column,
					'exchanged_column'=>$exchanged_column,
				);
			}
		}
		return $out;
	}

	/**
	 * Finds recordset rows referencing $currency_id in one of their currency-typed
	 * fields. A currency-field value is stored as '<amount>__<currency_id>' (see
	 * format_default()), so a stored value "uses" this currency iff its column ends in
	 * exactly '__<currency_id>' - checked with RIGHT() rather than LIKE so the literal
	 * underscores in the separator never need wildcard-escaping. Each match also carries
	 * the record's creation date and its tab's Exchange Rate/Exchanged Amount values (raw,
	 * as stored - null if that tab has no such fields).
	 *
	 * @param $limit stop once this many matches are collected (null: no cap - used by
	 *   is_currency_used() to stop at the first match; the admin usage report wants every
	 *   match, since GenericBrowser's own paging handles display size).
	 * @param $tab_filter restrict the scan to one recordset (null: every recordset with a
	 *   currency field).
	 * @return array('rows'=>array(array('tab','field_caption','record_id','raw_value','created_on','rate','exchanged')), 'truncated'=>bool)
	 */
	public static function find_currency_usage($currency_id, $limit = null, $tab_filter = null) {
		$rows = array();
		$truncated = false;
		$suffix = '__'.$currency_id;
		foreach (self::currency_field_columns() as $col) {
			if ($tab_filter !== null && $col['tab'] !== $tab_filter) continue;
			if ($limit !== null && count($rows) >= $limit) { $truncated = true; break; }
			$rate_select = $col['rate_column'] ?: 'NULL';
			$exchanged_select = $col['exchanged_column'] ?: 'NULL';
			$sql = 'SELECT id, '.$col['column'].' AS raw_value, created_on, '.$rate_select.' AS rate_val, '.$exchanged_select.' AS exchanged_val'.
				' FROM '.$col['tab'].'_data_1 WHERE RIGHT('.$col['column'].', %d)=%s';
			if ($limit !== null) $sql .= ' LIMIT '.((int)($limit - count($rows)) + 1);
			$ret = DB::Execute($sql, array(strlen($suffix), $suffix));
			$matches = array();
			if ($ret) while ($r = $ret->FetchRow()) $matches[] = $r;
			foreach ($matches as $r) {
				if ($limit !== null && count($rows) >= $limit) { $truncated = true; break; }
				$rows[] = array(
					'tab'=>$col['tab'],
					'field_caption'=>$col['field_caption'],
					'record_id'=>$r['id'],
					'raw_value'=>$r['raw_value'],
					'created_on'=>$r['created_on'],
					'rate'=>$r['rate_val'],
					'exchanged'=>$r['exchanged_val'],
				);
			}
		}
		return array('rows'=>$rows, 'truncated'=>$truncated);
	}

	public static function is_currency_used($currency_id) {
		return (bool) self::find_currency_usage($currency_id, 1)['rows'];
	}

	/**
	 * Per-recordset match counts for $currency_id - backs the "Show usage" summary screen
	 * (one row per recordset, before drilling into any single one's actual records). A
	 * COUNT(DISTINCT id) per tab is much cheaper than fetching every matching id just to
	 * count them in PHP, and correctly counts a record once even if it has two currency
	 * fields both referencing this currency.
	 *
	 * @return array tab => array('caption', 'count'), sorted by caption
	 */
	public static function count_currency_usage_by_tab($currency_id) {
		$suffix = '__'.$currency_id;
		$by_tab = array();
		foreach (self::currency_field_columns() as $col) $by_tab[$col['tab']][] = $col['column'];

		$result = array();
		foreach ($by_tab as $tab => $columns) {
			$conds = array();
			$params = array();
			foreach ($columns as $column) {
				$conds[] = 'RIGHT('.$column.', %d)=%s';
				array_push($params, strlen($suffix), $suffix);
			}
			$count = (int) DB::GetOne('SELECT COUNT(DISTINCT id) FROM '.$tab.'_data_1 WHERE '.implode(' OR ', $conds), $params);
			if ($count) $result[$tab] = array('caption'=>Utils_RecordBrowserCommon::get_caption($tab), 'count'=>$count);
		}
		uasort($result, fn($a, $b) => strcasecmp($a['caption'], $b['caption']));
		return $result;
	}

	public static function get_rate_backfill_start() {
		$v = Variable::get('utils_currency_rate_backfill_start', false);
		return $v ?: date('Y-m-d', strtotime('-1 year'));
	}

	public static function set_rate_backfill_start($date) {
		Variable::set('utils_currency_rate_backfill_start', $date);
	}

	/**
	 * Looks up the cached daily rate for converting 1 unit of $currency_id into
	 * $target_currency_id, as of $date - falling back to the most recent earlier
	 * cached date (weekends/bank holidays have no published rate).
	 *
	 * @return float|null null if nothing is cached yet for this pair.
	 */
	public static function get_cached_rate($currency_id, $target_currency_id, $date) {
		if ($currency_id == $target_currency_id) return 1.0;
		$rate = DB::GetOne('SELECT rate FROM utils_currency_rate WHERE currency_id=%d AND target_currency_id=%d AND rate_date<=%D ORDER BY rate_date DESC LIMIT 1', array($currency_id, $target_currency_id, $date));
		return $rate!==false && $rate!==null ? (float)$rate : null;
	}

	private static function fetch_json($url) {
		if (function_exists('curl_init')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			$body = curl_exec($ch);
			curl_close($ch);
		} else {
			$body = @file_get_contents($url, false, stream_context_create(array('http'=>array('timeout'=>30))));
		}
		if (!$body) return null;
		$data = @json_decode($body, true);
		return is_array($data) ? $data : null;
	}

	/**
	 * Fetches missing daily exchange rates (from every active currency to every
	 * other active currency) from Frankfurter (api.frankfurter.dev, free ECB-backed
	 * rates, no API key) and caches them in utils_currency_rate. Backfills from
	 * get_rate_backfill_start() (or the last cached date, if later) through today.
	 * Called from the "Update currencies exchange rates" admin action.
	 */
	public static function fetch_daily_rates() {
		if (!CURRENCY_RATE_AUTO_FETCH) {
			Epesi::alert(__('Currency rate auto-fetch is disabled in configuration.'));
			return false;
		}
		$currencies = self::get_currencies();
		if (count($currencies) < 2) {
			Epesi::alert(__('Add at least two active currencies before fetching exchange rates.'));
			return false;
		}
		$today = date('Y-m-d');
		$backfill_start = self::get_rate_backfill_start();
		$fetched = 0;
		$skipped = array();
		foreach ($currencies as $from_id => $from_code) {
			$to_ids = array();
			foreach ($currencies as $id => $code) {
				if ($id != $from_id) $to_ids[$code] = $id;
			}
			if (!$to_ids) continue;

			$last = DB::GetOne('SELECT MAX(rate_date) FROM utils_currency_rate WHERE currency_id=%d', array($from_id));
			$start = $last ? date('Y-m-d', strtotime($last) + 86400) : $backfill_start;
			if ($start > $today) continue;

			$url = 'https://api.frankfurter.dev/v1/'.$start.'..'.$today.'?base='.urlencode($from_code).'&symbols='.urlencode(implode(',', array_keys($to_ids)));
			$data = self::fetch_json($url);
			if (!isset($data['rates']) || !is_array($data['rates'])) {
				$skipped[] = $from_code;
				continue;
			}
			$now = time();
			foreach ($data['rates'] as $rate_date => $rates_by_code) {
				foreach ($rates_by_code as $code => $rate) {
					if (!isset($to_ids[$code])) continue;
					$to_id = $to_ids[$code];
					$exists = DB::GetOne('SELECT id FROM utils_currency_rate WHERE currency_id=%d AND target_currency_id=%d AND rate_date=%D', array($from_id, $to_id, $rate_date));
					if ($exists) {
						DB::Execute('UPDATE utils_currency_rate SET rate=%f, source=%s, fetched=%d WHERE id=%d', array($rate, 'frankfurter', $now, $exists));
					} else {
						DB::Execute('INSERT INTO utils_currency_rate (currency_id, target_currency_id, rate_date, rate, source, fetched) VALUES (%d, %d, %D, %f, %s, %d)', array($from_id, $to_id, $rate_date, $rate, 'frankfurter', $now));
					}
					$fetched++;
				}
			}
		}
		$msg = __('Fetched').' '.$fetched.' '.__('exchange rate(s).');
		if ($skipped) $msg .= ' '.__('Skipped (unsupported currency or unreachable service):').' '.implode(', ', array_unique($skipped));
		$recalculated = self::recalculate_missing_amounts();
		if ($recalculated) $msg .= ' '.__('Recalculated').' '.$recalculated.' '.__('previously-unresolved record(s).');
		Epesi::alert($msg);
		return false;
	}

	/**
	 * Re-triggers amount recalculation, via each optional Premium module's own
	 * hook, for existing records that were saved with a missing exchange rate
	 * before this cache had data for them - so a fetch retroactively fixes
	 * already-broken records, not just future ones. No-ops per module when
	 * that module isn't installed.
	 */
	private static function recalculate_missing_amounts() {
		$count = 0;
		if (ModuleManager::is_installed('Premium_Accounts') >= 0) $count += Premium_AccountsCommon::recalculate_missing_amounts();
		if (ModuleManager::is_installed('Premium_Expenses') >= 0) $count += Premium_ExpensesCommon::recalculate_missing_amounts();
		if (ModuleManager::is_installed('Premium_Timesheet') >= 0) $count += Premium_TimesheetCommon::recalculate_missing_amounts();
		if (ModuleManager::is_installed('Premium_Vehicles') >= 0) $count += Premium_VehiclesCommon::recalculate_missing_amounts();
		return $count;
	}

	public static function admin_caption() {
		return array('label'=>__('Currencies'), 'section'=>__('Regional Settings'));
	}
	
	public static function get_symbol($arg) {
        self::load_cache();
		if (!isset(self::$cache[$arg])) return null;
		return self::$cache[$arg]['symbol'];
	}
	public static function get_symbol_position($arg) {
        self::load_cache();
		if (!isset(self::$cache[$arg])) return null;
		return self::$cache[$arg]['pos_before'];
	}

    /**
     * Parse currency using existing currencies set in the system.
     *
     * @param $string Currency string to parse
     *
     * @return array|null null on failure and array(value, currency_id) on success - like get_values returns.
     */
    public static function parse_currency($string) {
        $string = html_entity_decode($string);
        $string = preg_replace('/[\pZ\pC\s]/u', '', $string); // remove whitespaces, including unicode nbsp
        $currencies = Utils_CurrencyFieldCommon::get_currencies();
        foreach (array_keys($currencies) as $cur_id) {
            $symbol = Utils_CurrencyFieldCommon::get_symbol($cur_id);
            $symbol_pos_before = Utils_CurrencyFieldCommon::get_symbol_position($cur_id);
            // check for symbol
            if ($symbol_pos_before) {
                if (str_starts_with($string, (string) $symbol)) {
                    $string = substr($string, strlen($symbol));
                } else continue;
            } else {
                $pos_of_sym = strlen($string) - strlen($symbol);
                if (strrpos($string, (string) $symbol) == $pos_of_sym) {
                    $string = substr($string, 0, $pos_of_sym);
                } else continue;
            }
            // separate by decimal point
            $exp = explode(Utils_CurrencyFieldCommon::get_decimal_point($cur_id), $string);
            if (count($exp) > 2)
                continue;
            $fraction = count($exp) == 2 ? $exp[1] : '0';
            $int = $exp[0];
            if (!preg_match('/^\d+$/', $fraction))
                continue;
            $th_point = Utils_CurrencyFieldCommon::get_thousand_point($cur_id);
            if (strlen($th_point)) {
                $thparts = explode($th_point, $int);
                if (count($thparts) > 1) {
                    for ($i = 1; $i < count($thparts); $i++)
                        if (strlen($thparts[$i]) != 3)
                            continue 2;
                }
                $int = str_replace($th_point, '', $int);
            }
            if (preg_match('/^\-?\d+$/', $int)) {
                return array($int . '.' . $fraction, $cur_id);
            }
        }
        return null;
    }

    private static $cache;
    public static function load_cache() {
        if(!isset(self::$cache))
            self::$cache = DB::GetAssoc('SELECT id,pos_before,symbol,decimals,code,thousand_sign,decimal_sign FROM utils_currency');
    }
    public static function load_js() {
        self::load_cache();
        load_js('modules/Utils/CurrencyField/currency.js');
        $currencies = Utils_CurrencyFieldCommon::get_all_currencies();
        $js = 'Utils_CurrencyField.currencies=new Array();';
        foreach ($currencies as $k => $v) {
            $symbol = Utils_CurrencyFieldCommon::get_symbol($k);
            $position = Utils_CurrencyFieldCommon::get_symbol_position($k);
            $curr_format = '-?([0-9]*)\\'.Utils_CurrencyFieldCommon::get_decimal_point($k).'?[0-9]{0,'.Utils_CurrencyFieldCommon::get_precission($k).'}';
            $js .= 'Utils_CurrencyField.currencies[' . $k . ']={' . '"decp":"' . Utils_CurrencyFieldCommon::get_decimal_point($k) . '",' . '"thop":"' . Utils_CurrencyFieldCommon::get_thousand_point($k) . '",' . '"symbol_before":"' . ($position ? $symbol : '') . '",' . '"symbol_after":"' . (!$position ? $symbol : '') . '",' . '"dec_digits":' . Utils_CurrencyFieldCommon::get_precission($k) . ',' . 
                '"regex":'.json_encode($curr_format).'};';
        }
        eval_js_once($js);
    }
}

// openpsa's _loadElement() wants a plain classname string (class already loaded), not
// array($file,$class) — see include/epesi.php's register_custom_qf_types() for why.
require_once('modules/Utils/CurrencyField/currency.php');
$GLOBALS['HTML_QUICKFORM_ELEMENT_TYPES']['currency'] = 'HTML_QuickForm_currency';
on_init(array('Utils_CurrencyFieldCommon','load_js'));
?>
