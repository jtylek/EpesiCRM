<?php

class Utils_RecordBrowser_ReplaceValue
{
    protected $value;
    protected $human_readable;
    protected $replace;
    protected $deactivate;
    protected $priority;

    /**
     * Utils_RecordBrowser_ReplaceValue constructor.
     *
     * @param string $value          Meta value that should be replaced with real one
     * @param string $human_readable Human redable string used in crits to words
     * @param mixed  $replace        Real value that will be used in crits
     * @param bool   $deactivate     Do not use this crit at all if $replace is null
     * @param int    $priority       You may override some system default replacements using higher priority
     */
    public function __construct($value, $human_readable, $replace, $deactivate = false, $priority = 1)
    {
        $this->value = $value;
        $this->human_readable = $human_readable;
        $this->replace = $replace;
        $this->deactivate = $deactivate;
        $this->priority = $priority;
    }

    /**
     * @return mixed
     */
    public function get_value()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function get_replace()
    {
        return $this->replace;
    }

    /**
     * @return boolean
     */
    public function get_deactivate()
    {
        return $this->deactivate;
    }

    /**
     * @return int
     */
    public function get_priority()
    {
        return $this->priority;
    }

    /**
     * @return string
     */
    public function get_human_readable()
    {
        return $this->human_readable;
    }

}
