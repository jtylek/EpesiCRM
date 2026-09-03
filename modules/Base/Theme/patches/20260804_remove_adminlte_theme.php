<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// The light-only 'adminlte' theme was removed 2026-08-04 (see
// AI-shared/theming-and-frontend.md) - every modules/*/theme_adminlte/ directory is
// gone, so an install that still has it selected as the global default would
// otherwise silently fall back to raw, unstyled markup on next load. Migrate
// to its dark fork, the sole remaining AdminLTE-family member (already the
// project's own default_theme since ThemeInstall.php - this only matters for
// an existing install where an admin had explicitly picked the light one).
if (Variable::get('default_theme', false) === 'adminlte') {
	Variable::set('default_theme', 'adminltedark');
}
