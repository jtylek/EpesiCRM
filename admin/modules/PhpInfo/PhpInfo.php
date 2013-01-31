<?php

class PhpInfo extends AdminModule {

    public function menu_entry() {
        return "PHP Info";
    }

    public function body() {
        ob_start();
        print('<link href="./modules/PhpInfo/phpinfo.css" rel="stylesheet" type="text/css" />');
        print('<div id="phpinfo" style="height: 800px; overflow-y:auto;">');
        ob_start();
        phpinfo();
        $pinfo = ob_get_contents();
        ob_end_clean();

        // the name attribute "module_Zend Optimizer" of an anker-tag is not xhtml valide, so replace it with "module_Zend_Optimizer"
        echo ( str_replace("module_Zend Optimizer", "module_Zend_Optimizer", preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo)) );

        print('</div>');
        return ob_get_clean();
    }

}

?>