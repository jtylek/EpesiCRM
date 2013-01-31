<?php

class EpesiShell extends AdminModule {

    public function menu_entry() {
        return 'Run PHP command';
    }

    public function required_epesi_modules() {
        return ModuleLoader::all_modules;
    }

    public function body() {
        ob_start();
        print('<div class="title">EPESI Shell</div>');
        print('<p>Place "return" statement to see returned value</p>');
        $cmd = $this->cmd();
        if ($cmd) {
            ob_start();
            $returned_value = eval($cmd . ';');
            $output = ob_get_clean();
            $this->output($output);
            $this->returned_value($returned_value);
        }
        $this->form($cmd);
        return ob_get_clean();
    }

    private function cmd() {
        return isset($_POST['cmd']) ? $_POST['cmd'] : null;
    }

    private function output($output) {
        print('<pre>Output:</pre><div style="border: 1px solid lightgray; padding: 10px">' . $output . '</div>');
    }

    private function returned_value($value) {
        print('<pre>Returned value:</pre><div style="border: 1px solid lightgray; padding: 10px; overflow: auto"><xmp>');
        var_dump($value);
        print('</xmp></div>');
    }

    private function form($cmd = '') {
        print('<pre>Command:<form method="post"><textarea type="text" name="cmd" style="width:100%">' . $cmd . '</textarea><input type="submit" value="execute"/></form></pre>');
    }

}

?>