<?php
/**
 *
 * This file is part of phpFastCache.
 *
 * @license MIT License (MIT)
 *
 * For full copyright and license information, please see the docs/CREDITS.txt file.
 *
 * @author Khoa Bui (khoaofgod)  <khoaofgod@gmail.com> http://www.phpfastcache.com
 * @author Georges.L (Geolim4)  <contact@geolim4.com>
 *
 */

define('PSSDB_PHP_EXT', 'php');

/**
 * Register Autoload
 */
spl_autoload_register(function ($entity) {
    $module = explode('\\', $entity, 2);
    if ($module[ 0 ] !== 'phpssdb') {
        /**
         * Not a part of phpssdb file
         * then we return here.
         */
        return;
    }

    $entity = str_replace('\\', '/', $entity);
    $path = __DIR__ . '/' . $entity . '.' . PSSDB_PHP_EXT;

    if (is_readable($path)) {
        require_once $path;
    }
});

if (class_exists('Composer\Autoload\ClassLoader')) {
    trigger_error('Your project already makes use of Composer. You SHOULD use the composer dependency "phpfastcache/phpssdb" instead of hard-autoloading.',
      E_USER_WARNING);
}