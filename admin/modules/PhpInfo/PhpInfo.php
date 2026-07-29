<?php

class PhpInfo extends AdminModule {

    public function menu_entry() {
        return "PHP Info";
    }

    public function icon() {
        return 'bi-info-circle';
    }

    public function body() {
        ob_start();
        phpinfo();
        $pinfo = ob_get_clean();

        // the name attribute "module_Zend Optimizer" of an anker-tag is not xhtml valide, so replace it with "module_Zend_Optimizer"
        $pinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $pinfo);
        $pinfo = str_replace('module_Zend Optimizer', 'module_Zend_Optimizer', $pinfo);

        return $this->render('PhpInfo.tpl', array('phpinfo_html' => $pinfo));
    }

}

?>
