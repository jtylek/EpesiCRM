jump_to_record_id = function (tab) {
	if (document.getElementById("jump_to_record_input").style.display=="")
		document.getElementById("jump_to_record_input").style.display = "none";
	else
		document.getElementById("jump_to_record_input").style.display = "";
	focus_by_id("jump_to_record_input");
}

// Quick-add-in-table row: ArrowUp/ArrowDown jump between fields (like Tab),
// Enter submits. Delegated on document since the row is re-rendered after
// each submit/validation pass. Skips ArrowUp/Down inside a textarea so
// multi-line notes keep normal cursor movement, and skips Enter-submits
// when Shift is held in a textarea so Shift+Enter still inserts a newline.
document.addEventListener('keydown', function(e) {
	var row = document.getElementById('add_in_table_row');
	if (!row || !row.contains(e.target)) return;
	var tag = e.target.tagName;
	if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && tag !== 'TEXTAREA') {
		var fields = Array.prototype.slice.call(row.querySelectorAll('input:not([type=hidden]):not([type=submit]), select, textarea'));
		var idx = fields.indexOf(e.target);
		if (idx === -1) return;
		var next = fields[e.key === 'ArrowDown' ? idx + 1 : idx - 1];
		if (next) {
			e.preventDefault();
			next.focus();
		}
	} else if (e.key === 'Enter' && !(tag === 'TEXTAREA' && e.shiftKey)) {
		e.preventDefault();
		var save = row.querySelector('input[name=submit_qanr]');
		if (save) save.click();
	}
});
