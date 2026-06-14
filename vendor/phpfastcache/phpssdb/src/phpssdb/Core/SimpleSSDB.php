<?php
/**
 *
 * This file is part of phpFastCache.
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt file.
 *
 * @author (Original project) ideawu http://www.ideawu.com/
 * @author (PhpFastCache Interfacing) Khoa Bui (khoaofgod)  <khoaofgod@gmail.com> http://www.phpfastcache.com
 * @author (PhpFastCache Interfacing) Georges.L (Geolim4)  <contact@geolim4.com>
 *
 */

namespace phpssdb\Core;


/**
 * All methods(except *exists) returns false on error,
 * so one should use Identical(if($ret === false)) to test the return value.
 */
class SimpleSSDB extends SSDB
{
    public function __construct($host, $port, $timeout_ms = 2000)
    {
        parent::__construct($host, $port, $timeout_ms);
        $this->easy();
    }
}