var utils_genericbrowser__hidetip = false;
var utils_genericbrowser__hide_current = 0;

table_overflow_show = function (e_td) {
	utils_genericbrowser__hidetip = false;
	var e_tip = $("table_overflow");
	if (!e_tip) return;
	if (e_td.scrollHeight>e_td.clientHeight || e_td.scrollWidth>e_td.clientWidth) {
		e_tip.style.minWidth = e_td.clientWidth+"px";
		e_tip.style.minHeight = e_td.clientHeight+"px";
		$("table_overflow_content").innerHTML = e_td.innerHTML;
		e_tip.clonePosition(e_td,{setHeight: false, setWidth: false, offsetTop: -1, offsetLeft: -1});
		e_tip.show();
		if (e_tip.clientWidth<=e_td.clientWidth) e_tip.hide(); // Work-around for firefox, because it cannot handle scrollWidth in <td>
		table_overflow_hide_delayed();
	}
};
table_overflow_stop_hide = function() {
	utils_genericbrowser__hidetip = false;
}
table_overflow_hide_delayed = function () {
	utils_genericbrowser__hide_current++;
	utils_genericbrowser__hidetip = true;
	setTimeout("table_overflow_hide("+utils_genericbrowser__hide_current+");", 800);
};
table_overflow_hide = function (current) {
	if (!utils_genericbrowser__hidetip || utils_genericbrowser__hide_current!=current) return;
	var e_tip = $("table_overflow");
	if (!e_tip) return;
	e_tip.hide();
};
Utils_GenericBrowser__overflow_div = function() {
	div = document.createElement("div");
	div.id = "table_overflow";
	div.style.position = "absolute";
	div.style.display = "none";
	div.style.zIndex = 1900;
	div.style.left = 0;
	div.style.top = 0;
	div.innerHTML = "<div id=\'table_overflow_content\' class=\'Utils_GenericBrowser__overflow_div_content\'></div>";
	div.className = "Utils_GenericBrowser__overflow_div";
	body = document.getElementsByTagName("body");
	body = body[0];
	document.body.appendChild(div);
	div.onmouseout = table_overflow_hide_delayed;
	div.onmouseover = table_overflow_stop_hide;
};
