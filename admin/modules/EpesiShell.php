<?php

class EpesiShell extends AdminModule {

    public function menu_entry() {
        return __('Run PHP command');
    }

    public function icon() {
        return 'bi-terminal';
    }

    public function required_epesi_modules() {
        return ModuleLoader::all_modules;
    }

    public function body() {
        $disabled = !defined('ALLOW_PHP_EMBEDDING') || !ALLOW_PHP_EMBEDDING;
        $vars = array('disabled' => $disabled);

        if ($disabled) {
            $vars['disabled_message'] = __('This tool is currently disabled. Please edit file %s and add following line %s', array(DATA_DIR . '/config.php', "define('ALLOW_PHP_EMBEDDING', 1);"));
            $vars['disabled_note'] = __("This tool allows you to execute any PHP code as it would be executed in EPESI application. It's intended mainly for developers. Don't leave it enabled on non-development installation.");
        } else {
            $cmd = $this->cmd();
            $vars['cmd'] = $cmd;
            $vars['has_output'] = false;
            if ($cmd) {
                ob_start();
                $returned_value = eval($cmd . ';');
                $vars['output'] = ob_get_clean();
                $vars['returned_dump'] = $this->dump($returned_value);
                $vars['has_output'] = true;
            }
        }

        return $this->render('EpesiShell.tpl', $vars);
    }

    private function cmd() {
        return $_POST['cmd'] ?? null;
    }

    private function dump($value) {
        ob_start();
        var_dump($value);
        return ob_get_clean();
    }

}

?>
