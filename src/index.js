import jQuery from '../vendor/bower-asset/gentelella/vendors/jquery/dist/jquery.js'
import 'script-loader!../libs/HistoryKeeper.js';

import Epesi from './epesi';

window.focus_by_id = (idd) => {
    let xx = document.getElementById(idd);
    if (xx) setTimeout(function () {
        jq(xx).focus();
    }, 200);
};

window.addslashes = x => x.replace(/('|"|\\)/g, "\\$1")

window.wait_while_null = (id, action) => {
    if (eval('typeof(' + id + ')') != 'undefined')
        eval(action);
    else
        setTimeout('wait_while_null(\'' + addslashes(id) + '\', \'' + addslashes(action) + '\')', 200);
};

window._chj = Epesi.href;

jQuery(document).ajaxSend(function (ev, xhr, settings) {
    xhr.setRequestHeader('X-Client-ID', Epesi.client_id);
});

window.getTotalTopOffet = e => {
    let ret = 0;
    while (e != null) {
        ret += e.offsetTop;
        e = e.offsetParent;
    }
    return ret;
};
window.is_visible = function (element) {
    if (!element) return false;
    let display = jQuery(element).css('display');
    if (display == "none") return false;
    if (element.parentNode && element.parentNode.style) {
        xxx = element.parentNode;
        return is_visible(element.parentNode);
    }
    return true;
};
window.jq = jQuery;
window.jQuery = jQuery;
window.$ = jQuery;

window.Epesi = Epesi;
