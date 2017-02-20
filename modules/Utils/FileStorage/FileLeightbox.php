<?php
/**
 * Filestorage File Leightbox
 *
 * @author     Adam Bukowski <abukowski@telaxus.com>
 * @copyright  Telaxus LLC
 * @license    MIT
 * @version    0.1
 * @package    epesi-Utils
 * @subpackage FileStorage
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Class Utils_FileStorage
 */
class Utils_FileStorage_FileLeightbox
{

    public static function get_file_leightbox($meta)
    {
        $theme = Base_ThemeCommon::init_smarty();

        $links = array();

        $lid = 'get_file_' . md5(serialize($meta));

        $close_leightbox_js = 'leightbox_deactivate(\'' . $lid . '\');';
        $theme->assign('save_options_id', '');
        $onclick = $close_leightbox_js;
        $theme->assign('download_options_id', 'attachment_download_options_' . $meta['id']);


        $file_history_key = md5(serialize($meta['id']));
        if (isset($_GET['utils_attachment_file_history']) && $_GET['utils_attachment_file_history'] == $file_history_key) {
            echo 'show file history ' . $meta['id'];
//            self::navigate_to_file_history($meta['id']);
        }

        $view_link = 'modules/Utils/Attachment/get.php?' . http_build_query(array('id' => $meta['id'], 'cid' => CID, 'view' => 1));
        $download_link = 'modules/Utils/Attachment/get.php?' . http_build_query(array('id' => $meta['id'], 'cid' => CID));
        $history_href_js = Epesi::escapeJS(Module::create_href_js(array('utils_attachment_file_history' => $file_history_key)), true, false);


        $links['view'] = '<a href="' . $view_link . '" target="_blank" onclick="' . $close_leightbox_js . '">' . __('View') . '</a><br>';
        $links['download'] = '<a href="' . $download_link . '" onclick="' . $close_leightbox_js . '">' . __('Download') . '</a><br>';
        $links['history'] = '<a onclick="' . $history_href_js . ';'.$close_leightbox_js.'">' . __('File History') . '</a><br>';

        load_js('modules/Utils/Attachment/remote.js');
        $theme->assign('filename', $meta['filename']);
        $filepath = $meta['file'];
        $theme->assign('file_size', __('File size: %s', array(filesize_hr($filepath))));

        $theme->assign('labels', array(
            'filename'  => __('Filename'),
            'file_size' => __('File size')
        ));

        foreach ($links as $key => &$l) {
            $theme->assign($key, $l);
            $l = Base_ThemeCommon::parse_links($key, $l);
        }
        $theme->assign('__link', $links);

        $custom_getters = array();
        $getters = ModuleManager::call_common_methods('attachment_getters');
        foreach ($getters as $mod => $arr) {
            if (is_array($arr)) {
                foreach ($arr as $caption => $func) {
                    $cus_id = md5($mod . $caption . serialize($func));
                    if (isset($_GET['utils_attachment_custom_getter']) && $_GET['utils_attachment_custom_getter'] == $cus_id) {
                        call_user_func_array(array($mod . 'Common', $func['func']), array($filepath, $meta['filename'], $meta['id']));
                    }
                    $custom_getters[] = array('open' => '<a href="javascript:void(0)" onclick="' . Epesi::escapeJS(Module::create_href_js(array('utils_attachment_custom_getter' => $cus_id)), true, false) . ';' . $close_leightbox_js . '">', 'close' => '</a>', 'text' => $caption, 'icon' => $func['icon']);
                }
            }
        }
        $theme->assign('custom_getters', $custom_getters);

        ob_start();
        Base_ThemeCommon::display_smarty($theme, 'Utils_FileStorage', 'download');
        $c = ob_get_clean();

        Libs_LeightboxCommon::display($lid, $c, __('File'));
        return Libs_LeightboxCommon::get_open_href($lid);
    }

}
