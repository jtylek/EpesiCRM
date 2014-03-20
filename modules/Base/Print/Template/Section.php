<?php defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Base_Print_Template_Section
{
    abstract public function fetch($section);

    abstract public function get_tpl();
}