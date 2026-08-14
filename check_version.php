<?php
/**
 * @author Janusz Tylek
 * @license MIT
 * @package epesi-base
 *
 * Reports the newest mtime among this install's own JS/CSS assets, polled
 * periodically by Epesi.updateCheck (include/epesi.js) so a long-open
 * browser tab can notice a fix shipped since it loaded and prompt for a
 * reload. Deliberately no session/DB bootstrap: the response is just a
 * timestamp, and this is polled far too often to pay for a full
 * include.php. See epesi_asset_version() in include/misc.php for the
 * (cached) scan itself and why this exists.
 */
define('_VALID_ACCESS', 1);
require_once('include/data_dir.php');
require_once('include/config.php');
require_once('include/misc.php');

header('Content-Type: text/plain');
header('Cache-Control: no-store');

echo epesi_asset_version();
