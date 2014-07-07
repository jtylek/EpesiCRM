<?php

class Patches extends SteppedAdminModule {

    private $_patches_ran;

    public function menu_entry() {
        return 'Patches';
    }

    public function header() {
        print ('<link href="modules/Patches/patches.css" rel="stylesheet" type="text/css" />');
        return 'EPESI Patching utility';
    }

    public function required_epesi_modules() {
        return ModuleLoader::all_modules;
    }

    public function action() {
        $success = true;
        ini_set('display_errors',true);
        set_time_limit(0);
        switch ($this->get_step()) {
            case 1:
                $this->_patches_ran = PatchUtil::apply_new();
                $this->set_next_step(2);
                break;
            case 2:
                ModuleManager::create_common_cache();
                Base_ThemeCommon::themeup();
                Base_LangCommon::update_translations();
                break;
        }
        return $success;
    }

    public function start_text() {
        ob_start();
        $this->_print_patches_list();
        $patches = '<br/><br/>' . ob_get_clean();
        return '<center><b>This utility scans for available patches and applies them as necessary</b></center>' . $patches;
    }

    public function success_text() {
        switch ($this->get_step()) {
            case 1:
                ob_start();
                $this->_print_ran_patches();
                return ob_get_clean();
            case 2:
                $txt = 'The installation was patched and cache files were updated.';
                return "<center><strong>$txt</strong></center>";
        }
    }

    public function failure_text() {
        
    }

    private function _print_ran_patches() {
        $patched_success = 0;
        $patched_failure = 0;
        $patches_to_run = 0;
        print('<table id="patches">');
        /** @var Patch $patch */
        foreach ($this->_patches_ran as $patch) {
            $apply_status = $patch->get_apply_status();
            if ($apply_status === Patch::STATUS_SUCCESS) {
                $this->print_row_install_success($patch);
                $patched_success++;
            } elseif ($apply_status === Patch::STATUS_ERROR) {
                $this->print_row_install_failure($patch);
                $patched_failure++;
            } elseif ($apply_status === Patch::STATUS_TIMEOUT) {
                $this->print_row_install_in_progress($patch);
                $patches_to_run++;
            } elseif ($apply_status === Patch::STATUS_NEW) {
                $this->print_row_install_no_run($patch);
                $patches_to_run++;
            }
        }
        if ($patched_success)
            print('<tr><td><div class="left">&nbsp;</div><div class="center strong">Patches successfully installed: </div><div class="right green strong">' . $patched_success . '</div></td></tr>');
        if ($patched_failure)
            print('<tr><td><div class="left">&nbsp;</div><div class="center strong">Patches with errors: </div><div class="right red strong">' . $patched_failure . '</div></td></tr>');
        if ($patches_to_run) {
            print('<tr><td><div class="left">&nbsp;</div><div class="center strong">Patches to run: </div><div class="right gray strong">' . $patches_to_run . '</div></td></tr>');
        }

        if ($patches_to_run || $patched_failure) {
            $this->set_auto_run();
            $this->set_next_step(1);
            $msg = 'Do not close this page. Browser should reload this page until all patches will be applied.';
        } else {
            $msg = 'Press NEXT to rebuild common cache, theme files and base language files. This operation can take a minute...';
        }
        print('<tr><td><div class="content infotext">' . $msg . '</div></td></tr>');
        print('</table>');
    }

    private function _print_patches_list() {
        $counter = 0;
        $counterpatched = 0;
        $patches = PatchUtil::list_patches(false);
        print('<table id="patches">');
        foreach ($patches as $patch) {
            if ($patch->was_applied()) {
                $this->print_row_old_patch($patch);
                $counterpatched++;
            } else {
                $this->print_row_new_patch($patch);
                $counter++;
            }
        }
        print('<tr><td>&nbsp;</td></tr>');
        if ($counter)
            print('<tr><td><div class="left">&nbsp;</div><div class="center strong">New patches found: </div><div class="right red strong">' . $counter . '</div></td></tr>');
        if ($counterpatched)
            print('<tr><td><div class="left">&nbsp;</div><div class="center strong">Patches already installed: </div><div class="right green">' . $counterpatched . '</div></td></tr>');
        if ($counter == 0) {
            print('<tr><td><div class="content infotext">No new patches were found. Press NEXT to rebuild common cache and theme files. This operation can take a minute...</div></td></tr>');
            $this->set_next_step(2);
        } else {
            print('<tr><td><div class="content infotext">New patches were found. Press NEXT to apply them. This operation can take a minute...</div></td></tr>');
            $this->set_next_step(1);
        }
        print('</table>');
    }

    private function print_row_new_patch(Patch $patch) {
        print("<tr><td><div class=\"left strong\">{$patch->get_module()}</div><div class=\"center strong\"><b>{$patch->get_short_description()}</b></div><div class=\"right red strong\">new patch</div></td></tr>");
    }

    private function print_row_old_patch(Patch $patch) {
        print("<tr><td><div class=\"left\">{$patch->get_module()}</div><div class=\"center\">{$patch->get_short_description()}</div><div class=\"right green\">installed</div></td></tr>");
    }

    private function print_row_install_success(Patch $patch) {
        print("<tr><td><div class=\"left\">{$patch->get_module()}</div><div class=\"center\">{$patch->get_short_description()}</div><div class=\"right green strong\">patch installed</div></td></tr>");
    }

    private function print_row_install_no_run(Patch $patch) {
        print("<tr><td><div class=\"left\">{$patch->get_module()}</div><div class=\"center\">{$patch->get_short_description()}</div><div class=\"right gray strong\">patch not applied</div></td></tr>");
    }

    private function print_row_install_in_progress(Patch $patch) {
        $user_message = $patch->get_user_message();
        if ($user_message) {
            $user_message = "<div class=\"gray\">$user_message</div>";
        }
        print("<tr><td><div class=\"left\">{$patch->get_module()}</div><div class=\"center\">{$patch->get_short_description()}</div><div class=\"right blue strong\">in progress...$user_message</div></td></tr>");
    }

    private function print_row_install_failure(Patch $patch) {
        print("<tr><td><div class=\"left strong\">{$patch->get_module()}</div><div class=\"center strong\">{$patch->get_short_description()}</div><div class=\"right red strong\">install error</div></td></tr>");
        $errormsg = "File: {$patch->get_file()}\n{$patch->get_apply_error_msg()}";
        print("<tr><td><pre class=\"errorbox\">$errormsg</pre></td></tr>");
    }

}

?>