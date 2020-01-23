<?php

class Modules extends AdminModule {

    public function body() {
        ob_start();

        //create default module form
        print('<div class="title"><H1>Select modules to disable</H1></div>');
        print('Selected modules will be marked as not installed but uninstall methods will not be called. Any database tables and other modifications made by modules\' install methods will not be reverted.<br><br>');
        print('To uninstall module please use <strong>Modules Administration & Store</strong> in Epesi Application.<BR>');
        print('<hr/><br/>');
        $form = new HTML_QuickForm('modulesform', 'post', $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET), '', null, true);

        $states = array(ModuleManager::MODULE_ENABLED => 'Active',
                        ModuleManager::MODULE_DISABLED => 'Inactive');

        $modules = DB::GetAssoc('SELECT * FROM modules ORDER BY state, name');

        foreach ($modules as $m) {
            $name = $m['name'];
            $state = isset($m['state']) ? $m['state'] : ModuleManager::MODULE_ENABLED;
            if ($state == ModuleManager::MODULE_NOT_FOUND) {
                $state = ModuleManager::MODULE_DISABLED;
            }
            $form->addElement('select', $name, $name, $states);
            $form->setDefaults(array($name => $state));
        }

        $form->addElement('button', 'submit_button', 'Update', array('class' => 'button', 'onclick' => 'if(confirm("Are you sure?")) document.modulesform.submit();'));

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
        $form->display();

        return ob_get_clean();
    }

    public function menu_entry() {
        return "Disable modules";
    }

}

?>