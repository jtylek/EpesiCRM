<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// OpenFlashChart (Flash, non-functional in every browser since ~2021) -> ChartJS
// migration - see AI-shared/deliberate-removals.md. This module's requires() now
// lists Libs_ChartJSInstall instead of Libs_OpenFlashChartInstall - requires()
// alone only affects fresh installs' dependency resolution, existing installs
// need this patch to actually pick up the new module.
ModuleManager::install('Libs/ChartJS');
