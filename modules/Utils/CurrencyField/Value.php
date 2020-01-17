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

class Utils_CurrencyField_WrongAmountFormatException extends Exception {}

class Utils_CurrencyField_CurrencyMismatchException extends Exception {}

class Utils_CurrencyField_CurrencyNotFoundException extends Exception {}

class Utils_CurrencyField_Value
{
    /**
     * @param string $amount numeric value that represents amount of cash
     * @param int $currency_id currency id that represents
     */
    public function __construct($amount, $currency_id)
    {
        $this->set_amount($amount);
        $this->set_currency_id($currency_id);
    }

    public static function by_currency_code($amount, $currency_code)
    {
        $currency_id = self::get_currency_by_code($currency_code);
        return new self($amount, $currency_id);
    }

    public static function from_string($string)
    {
        list($amount, $currency) = Utils_CurrencyFieldCommon::get_values($string);
        return new self($amount, $currency);
    }

    public function is_zero()
    {
        return $this->get_amount() == 0;
    }

    public function to_string()
    {
        $str = Utils_CurrencyFieldCommon::format_default($this->get_amount(), $this->get_currency_id());
        return $str;
    }

    public function format()
    {
        return Utils_CurrencyFieldCommon::format($this->get_amount(), $this->get_currency_id());
    }

    public function add(Utils_CurrencyField_Value $other, $report_error = true)
    {
        if ($this->get_currency_id() == $other->get_currency_id()) {
            $this->add_amount($other->get_amount());
        } elseif ($report_error) {
            $msg = "Can't add two values of different currencies.";
            throw new Utils_CurrencyField_CurrencyMismatchException($msg);
        }
    }

    public function add_amount($numeric_value)
    {
        $this->set_amount(self::add_amounts($this->get_amount(), $numeric_value));
    }

    public function subtract_amount($numeric_value)
    {
        $this->set_amount(self::subtract_amounts($this->get_amount(), $numeric_value));
    }

    public function subtract(Utils_CurrencyField_Value $other, $report_error = true)
    {
        if ($this->get_currency_id() == $other->get_currency_id()) {
            $this->subtract_amount($other->get_amount());
        } elseif ($report_error) {
            $msg = "Can't subtract two values of different currencies.";
            throw new Utils_CurrencyField_CurrencyMismatchException($msg);
        }
    }

    public function get_amount()
    {
        return $this->amount;
    }

    public function get_currency_id()
    {
        return $this->currency_id;
    }

    public function get_currency_code()
    {
        return Utils_CurrencyFieldCommon::get_code($this->currency_id);
    }

    public function set_amount($amount)
    {
        if ($amount && !is_numeric($amount)) {
            throw new Utils_CurrencyField_WrongAmountFormatException($amount);
        }
        $this->amount = $amount;
    }

    public function set_currency_id($currency_id)
    {
        if (!Utils_CurrencyFieldCommon::get_code($currency_id)) {
            $msg = "Currency with id: $currency_id doesn't exist in the system.";
            throw new Utils_CurrencyField_CurrencyNotFoundException($msg);
        }
        $this->currency_id = $currency_id;
    }

    public function set_currency_by_code($currency_code)
    {
        $this->currency_id = self::get_currency_by_code($currency_code);
    }

    protected static function get_currency_by_code($currency_code)
    {
        $currency_id = Utils_CurrencyFieldCommon::get_id_by_code($currency_code);
        if (!is_numeric($currency_id)) {
            $msg = "Currency with code: $currency_code doesn't exist in the system.";
            throw new Utils_CurrencyField_CurrencyNotFoundException($msg);
        }
        return $currency_id;
    }

    protected static function add_amounts($am1, $am2)
    {
        return $am1 + $am2;
    }

    protected static function subtract_amounts($am1, $am2)
    {
        return $am1 - $am2;
    }

    private $amount;
    private $currency_id;

}
