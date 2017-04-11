<?php
/**
 * ActionBar
 *
 * This class provides action bar component.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage actionbar
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_ActionBar extends Module
{
    private static $launchpad;

    /**
     * Compares two action bar entries to determine order.
     * For internal use only.
     *
     * @param mixed action bar entry
     * @param mixed action bar entry
     * @return int comparison result
     */
    public function compare($a, $b)
    {
        if (!isset(Base_ActionBarCommon::$available_icons[$a['icon']])) return 1;
        if (!isset(Base_ActionBarCommon::$available_icons[$b['icon']])) return -1;
        if (!isset($a['position'])) $a['position'] = 0;
        if (!isset($b['position'])) $b['position'] = 0;
        $ret = $a['position'] - $b['position'];
        if ($ret == 0) $ret = Base_ActionBarCommon::$available_icons[$a['icon']] - Base_ActionBarCommon::$available_icons[$b['icon']];
        if ($ret == 0) $ret = strcmp(strip_tags($a['label']), strip_tags($b['label']));
        return $ret;
    }

    public function compare_launcher($a, $b)
    {
        return strcmp($a['label'], $b['label']);
    }

    /**
     * Displays action bar.
     */
    public function body()
    {

        $this->help('ActionBar basics', 'main');

        $icons = Base_ActionBarCommon::get();
        $fa_icons = FontAwesome::get();

        //sort
        usort($icons, array($this, 'compare'));

        //translate
        foreach ($icons as &$i) {
            $description = $i['description'];
            if ($i['description'])
                $t = Utils_TooltipCommon::open_tag_attrs($description);
            else
                $t = '';
            $fa = array_key_exists('fa-' . $i['icon'], $fa_icons);
            $i['open'] = '<a ' . $i['action'] . ' ' . $t . ' class="icon-' . ($fa ? $i['icon'] : md5($i['icon'])) . '">';
            $i['close'] = '</a>';
            if (!$fa && strpos($i['icon'], '/') !== false && file_exists($i['icon'])) {
                $i['icon_url'] = $i['icon'];
                unset($i['icon']);
            }
            //if (isset(Base_ActionBarCommon::$available_icons[$i['icon']]))
            //	$i['icon'] = Base_ThemeCommon::get_template_file('Base_ActionBar','icons/'.$i['icon'].'.png');
        }

        $launcher_left = [];
        $launcher_right = [];
        if (Base_AclCommon::is_user()) {
            $opts = Base_Menu_QuickAccessCommon::get_options();
            if (!empty($opts)) {
                self::$launchpad = array();
                foreach ($opts as $k => $v) {
                    if (Base_ActionBarCommon::$quick_access_shortcuts
                        && Base_User_SettingsCommon::get(Base_Menu_QuickAccessCommon::module_name(), $v['name'] . '_d')
                    ) {
                        $ii = array();
                        $trimmed_label = trim(substr(strrchr($v['label'], ':'), 1));
                        $ii['label'] = $trimmed_label ? $trimmed_label : $v['label'];
                        $ii['description'] = $v['label'];
                        $arr = $v['link'];
                        $icon = null;
                        $icon_url = null;
                        if (isset($v['link']['__icon__'])) {
                            if (array_key_exists('fa-' . $v['link']['__icon__'], $fa_icons))
                                $icon = $v['link']['__icon__'];
                            else
                                $icon_url = Base_ThemeCommon::get_template_file($v['module'], $v['link']['__icon__']);
                        } else
                            $icon_url = Base_ThemeCommon::get_template_file($v['module'], 'icon.png');
                        if (!$icon && !$icon_url) $icon_url = 'cog';
                        $ii['icon'] = $icon;
                        $ii['icon_url'] = $icon_url;

                        if (isset($arr['__url__']))
                            $ii['open'] = '<a href="' . $arr['__url__'] . '" target="_blank" class="icon-' . ($icon ? $icon : md5($icon_url)) . '">';
                        else
                            $ii['open'] = '<a ' . Base_MenuCommon::create_href($this, $arr) . ' class="icon-' . ($icon ? $icon : md5($icon_url)) . '">';
                        $ii['close'] = '</a>';

                        if ($ii['label'] == 'Launchpad') {
                            $launcher_left[] = $ii;
                        } else {
                            $launcher_right[] = $ii;
                        }
                    }
                    if (Base_User_SettingsCommon::get(Base_Menu_QuickAccessCommon::module_name(), $v['name'] . '_l')) {
                        $ii = array();
                        $trimmed_label = trim(substr(strrchr($v['label'], ':'), 1));
                        $ii['label'] = $trimmed_label ? $trimmed_label : $v['label'];
                        $ii['description'] = $v['label'];
                        $arr = $v['link'];
                        if (isset($arr['__url__']))
                            $ii['open'] = '<a href="' . $arr['__url__'] . '" target="_blank" onClick="actionbar_launchpad_deactivate()">';
                        else {
                            $ii['open'] = '<a onClick="actionbar_launchpad_deactivate();' . Base_MenuCommon::create_href_js($this, $arr) . '" href="javascript:void(0)">';
                        }
                        $ii['close'] = '</a>';

                        $icon = null;
                        $icon_url = null;
                        if (isset($v['link']['__icon__'])) {
                            if (array_key_exists('fa-' . $v['link']['__icon__'], $fa_icons))
                                $icon = $v['link']['__icon__'];
                            else
                                $icon_url = Base_ThemeCommon::get_template_file($v['module'], $v['link']['__icon__']);
                        } else
                            $icon_url = Base_ThemeCommon::get_template_file($v['module'], 'icon.png');
                        if (!$icon && !$icon_url) $icon_url = 'cog';
                        $ii['icon'] = $icon;
                        $ii['icon_url'] = $icon_url;

                        self::$launchpad[] = $ii;
                    }
                }
                usort(self::$launchpad, array($this, 'compare_launcher'));
                if (!empty(self::$launchpad)) {
                    $th = $this->pack_module(Base_Theme::module_name());
                    usort(self::$launchpad, array($this, 'compare_launcher'));
                    $th->assign('icons', self::$launchpad);
                    eval_js_once('actionbar_launchpad_deactivate = function(){leightbox_deactivate(\'actionbar_launchpad\');}');
                    ob_start();
                    $th->display('launchpad');
                    $lp_out = ob_get_clean();
                    $big = count(self::$launchpad) > 10;
                    Libs_LeightboxCommon::display('actionbar_launchpad', $lp_out, __('Launchpad'), $big);
                    array_unshift($launcher_left, array('label' => __('Launchpad'), 'description' => 'Quick modules launcher', 'open' => '<a ' . Libs_LeightboxCommon::get_open_href('actionbar_launchpad') . '>', 'close' => '</a>', 'icon' => 'th-large'));
                }
            }
        }

	if(!isset($_SESSION['client']['__history_id__'])){
	    History::set();
	    $_SESSION['client']['__history_id__'] = null;
	}

        $curr_hist = History::get_id();

        $last_hist = $curr_hist == 1 ? $curr_hist : $this->get_module_variable('hist', 0);

        if (0 !== $last_hist) {
            $launcher_left[] = array(
                'label' => __('Back'),
                'description' => __('Get back'),
                'icon' => 'arrow-left',
                'icon_url' => null,
                'open' => '<a ' . $this->create_back_href() . '>',
                'close' => '</a>'
            );
        }

        $this->set_module_variable('hist', $curr_hist);

        if($this->is_back()){
            History::back();
        }

        //display
        $th = $this->pack_module(Base_Theme::module_name());
        $th->assign('icons', $icons);
        $th->assign('launcher_right', array_reverse($launcher_right));
        $th->assign('launcher_left', array_reverse($launcher_left));
        $th->display();
    }

}

?>
