<?php

class Patches extends SteppedAdminModule {

    private $_patches_ran;

    public function menu_entry() {
        return 'Patches';
    }

    public function icon() {
        return 'bi-bandaid';
    }

    public function header() {
        return __('EPESI Patching utility');
    }

    public function required_epesi_modules() {
        return ModuleLoader::all_modules;
    }

    public function action() {
        ini_set('display_errors', true);
        set_time_limit(0);
        switch ($this->get_step()) {
            case 1:
                $this->_patches_ran = PatchUtil::apply_new();
                $this->set_next_step(2);
                break;
            case 2:
                Cache::clear();
                break;
        }
        return true;
    }

    public function start_text() {
        $filter = $_GET['filter'] ?? 'uninstalled';
        if (!in_array($filter, array('installed', 'uninstalled')))
            $filter = 'uninstalled';

        $patches = PatchUtil::list_patches(false);
        $rows = array();
        $new_count = 0;
        $installed_count = 0;

        foreach ($patches as $patch) {
            if ($patch->was_applied()) {
                $installed_count++;
                if ($filter == 'installed')
                    $rows[] = $this->row($patch, 'installed', 'text-bg-success');
            } else {
                $new_count++;
                if ($filter == 'uninstalled')
                    $rows[] = $this->row($patch, 'new patch', 'text-bg-danger', true);
            }
        }

        if ($new_count == 0) {
            $this->set_next_step(2);
            $message = 'No new patches were found. Press NEXT to rebuild common cache and theme files. This operation can take a minute...';
        } else {
            $this->set_next_step(1);
            $message = 'New patches were found. Press NEXT to apply them. This operation can take a minute...';
        }

        return $this->render('Patches.tpl', array(
            'mode' => 'list',
            'filter' => $filter,
            'rows' => $rows,
            'new_count' => $new_count,
            'installed_count' => $installed_count,
            'patched_success' => 0,
            'patched_failure' => 0,
            'patches_to_run' => 0,
            'message' => $message,
        ));
    }

    public function success_text() {
        if ($this->get_step() == 1)
            return $this->ran_patches_view();

        return $this->render('Patches.tpl', array('mode' => 'done'));
    }

    public function failure_text() {
        return '';
    }

    private function ran_patches_view() {
        $rows = array();
        $patched_success = 0;
        $patched_failure = 0;
        $patches_to_run = 0;

        /** @var Patch $patch */
        foreach ($this->_patches_ran as $patch) {
            $status = $patch->get_apply_status();
            if ($status === Patch::STATUS_SUCCESS) {
                $rows[] = $this->row($patch, 'patch installed', 'text-bg-success');
                $patched_success++;
            } elseif ($status === Patch::STATUS_ERROR) {
                $extra = "File: {$patch->get_file()}\n{$patch->get_apply_error_msg()}";
                $rows[] = $this->row($patch, 'install error', 'text-bg-danger', true, $extra);
                $patched_failure++;
            } elseif ($status === Patch::STATUS_TIMEOUT) {
                $rows[] = $this->row($patch, 'in progress...', 'text-bg-info', false, $patch->get_user_message());
                $patches_to_run++;
            } elseif ($status === Patch::STATUS_NEW) {
                $rows[] = $this->row($patch, 'patch not applied', 'text-bg-secondary');
                $patches_to_run++;
            }
        }

        if ($patched_failure) {
            $this->set_next_step(1);
            $message = 'Some errors occured. Try to fix them and rerun patch.';
        } elseif ($patches_to_run) {
            $this->set_auto_run();
            $this->set_next_step(1);
            $message = 'Do not close this page. Browser should reload this page until all patches will be applied.';
        } else {
            $message = 'Press NEXT to clear cache...';
        }

        return $this->render('Patches.tpl', array(
            'mode' => 'ran',
            'rows' => $rows,
            'new_count' => 0,
            'installed_count' => 0,
            'patched_success' => $patched_success,
            'patched_failure' => $patched_failure,
            'patches_to_run' => $patches_to_run,
            'message' => $message,
        ));
    }

    private function row(Patch $patch, $status_text, $badge_class, $strong = false, $extra = null) {
        return array(
            'module' => $patch->get_module(),
            'description' => $patch->get_short_description(),
            'status_text' => $status_text,
            'badge_class' => $badge_class,
            'strong' => $strong,
            'extra' => $extra,
        );
    }

}

?>
