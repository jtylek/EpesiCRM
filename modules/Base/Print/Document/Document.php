<?php

abstract class Base_Print_Document_Document
{
    private $filename;
    private $footer;
    private $file_extension;

    abstract public function document_type_name();

    public function init($data)
    {

    }

    public function set_filename($filename)
    {
        $this->filename = $filename;
    }

    public function get_filename()
    {
        return $this->filename;
    }

    public function set_filename_extension($extension)
    {
        $this->file_extension = $extension;
    }

    public function get_filename_extension()
    {
        return $this->file_extension;
    }

    public function get_filename_with_extension()
    {
        $full = $this->get_filename() . '.' . $this->get_filename_extension();
        return $full;
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