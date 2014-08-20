<?php

class SimpleLayout {

    private $menu_entries = array();
    private $show_action_links = true;
    private $action_links = array();
    private $title = '';

    function add_menu_entry($href, $text) {
        $this->menu_entries[$href] = htmlspecialchars($text);
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
    private function format_action_links() {
        $links = array();
        foreach($this->action_links as $text => $href) {
            $text = htmlspecialchars($text);
            $links[] = "<a href=\"$href\">$text</a>";
        }
        return implode(' | ', $links);
    }

    function display_html($html) {
        $this->pageheader();
        $this->startframe();

        print($html);

        $this->closeframe();
        $this->pagefooter();
    }

    function display_menu() {
        $this->pageheader();
        $this->startframe();

        asort($this->menu_entries);
        $i = 1;
        foreach ($this->menu_entries as $href => $text) {
            print("<a href=\"$href\">$i. {$text}</a><br/>");
            $i++;
        }
        if ($i == 1) { // no menu entries
            print("There is nothing here for you.");
        }

        $this->closeframe();
        $this->pagefooter();
    }

    function pageheader() { ?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title><?php $xx = $this->title ? $this->title . ' - ' : ''; print $xx; ?>EPESI Administrator's Tools</title>
                <link href="./images/admintools.css" rel="stylesheet" type="text/css" />

            </head>

            <body>
                <table id="banner" border="0" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="image">&nbsp;</td>
                        <td class="header">&nbsp;&nbsp;Administrator's Tools&nbsp;</td>
                    </tr>
                </table>
                <br/>
                <center>
    <?php }

    function startframe() { ?>
                    <div id="main">
                        <div class="content">
                            <?php
                        }

                        function closeframe() {
                            ?>
                        </div></div>
    <?php }

    function pagefooter() {
        print '<br/><center>';
        if ($this->show_action_links)
            print('<div class="title">' . $this->format_action_links() . '</div>');
        ?>
                    <hr/>
                    <p><a href="http://www.epesi.org"><img src="./images/epesi-powered.png" border="0" alt="EPESI powered"/></a></p>
                    <span class="footer">Copyright &copy; 2014 &bull; <a href="http://www.epesi.org/">EPESI framework</a> &bull; Application developed by <a href="http://www.telaxus.com">Telaxus LLC</a></span>
                    <br/>
                </center>

            </body>

        </html>
    <?php }

}
?>