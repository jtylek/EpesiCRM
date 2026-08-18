<?php
/**
 * Chart.js - https://www.chartjs.org
 * Copyright (c) 2014-2024 Chart.js Contributors
 * Released under the MIT License.
 *
 * @license MIT
 * @package epesi-libs
 * @subpackage ChartJS
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Nothing needed at this Common-file's top level: ChartJS_0.php's own
// construct() loads chart.umd.min.js/cj.js itself, the same reliable
// constructor-based pattern modules/Libs/Quill/quill.php uses (see that
// file's own comment - a Common-file top-level load_js()/load_css() call was
// observed unreliable for a newly-installed module in this dev environment,
// constructor calls were not).
class Libs_ChartJSCommon extends ModuleCommon {
}
?>
