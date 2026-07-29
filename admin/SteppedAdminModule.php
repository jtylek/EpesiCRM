<?php

/**
 * This class provide two step admin module. Just implement abstract methods.
 * action method should return boolean success value.
 *
 * @author  Janusz Tylek <j@epe.si>
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
        $this->_get_step();
        $header = $this->header();

        if ($this->step) {
            $content = $this->action() ? $this->success_text() : $this->failure_text();
            unset($_SESSION[self::$step_var]);
        } else {
            $content = $this->start_text();
        }

        return $this->render('stepped_module.tpl', array(
            'header' => $header,
            'content' => $content,
            'show_button' => $this->next_step !== null,
            'auto_run' => $this->auto_run,
            'button_text' => $this->button_text,
            'step_var' => self::$step_var,
            'next_step' => $this->next_step,
        ));
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
        $this->step = $_SESSION[self::$step_var] ?? false;
        // set next step if we are in first page
        if (!$this->step && !$this->next_step)
            $this->set_next_step(1);
    }

    protected function get_step() {
        return $this->step;
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
