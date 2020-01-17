<?php
/**
 * @author      Janusz Tylek <j@epe.si>
 * @copyright  Copyright &copy; 2014, Janusz Tylek
 * @license    MIT
 * @version    1.0
 * @package    epesi-utils
 * @subpackage CurrencyField
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CurrencyField_Sum
{
    private $currencies_values = array();

    public function add(Utils_CurrencyField_Value $value)
    {
        $sum = $this->get_currency_sum($value->get_currency_id());
        $sum->add($value);
    }

    public function get_currency_sum($currency_id)
    {
        if (!array_key_exists($currency_id, $this->currencies_values)) {
            $this->currencies_values[$currency_id] = new Utils_CurrencyField_Value(0, $currency_id);
        }
        return $this->currencies_values[$currency_id];
    }

    public function remove_zero_values()
    {
        foreach ($this->currencies_values as $currency_id => $value) {
            if ($value->is_zero()) {
                unset($this->currencies_values[$currency_id]);
            }
        }
    }

    public function get_total()
    {
        $this->remove_zero_values();
        return $this->currencies_values;
    }

    public function format($separator = '<br>')
    {
        $ret = '';
        foreach ($this->get_total() as $currency => $value) {
            if ($ret) $ret .= $separator;
            $ret .= $value->format();
        }
        return $ret;
    }
}
