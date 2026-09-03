<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

// Fresh installs already default to 'adminltedark' (ThemeInstall.php), but any
// existing install still explicitly on the legacy 'default' theme (or with no
// default_theme set at all - both resolve to the classic table-based look, see
// Base_ThemeCommon::get_default_template()) keeps rendering that way forever,
// since nothing else ever changes this Variable after install. Opt existing
// installs into adminltedark on upgrade, matching the project's stated
// direction (see AI-shared/theming-and-frontend.md) rather than leaving them stuck
// on the theme nobody is investing further design work in.
$current = Variable::get('default_theme', false);
if ($current === false || $current === 'default') {
	Variable::set('default_theme', 'adminltedark');
}
