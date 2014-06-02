<?php

class MaintenanceModeSwitch extends SteppedAdminModule {

    public function menu_entry() {
        return "Maintenance Mode";
    }

    public function required_epesi_modules() {
        return array();
    }

    public function header() {
        return 'Maintenance Mode';
    }

    public function action() {
        $was_on = MaintenanceMode::is_on();
        if ($was_on) {
            MaintenanceMode::turn_off();
        } else {
            MaintenanceMode::turn_on_with_cookie();
        }
        $is_on = MaintenanceMode::is_on();
        return $was_on != $is_on;
    }

    public function start_text() {
        $txt = MaintenanceMode::is_on() ? 'on' : 'off';
        $this->set_button_text('Switch');
        return "<center>Maintenance mode is now <strong>$txt</strong>.";
    }

    public function success_text() {
        $txt = MaintenanceMode::is_on() ? 'on' : 'off';
        return "<center>Maintenance mode is now <strong>$txt</strong></center>";
    }

    public function failure_text() {
        return 'Failed to switch maintenance mode';
    }

}

?>