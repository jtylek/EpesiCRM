var utils_genericbrowser__hidetip = false;
var utils_genericbrowser__last_td = false;
var utils_genericbrowser__hide_current = 0;
var utils_genericbrowser__firefox_fix = false;

table_overflow_show = function (e_td, force) {
	var e_tip = $("table_overflow");
	if (!e_tip) return;
	// *** firefox fix ***
	if (utils_genericbrowser__firefox_fix == e_td) return; 
	utils_genericbrowser__firefox_fix = e_td;
	// *** firefox fix ***
	if (force || e_td.scrollHeight>e_td.clientHeight || e_td.scrollWidth>e_td.clientWidth) {
		if (utils_genericbrowser__last_td) table_overflow_hide(utils_genericbrowser__hide_current);
		e_tip.style.minWidth = e_td.clientWidth+"px";
		e_tip.style.minHeight = e_td.clientHeight+"px";
		while (e_td.childNodes.length>0) {
			$("table_overflow_content").appendChild(e_td.firstChild);
		}
		utils_genericbrowser__last_td = e_td;
		
		e_td.innerHTML = $("table_overflow_content").innerHTML; // fix for cell height, TODO: find  a way to keep size without copying html, nodes ids are messing up
		var is_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
		var leftOffset = -2;
		if (is_chrome)
			leftOffset = -1;
		
		e_tip.clonePosition(e_td,{setHeight: false, setWidth: false, offsetTop: -2, offsetLeft: leftOffset});
		e_tip.show();
		if (e_tip.clientWidth<=e_td.clientWidth && e_tip.clientHeight-3<=e_td.clientHeight) { // 3 pixels because Firefox is getting lost at what height should elements have
			utils_genericbrowser__hidetip = true;
			table_overflow_hide(utils_genericbrowser__hide_current); // Work-around for firefox, because it cannot handle scrollWidth in <td>
		} else {
			if(navigator.userAgent.match(/(iPad|iPod|iPhone)/i) == null)
				table_overflow_hide_delayed();
		}
	}
};
table_overflow_stop_hide = function() {
	utils_genericbrowser__hidetip = false;
}
table_overflow_hide_delayed = function () {
	// *** firefox fix ***
	utils_genericbrowser__firefox_fix = false;
	// *** firefox fix ***
	utils_genericbrowser__hide_current++;
	utils_genericbrowser__hidetip = true;
	setTimeout("table_overflow_hide("+utils_genericbrowser__hide_current+");", 800);
};
table_overflow_hide = function (current) {
	if (!utils_genericbrowser__hidetip || utils_genericbrowser__hide_current!=current) return;
	var e_tip = $("table_overflow");
	if (!e_tip) return;
	if (utils_genericbrowser__last_td) {
		utils_genericbrowser__last_td.innerHTML = '';
		while ($("table_overflow_content").childNodes.length>0) {
			utils_genericbrowser__last_td.appendChild($("table_overflow_content").firstChild);
		}
		utils_genericbrowser__last_td = false;
	}
	e_tip.hide();
};
Utils_GenericBrowser__overflow_div = function() {
	div = document.createElement("div");
	div.id = "table_overflow";
	div.style.position = "absolute";
	div.style.display = "none";
	div.style.zIndex = 9;
	div.style.left = 0;
	div.style.top = 0;
	div.innerHTML = "<div id=\'table_overflow_content\' class=\'Utils_GenericBrowser__overflow_div_content\'></div>";
	div.className = "Utils_GenericBrowser__overflow_div";
	body = document.getElementsByTagName("body");
	body = body[0];
	document.body.appendChild(div);
	div.onmouseout = table_overflow_hide_delayed;
	if(navigator.userAgent.match(/(iPad|iPod|iPhone)/i) == null)
		div.onmouseover = table_overflow_stop_hide;
	else
		div.onmouseover = table_overflow_hide_delayed;
};
