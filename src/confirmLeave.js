import jQuery from 'jquery';

export default {
    // object of form ids which require confirmation for leaving the page
    // store changed fields to pass through submit state
    forms: {},
    // store currently submitted form. If it'll fail to validate then we
    // have list of changed fields
    forms_freezed: {},
    message: 'Leave page?',
    //checks if leaving the page is approved
    check: function () {
        //remove non-existent forms from the array
        jQuery.each(this.forms, function (f) {
            if (!jQuery('#' + f).length) Epesi.confirmLeave.deactivate(f);
        });
        jQuery.each(this.forms_freezed, function (f) {
            if (!jQuery('#' + f).length) Epesi.confirmLeave.deactivate(f);
        });
        //check if there is any not freezed form with changed values to confirm leave
        var requires_confirmation = false;
        if (Object.keys(this.forms).length) {
            for (var form in this.forms) {
                if (jQuery('#' + form + ' .changed-input').length) {
                    requires_confirmation = true;
                    break;
                }
            }
        }
        if (requires_confirmation) {
            //take care if user disabled alert messages
            var openTime = new Date();
            try {
                var confirmed = confirm(this.message);
            } catch (e) {
                var confirmed = true;
            }
            var closeTime = new Date();
            if ((closeTime - openTime) > 350 && !confirmed) return false;
            this.deactivate();
        }
        return true;
    },
    activate: function (f, m) {
        this.message = m;
        // add form or restore from freezed state - form is freezed for submit
        if (!(f in this.forms)) {
            if (f in this.forms_freezed) {
                this.forms[f] = this.forms_freezed[f];
                delete this.forms_freezed[f];
            } else {
                this.forms[f] = {};
            }
        }
        // apply class to all changed inputs - required for validation failure
        for (var key in this.forms[f]) {
            jQuery('#' + f + ' [name="' + key + '"]').addClass('changed-input');
        }
        // on change add changed-input class
        jQuery('#' + f).on('change', 'input, textarea, select', function (e) {
            if (e.originalEvent === undefined) return;
            var el = jQuery(this);
            el.addClass('changed-input');
            var form = f in Epesi.confirmLeave.forms ? Epesi.confirmLeave.forms[f] : Epesi.confirmLeave.forms_freezed[f];
            form[el.attr('name')] = true;
        });
        //take care if user refreshing or going to another page
        jQuery(window).unbind('beforeunload').on('beforeunload', function () {
            if (jQuery('.changed-input').length) {
                return Epesi.confirmLeave.message;
            }
        });
    },
    deactivate: function (f) {
        if (arguments.length) {
            delete this.forms[f];
            delete this.forms_freezed[f];
        } else {
            this.forms = {};
            this.forms_freezed = {};
        }

        if (!Object.keys(this.forms).length) jQuery(window).unbind('beforeunload');
    },
    freeze: function (f) {
        if (f in this.forms) {
            this.forms_freezed[f] = this.forms[f];
            delete this.forms[f];
        }
    }
}