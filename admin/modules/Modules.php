<?php

class Modules extends AdminModule {

    public function icon() {
        return 'bi-toggles';
    }

    public function body() {
        $form = new HTML_QuickForm('modulesform', 'post', $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET), '', null, true);

        $states = array(ModuleManager::MODULE_ENABLED => 'Active',
                        ModuleManager::MODULE_DISABLED => 'Inactive');

        $modules = DB::GetAssoc('SELECT * FROM modules ORDER BY state, name');

        foreach ($modules as $name => $m) {
            $state = $m['state'] ?? ModuleManager::MODULE_ENABLED;
            if ($state == ModuleManager::MODULE_NOT_FOUND) {
                $state = ModuleManager::MODULE_DISABLED;
            }
            $form->addElement('select', $name, $name, $states);
            $form->setDefaults(array($name => $state));
        }

        $form->addElement('button', 'submit_button', 'Update', array('class' => 'btn btn-primary', 'onclick' => Module::wrap_confirm_js('Are you sure?', 'document.modulesform.submit();')));

        //validation or display
        if ($form->validate()) {
            //uninstall
            $vals = $form->exportValues();
            foreach ($vals as $k => $v) {
                if (isset($modules[$k]['state']) && $modules[$k]['state'] != $v) {
                    ModuleManager::set_module_state($k, $v);
                }
            }
        }

        ob_start();
        $form->display();
        $form_html = ob_get_clean();

        return $this->render('Modules.tpl', array('form_html' => $form_html));
    }

    public function menu_entry() {
        return "Disable modules";
    }

}

?>
