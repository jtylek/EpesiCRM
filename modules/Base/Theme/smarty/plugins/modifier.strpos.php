<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage PluginsModifier
 */

/**
 * Smarty modifier: strpos
 *
 * Usage in template:
 *   {$haystack|strpos:'needle'}
 *   {assign var="pos" value=$haystack|strpos:'-'}
 *
 * Returns integer position or boolean false (tak jak PHP strpos)
 *
 * @param  string  $haystack
 * @param  string  $needle
 * @param  int     $offset
 * @return int|false
 */
function smarty_modifier_strpos($haystack, $needle, $offset = 0)
{
    if (!is_string($haystack) && !is_numeric($haystack)) {
        return false;
    }
    if (!is_string($needle) && !is_numeric($needle)) {
        return false;
    }
    $haystack = (string) $haystack;
    $needle = (string) $needle;
    $offset = (int) $offset;

    return strpos($haystack, $needle, $offset);
}