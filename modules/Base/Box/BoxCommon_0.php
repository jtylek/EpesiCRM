<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_BoxCommon{
	public static function get_main_module_name() {
		$ini = Base_ThemeCommon::get_template_file('Base_Box','default.ini');
		if(!$ini) {
			print(Base_LangCommon::ts('Unable to read Base_Box.ini file! Please create one, or change theme.'));
			return;
		}
		$containers = parse_ini_file($ini,true);
		return $containers['Logged']['main'];
	}
}

?>
