<?php

abstract class Base_Print_Document_Document
{
    private $filename;
    private $footer;

    abstract public function document_type_name();

    public function set_filename($filename)
    {
        $this->filename = $filename;
    }

    public function get_filename()
    {
        return $this->filename;
    }

    abstract public function write_text($text);

    abstract public function get_output();

    public function set_footer($text)
    {
        $this->footer = $text;
    }

    public function get_footer()
    {
        return $this->footer;
    }

    public function http_headers()
    {
    }

}