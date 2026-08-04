<?php

class ChangeTheme extends AdminModule {

    public function menu_entry() {
        return 'Change Theme';
    }

    public function icon() {
        return 'bi-palette';
    }

    public function required_epesi_modules() {
        return array('Base_Theme');
    }

    public function body() {
        $themes = Base_Theme::list_themes();
        $current = Variable::get('default_theme');

        $form = new HTML_QuickForm('changethemeform', 'post', $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET), '', null, true);
        $form->addElement('select', 'theme', 'Theme', $themes);
        $form->setDefaults(array('theme' => $current));
        $form->addElement('button', 'submit_button', 'Save', array('class' => 'btn btn-primary', 'onclick' => 'document.changethemeform.submit();'));

        $message = null;
        if ($form->validate()) {
            $vals = $form->exportValues();
            if ($vals['theme'] !== $current) {
                Variable::set('default_theme', $vals['theme']);
                Base_ThemeCommon::create_cache();
                $current = $vals['theme'];
                $message = 'Theme changed to "' . $current . '".';
            }
        }

        ob_start();
        $form->display();
        $form_html = ob_get_clean();

        return $this->render('ChangeTheme.tpl', array(
            'form_html' => $form_html,
            'message' => $message,
        ));
    }

}

?>
