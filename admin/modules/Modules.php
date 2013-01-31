<?php

class Modules extends AdminModule {

    public function body() {
        ob_start();

        //create default module form
        print('<div class="title">Select modules to disable</div>');
        print('<span style="color: red; font-weight: bold">WARNING!</span> Selected modules will be marked as not installed but uninstall methods <strong>WILL NOT</strong> be called. Any database tables and other modifications made by modules\' install methods will not be reverted.');
        print('<hr/><br/>');
        $form = new HTML_QuickForm('modulesform', 'post', $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET), '', null, true);

        $modules = DB::GetAssoc('SELECT name, name FROM modules ORDER BY name');
        foreach ($modules as $name)
            $form->addElement('checkbox', $name, $name);

        $form->addElement('button', 'submit_button', 'Disable Selected', array('class' => 'button', 'onclick' => 'if(confirm("Are you sure you want to disable selected modules?"))document.modulesform.submit();'));

        //validation or display
        if ($form->validate()) {
            //uninstall
            $vals = $form->exportValues();
            $ret = DB::Execute('SELECT * FROM modules ORDER BY priority DESC');
            $uninstalled = array();
            while ($row = $ret->FetchRow())
                if (isset($vals[$row['name']]) && $vals[$row['name']]) {
                    DB::Execute('DELETE FROM modules WHERE name=%s', array($row['name']));
                    $uninstalled[] = $row['name'];
                }
            if (count($uninstalled)) {
                print('<strong>Modules that were marked as uninstalled:</strong><br/>');
                print(implode('<br/>', $uninstalled));
            } else {
                print('No modules selected.');
            }
        } else {
            $form->display();
        }

        return ob_get_clean();
    }

    public function menu_entry() {
        return "Disable modules";
    }

}

?>