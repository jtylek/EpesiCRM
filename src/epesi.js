/*
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @licence MIT
 */

import Loader from './loader';
import axios from 'axios';
import qs from 'qs';
import ConfirmLeave from './confirmLeave';

const Epesi = {
    loader: new Loader(),
    confirmLeave: new ConfirmLeave(),
    default_indicator:'loading...',
    procOn:0,
    client_id:0,
    process_file:'process.php',
    indicator:'epesiStatus',
    indicator_text:'epesiStatusText',
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