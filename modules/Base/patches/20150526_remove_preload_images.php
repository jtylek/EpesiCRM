<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Variable::delete('preload_image_cache_default');
Variable::delete('preload_image_cache_selected');
