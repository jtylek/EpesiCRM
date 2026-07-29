<?php

/**
 * Launches update.php - a separate, standalone multi-step script (package
 * download, file wipe/extract, patches) with its own control flow and page
 * shell, not something this module's body() can safely wrap/embed.
 */
class UpdateEpesi extends AdminModule {

    public function menu_entry() {
        return 'Update Epesi';
    }

    public function icon() {
        return 'bi-cloud-arrow-down';
    }

    public function href() {
        $admin_index = $_SERVER['PHP_SELF'];
        return rtrim(dirname($admin_index, 2), '/') . '/update.php';
    }

    // href() above is what the sidebar/dashboard entry actually links to;
    // this only runs if someone lands on ?module=UpdateEpesi directly.
    public function body() {
        header('Location: ' . $this->href());
        exit();
    }

}

?>
