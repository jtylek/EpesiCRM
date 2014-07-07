<?php

/**
 * This class provide two step admin module. Just implement abstract methods.
 * action method should return boolean success value.
 *
 * @author Adam Bukowski <abukowski@telaxus.com>
 */
abstract class SteppedAdminModule extends AdminModule {

    private static $step_var = 'step';
    private $button_text = 'Next';
    private $next_step;
    private $step = false;
    private $auto_run = false;

    abstract function header();

    abstract function action();

    abstract function start_text();

    abstract function success_text();

    abstract function failure_text();

    public function body() {
        ob_start();
        $this->_get_step();
        $this->_print_header();
        if ($this->step)
            $this->_run_action();
        else
            $this->_print_starting_page();
        $this->_print_action_button();
        return ob_get_clean();
    }

    private function _get_step() {
        if (isset($_POST[self::$step_var]) && $_POST[self::$step_var]) {
            $_SESSION[self::$step_var] = $_POST[self::$step_var];
            header('Location: ' . $_SERVER['REQUEST_URI']);
            die();
        }
        if (isset($_GET[self::$step_var]) && $_GET[self::$step_var]) {
            $_SESSION[self::$step_var] = $_GET[self::$step_var];
        }
        $this->step = isset($_SESSION[self::$step_var])
                ? $_SESSION[self::$step_var] : false;
        // set next step if we are in first page
        if (!$this->step && !$this->next_step)
            $this->set_next_step(1);
    }
    
    protected function get_step() {
        return $this->step;
    }

    private function _print_header() {
        print('<div class="title">' . $this->header() . '</div><br/>');
    }

    private function _run_action() {
        print( $this->action() ?
                        $this->success_text() :
                        $this->failure_text()
        );
        unset($_SESSION[self::$step_var]);
    }

    private function _print_starting_page() {
        print($this->start_text());
    }
    
    private function _print_action_button() {
        if ($this->next_step != null)
            print("<br/><br/><center>{$this->_run_button()}</center>");
    }

    private function _run_button() {
        $ret = '<form method="post" name="action_button">
            <input type="hidden" name="' . self::$step_var . '" value="' . 
                htmlspecialchars($this->next_step) . '" />';
        if ($this->auto_run) {
            $ret .= '</form>';
            $ret .= '<script type="text/javascript">document.action_button.submit()</script>';
        } else {
            $ret .= '<input type="submit" class="button" value="' .
                    htmlspecialchars($this->button_text) . '" /></form>';
        }
        return $ret;
    }
    
    protected function set_button_text($text) {
        $this->button_text = $text;
    }
    
    protected function set_next_step($value) {
        $this->next_step = $value;
    }

    protected function set_auto_run($arg = true)
    {
        $this->auto_run = $arg;
    }
}

?>