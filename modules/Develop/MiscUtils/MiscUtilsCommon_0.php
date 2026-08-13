<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

function p($x, $strip_html_entities = true) {
    $tag = $strip_html_entities ? 'xmp' : 'pre';
    print("<$tag style=\"text-align:left; padding: 10px; margin: 10px; background-color: white; color: black; border: 1px solid black\">");
    if (is_string($x))
        print($x);
    else
        var_dump($x);
    print("</$tag>");
}

class Develop_MiscUtilsCommon extends ModuleCommon {
    
}

require_once dirname(__FILE__) . '/kint/Kint.class.php';

?>