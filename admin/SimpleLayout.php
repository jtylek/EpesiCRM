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
                <title><?php $xx = $this->title ? $this->title . ' - ' : ''; print $xx; ?>Epesi Admin Utilities</title>
				<link href="https://fonts.googleapis.com/css?family=Exo+2:400,700|Titillium+Web:400,700&display=swap" rel="stylesheet">
                <link href="./images/admintools.css" rel="stylesheet" type="text/css" />

            </head>

            <body>
           
                <div class="banner" id="banner">
                        <div class="header">ADMIN UTILITIES</div>
                 </div>
                 
                 <div class="links">
                 <?php if ($this->show_action_links){
                     print($this->format_action_links());
                    }
                 ?> 
                 </div>

                 
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
        ?>

		<div class="footer" id="footer">
			<div><a href="https://epe.si"><img src="images/epesi-powered.png" border="0"></a></div>
			<div>Copyright &copy; 2006-<?php echo date('Y'); ?> by Janusz Tylek</div>
		    <div class="support">Support: <a href="https://epesi.org">https://epesi.org</a></div>
		</div>
            </body>
        </html>
    <?php }
}
?>
