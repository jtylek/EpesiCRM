<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Janusz Tylek
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage QuickForm
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// §50: HTML_QuickForm is provided by openpsa/quickform via composer autoload.
// The old vendored QuickForm (3.2.14-php7/) was removed — it was disabled here and
// referenced nowhere else (it still contained PHP-8-removed create_function/magic_quotes).
