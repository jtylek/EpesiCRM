/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence MIT
 */

import Loader from './loader';
import axios from 'axios';
import qs from 'qs';

const Epesi = {
    loader: new Loader(),
    default_indicator:'loading...',
    procOn:0,
    client_id:0,
    process_file:'process.php',
    indicator:'epesiStatus',
    indicator_text:'epesiStatusText',
    confirmLeave: {
        // object of form ids which require confirmation for leaving the page
        // store changed fields to pass through submit state
        forms:{},
        // store currently submitted form. If it'll fail to validate then we
        // have list of changed fields
        forms_freezed:{},
        message:'Leave page?',
        //checks if leaving the page is approved
        check: function() {
            //remove non-existent forms from the array
            jQuery.each(this.forms, function(f) {
                if (!jQuery('#'+f).length) Epesi.confirmLeave.deactivate(f);
            });
            jQuery.each(this.forms_freezed, function(f) {
                if (!jQuery('#'+f).length) Epesi.confirmLeave.deactivate(f);
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
                } catch(e) {
                    var confirmed = true;
                }
                var closeTime = new Date();
                if ((closeTime - openTime) > 350 && !confirmed) return false;
                this.deactivate();
            }
            return true;
        },
        activate: function(f, m) {
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
            jQuery(window).unbind('beforeunload').on('beforeunload', function() {
                if (jQuery('.changed-input').length) {
                    return Epesi.confirmLeave.message;
                }
            });
        },
        deactivate: function(f) {
            if (arguments.length) {
                delete this.forms[f];
                delete this.forms_freezed[f];
            } else {
                this.forms = {};
                this.forms_freezed = {};
            }

            if (!Object.keys(this.forms).length) jQuery(window).unbind('beforeunload');
        },
        freeze: function(f) {
            if (f in this.forms) {
                this.forms_freezed[f] = this.forms[f];
                delete this.forms[f];
            }
        }
    },
    updateIndicator: function() {
        var s = jq('#' + Epesi.indicator);
        if(s.length) {
            if(Epesi.procOn) s.show();
            else s.hide();
        }
        if (!Epesi.procOn) jq('#main_content').show();
    },
    updateIndicatorText: function(text) {
        jq('#' + Epesi.indicator_text).html(text);
    },
    history_on:1,
    history_add:function(id){
        Epesi.history_on=-1;
        unFocus.History.addHistory(id);
    },
    init:function(cl_id,path,params) {
        Epesi.client_id=cl_id;
        Epesi.process_file=path;

        axios.defaults.headers.common['X-Client-ID'] = cl_id;

        Epesi.history_add(0);
        if(typeof params == 'undefined')
            params = '';
        Epesi.request(params,0);
        unFocus.History.addEventListener('historyChange',function(history_id){
            switch(Epesi.history_on){
                case -1: Epesi.history_on=1;
                    return;
                case 1: Epesi.request('',history_id);
            }
        });
    },
    request: async function (url, history) {
        Epesi.procOn++;
        Epesi.updateIndicator();

        let keep_focus_field = null;
        if (typeof document.activeElement !== 'undefined') keep_focus_field = document.activeElement.getAttribute('id');

        try {
            let response = await axios.post(Epesi.process_file, qs.stringify({history, url}));
            jQuery(document).trigger('e:loading');
            eval(response.data);
        } catch (err) {
            Epesi.text(err.message, 'error_box', 'p')
        }

        Epesi.procOn--;
        Epesi.updateIndicator();
        Epesi.append_js("jQuery(document).trigger('e:load')");
        if (keep_focus_field !== null) {
            jQuery(`#${keep_focus_field}:visible`).focus();
        }
    },
    href: function(url, indicator, mode, disableConfirmLeave = false) {
        if (!disableConfirmLeave && !Epesi.confirmLeave.check()) return;
        if(Epesi.procOn === 0 || mode === 'allow'){
            !indicator ? Epesi.updateIndicatorText(Epesi.default_indicator) : Epesi.updateIndicatorText(indicator);
            Epesi.request(url);
        } else if(mode === 'queue') {
            setTimeout(() => Epesi.href(url, indicator, mode), 500);
        }
    },
    submit_form: function(formName, modulePath, indicator) {
        Epesi.confirmLeave.freeze(formName);
        let formSubmited = jQuery(`form[name="${formName}"] input[name="submited"]`);
        formSubmited.val(1);

        let formData = jQuery(`form[name="${formName}"]`).serializeArray();
        let url = qs.stringify(Object.assign(formData, {'__action_module__': encodeURIComponent(modulePath)}));
        _chj(url, indicator, '');

        formSubmited.val(0);
    },
    text: function(txt,idt,type) {
        var t=jq('#'+idt);
        if(!t.length) return;
        if(type=='i')//instead
            t.html(txt);
        else if(type=='p')//prepend
            t.prepend(txt);
        else if(type=='a')//append
            t.append(txt);
    },
    load_js:function(file) {
        Epesi.loader.load_js(file);
    },
    append_js:function(texti) {
        Epesi.loader.execute_js(texti);
    },
    append_js_script:function(texti) {
        console.warn('DEPRECATED: use Loader.execute_js instead');
        Loader.insertScript(texti);
    },
    js_loader:function() {
        console.warn('DEPRECATED: load is invoked implicitly');
        Epesi.loader.load();
    },
    load_css:function(file) {
        Epesi.loader.load_css(file);
    }
};

export default Epesi;