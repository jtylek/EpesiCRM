import jQuery from 'expose-loader?jq!expose-loader?jQuery!expose-loader?$!jquery';

import 'bootstrap';
import 'bootstrap/less/bootstrap.less';

import 'jquery-ui';
import 'jquery-ui/ui/widgets/sortable';
import 'jquery-ui/themes/base/all.css'

import 'select2';
import 'select2/dist/css/select2.css';

import 'script-loader!../libs/jquery.clonePosition.js';
import Chart from 'chart.js';

import 'gentelella/production/less/custom.css';
import 'font-awesome/css/font-awesome.css';

window.Chart = Chart;

import Epesi from './epesi';
window.EpesiClass = Epesi;

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