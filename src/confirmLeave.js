import jQuery from 'jquery';

class ConfirmLeave {
    // object of form ids which require confirmation for leaving the page
    // store changed fields to pass through submit state
    forms = {};
    // store currently submitted form. If it'll fail to validate then we
    // have list of changed fields
    forms_freezed = {};
    message = 'Leave page?';
    //checks if leaving the page is approved
    check = () => {
        //remove non-existent forms from the array
        [...Object.keys(this.forms), ...Object.keys(this.forms_freezed)].filter(item => (document.getElementById(item) === null)).forEach(item => Epesi.confirmLeave.deactivate(item));

        //check if there is any not freezed form with changed values to confirm leave
        let requires_confirmation = Object.keys(this.forms).reduce((previous, form_id) => previous || document.querySelector(`#${form_id} .changed-input`) !== null, false);

        if (requires_confirmation) {
            //take care if user disabled alert messages
            let openTime = new Date();
            let confirmed = false;
            try {
                confirmed = confirm(this.message);
            } catch(e) {
                confirmed = true;
            }
            let closeTime = new Date();
            if ((closeTime - openTime) < 350) confirmed = true;

            if(!confirmed) return false;
            else this.deactivate();
        }
        return true;
    };

    activate = (form_id, message) => {
        this.message = message;
        // add form or restore from freezed state - form is freezed for submit
        if (!(form_id in this.forms)) {
            if (form_id in this.forms_freezed) {
                this.forms[form_id] = this.forms_freezed[form_id];
                delete this.forms_freezed[form_id];
            } else {
                this.forms[form_id] = {};
            }
        }
        // apply class to all changed inputs - required for validation failure
        for (var key in this.forms[form_id]) {
            jQuery('#' + form_id + ' [name="' + key + '"]').addClass('changed-input');
        }
        // on change add changed-input class
        jQuery('#' + form_id).on('change', 'input, textarea, select', function (e) {
            if (e.originalEvent === undefined) return;
            var el = jQuery(this);
            el.addClass('changed-input');
            var form = form_id in Epesi.confirmLeave.forms ? Epesi.confirmLeave.forms[form_id] : Epesi.confirmLeave.forms_freezed[form_id];
            form[el.attr('name')] = true;
        });
        //take care if user refreshing or going to another page
        jQuery(window).unbind('beforeunload').on('beforeunload', function () {
            if (jQuery('.changed-input').length) {
                return Epesi.confirmLeave.message;
            }
        });
    };

    deactivate = (form_id = null) => {
        if (form_id !== null) {
            delete this.forms[form_id];
            delete this.forms_freezed[form_id];
        } else {
            this.forms = {};
            this.forms_freezed = {};
        }

        if (!Object.keys(this.forms).length) jQuery(window).unbind('beforeunload');
    };

    freeze = (form_id) => {
        if (form_id in this.forms) {
            this.forms_freezed[form_id] = this.forms[form_id];
            delete this.forms[form_id];
        }
    }
}

export default ConfirmLeave;