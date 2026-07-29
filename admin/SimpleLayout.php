<?php

require_once(__DIR__ . '/AdminSmarty.php');

class SimpleLayout {

    private $menu_entries = array();
    private $show_action_links = true;
    private $action_links = array();
    private $title = '';
    private $current_module = null;

    function add_menu_entry($href, $text, $key = null, $icon = 'bi-tools') {
        $this->menu_entries[] = array('href' => $href, 'text' => htmlspecialchars($text), 'key' => $key, 'icon' => $icon);
    }

    function set_current_module($key) {
        $this->current_module = $key;
    }

    function hide_action_links() {
        $this->show_action_links = false;
    }

    function add_action_link($href, $text) {
        $this->action_links[$text] = $href;
    }

    function set_title($title) {
        $this->title = $title;
    }

    // Same PHP_SELF-based computation AdminIndex::start_epesi_action() already
    // uses for the "Start EPESI" link - kept here too (rather than threading it
    // through the constructor) since asset-URL construction is this class's own
    // concern, robust to wherever admin/ is deployed under the webroot.
    private function epesi_root_url() {
        return rtrim(dirname($_SERVER['PHP_SELF'], 2), '/') . '/';
    }

    private function format_action_links() {
        $links = array();
        foreach ($this->action_links as $text => $href)
            $links[] = array('href' => $href, 'text' => htmlspecialchars($text));
        return $links;
    }

    private function sorted_menu_entries() {
        $entries = $this->menu_entries;
        usort($entries, function($a, $b) { return strcasecmp($a['text'], $b['text']); });
        return $entries;
    }

    private function base_vars() {
        return array(
            'title' => $this->title,
            'epesi_url' => $this->epesi_root_url(),
            'show_action_links' => $this->show_action_links,
            'action_links' => $this->format_action_links(),
            'menu_entries' => $this->sorted_menu_entries(),
            'current_module' => $this->current_module,
        );
    }

    function display_html($html) {
        $vars = $this->base_vars();
        $vars['show_menu'] = false;
        $vars['body'] = $html;
        print(AdminSmarty::render('layout.tpl', $vars));
    }

    function display_menu() {
        $vars = $this->base_vars();
        $vars['show_menu'] = true;
        $vars['body'] = null;
        print(AdminSmarty::render('layout.tpl', $vars));
    }

}

?>
