var quills = {};
var quills_hib = {};

// Quill's `table` blots (table-container/table-row/table-cell) are always
// registered - even without this module, pasting a <table> keeps its
// structure (confirmed live: HtmlPurifier doesn't strip table tags either -
// see AI-shared/ckeditor-to-quill-migration.md's follow-up notes for
// context). But without `table: true` here, nothing binds table-aware
// keyboard behavior: Tab doesn't move between cells, and Quill's default
// keyboard handlers - written for plain paragraphs - can corrupt the table
// once you start typing/navigating inside one. `modules/table` is Quill
// 2.0.3's own (undocumented-in-the-toolbar, API-only) table module - no
// toolbar UI, but it wires up correct cell navigation and exposes
// insertTable()/insertRow*()/insertColumn*()/deleteRow()/deleteColumn()/
// deleteTable() on quill.getModule('table') for any future toolbar button.
// `true` (not an options object) is enough to turn it on.

// Registers the icon for the custom 'switchtoolbar' button once, globally -
// Quill's toolbar module (see quill.js's Toolbar.prototype.attach) will still
// wire up a button with an unrecognized ql-<name> class as long as a handler
// is supplied per-instance in modules.toolbar.handlers, but it only gets an
// icon if that name is present in the shared ui/icons map. Three dots (an
// unambiguous "more options" glyph, unlike a direction-specific chevron that
// would need to flip) using the same ql-fill class Quill's own filled icons
// use, so quill.snow.css/theme.css (which inverts the whole toolbar for dark
// mode via a CSS filter, not per-icon rules) themes it automatically.
Quill.import('ui/icons')['switchtoolbar'] = '<svg viewbox="0 0 18 18"><circle class="ql-fill" cx="3" cy="9" r="1.5"/><circle class="ql-fill" cx="9" cy="9" r="1.5"/><circle class="ql-fill" cx="15" cy="9" r="1.5"/></svg>';

// Same registration mechanism as switchtoolbar above, for the "Insert Table"
// button enableInsertTable() (quill.php) adds to the Advanced toolbar. A
// plain 2x2 grid glyph, using ql-stroke (not ql-fill, unlike switchtoolbar's
// dots) to match how Quill's own stroke-drawn icons (list/blockquote/etc)
// look - either class is themed automatically by the same dark-mode filter
// rule in theme.css.
Quill.import('ui/icons')['inserttable'] = '<svg viewbox="0 0 18 18"><rect class="ql-stroke" height="12" width="12" x="3" y="3"/><line class="ql-stroke" x1="3" x2="15" y1="9" y2="9"/><line class="ql-stroke" x1="9" x2="9" y1="3" y2="15"/></svg>';

function quill_toolbar_config(key, value) {
	var handlers = {};
	if (value.switchable) handlers.switchtoolbar = function() { quill_switch_toolbar(key); };
	if (value.insertTable) handlers.inserttable = function() { quill_insert_table(key); };
	if (!value.switchable && !value.insertTable) return value.toolbar;
	return { container: value.toolbar, handlers: handlers };
}

// Prompts for a row/column count via a native prompt() - the same lightweight
// pattern already used elsewhere in this app for a single quick value (e.g.
// Base/Dashboard/ab.js's get_new_dashboard_tab_name()) - then inserts a table
// via Quill's own official modules/table API (the `table: true` module
// config below) at the current cursor position, or at the end of the
// document if the editor has no active selection yet (e.g. the toolbar was
// clicked before ever clicking into the editor body).
function quill_insert_table(key) {
	var quill = quills[key];
	var config = quill && quill.epesiConfig;
	if (!quill || !config) return;
	var rows = parseInt(prompt(config.insertTableRowsPrompt, '3'), 10);
	if (!rows || rows < 1 || rows > 20) return;
	var cols = parseInt(prompt(config.insertTableColsPrompt, '3'), 10);
	if (!cols || cols < 1 || cols > 20) return;
	var range = quill.getSelection(true) || { index: quill.getLength(), length: 0 };
	quill.setSelection(range.index, 0, 'silent');
	quill.getModule('table').insertTable(rows, cols);
}

function quill_update_button_titles(quill) {
	var config = quill.epesiConfig;
	var toolbarModule = quill.getModule('toolbar');
	var container = toolbarModule && toolbarModule.container;
	if (!config || !container) return;
	if (config.switchable) {
		var switchBtn = container.querySelector('.ql-switchtoolbar');
		if (switchBtn) switchBtn.title = config.advanced ? config.switchTitleToBasic : config.switchTitleToAdvanced;
	}
	if (config.insertTable) {
		var tableBtn = container.querySelector('.ql-inserttable');
		if (tableBtn) tableBtn.title = config.insertTableTitle;
	}
}

// Quill has no API to swap a running instance's toolbar module config, so
// switching presets destroys and recreates the instance against the
// alternate toolbar array - same technique this file's e:loading/e:load
// hibernate-recreate pair already uses for AJAX page swaps (and the same
// technique CKEditor's own ckeditor_reload() used for its toolbarswitch
// button), just triggered by a click instead of a page-replacement event.
function quill_switch_toolbar(key) {
	var quill = quills[key];
	var config = quill && quill.epesiConfig;
	var container = document.getElementById(key + '_editor');
	var input = document.getElementById(key);
	if (!quill || !config || !config.switchable || !container || !input) return;
	var html = quill.root.innerHTML;
	var toolbarModule = quill.getModule('toolbar');
	if (toolbarModule && toolbarModule.container) jQuery(toolbarModule.container).remove();
	container.innerHTML = '';
	config.advanced = !config.advanced;
	var value = {
		toolbar: config.advanced ? config.toolbarAdvanced : config.toolbarBasic,
		switchable: true,
		insertTable: config.insertTable,
		insertTableRowsPrompt: config.insertTableRowsPrompt,
		insertTableColsPrompt: config.insertTableColsPrompt
	};
	var newQuill = new Quill(container, {theme: 'snow', modules: {toolbar: quill_toolbar_config(key, value), table: true}});
	newQuill.clipboard.dangerouslyPasteHTML(html, 'silent');
	input.value = html;
	newQuill.epesiConfig = config;
	newQuill.on('text-change', function() { input.value = newQuill.root.innerHTML; });
	quill_update_button_titles(newQuill);
	newQuill.focus();
	quills[key] = newQuill;
}

// Quill lifecycle glue, playing the same role ck.js played for CKEditor around this
// app's AJAX-push page-replacement model (see AI-shared/legacy-js-migration.md's
// e:submit_form/e:loading/e:load trio, and AI-shared/bug-patterns.md's "uncaught
// exception in a document-level handler" entry for why every read here is guarded).
//
// Quill has no official .destroy() (unlike CKEditor's classic mode) and, when given
// an array toolbar config rather than a selector to an existing toolbar element,
// auto-generates its own toolbar as a new DOM node inserted as the *container's
// preceding sibling* - not a descendant of the container itself. Tearing an instance
// down therefore has to remove that sibling explicitly, or re-creating a new instance
// on the same container later (the e:load hibernate/recreate path) stacks a second
// toolbar on top of the first instead of replacing it.
function quill_teardown(key, quill) {
	var container = document.getElementById(key + '_editor');
	var input = document.getElementById(key);
	if (quill) {
		if (input) input.value = quill.root.innerHTML;
		var toolbarModule = quill.getModule('toolbar');
		if (toolbarModule && toolbarModule.container) jQuery(toolbarModule.container).remove();
	}
	if (container) {
		container.innerHTML = '';
		container.className = 'epesi-quill-editor';
		jQuery(container).hide();
	}
	delete quills[key];
}

jQuery(document).on('e:submit_form', function(e, name) {
	for (var key in quills) {
		var input = document.getElementById(key);
		if (input && name == input.form.getAttribute('name')) {
			quill_teardown(key, quills[key]);
		}
	}
});

jQuery(document).on('e:loading', function() {
	for (var key in quills) {
		var value = quills[key];
		if (value) quills_hib[key] = value.epesiConfig;
		quill_teardown(key, value);
	}
});

jQuery(document).on('e:load', function() {
	for (var key in quills_hib) {
		var value = quills_hib[key];
		(function(key, value) {
			var container = document.getElementById(key + '_editor');
			var input = document.getElementById(key);
			if (container && input && !quills[key]) {
				jQuery(container).show();
				var quill = new Quill(container, {theme: 'snow', modules: {toolbar: quill_toolbar_config(key, value), table: true}});
				// Quill reads the container's own pre-existing innerHTML as its
				// initial content on construction - true the first time this id
				// ever renders (quill.php's toHtml() pre-populates the container
				// with the current value for exactly this reason, and as a
				// readable fallback if this script never runs at all), but
				// quill_teardown() above always clears the container first, so
				// the hibernate/recreate path needs an explicit restore from the
				// hidden input, which quill_teardown() kept in sync.
				if (input.value) quill.clipboard.dangerouslyPasteHTML(input.value, 'silent');
				quill.epesiConfig = value;
				quill.on('text-change', function() { input.value = quill.root.innerHTML; });
				quill_update_button_titles(quill);
				quills[key] = quill;
			}
		})(key, value);
		delete quills_hib[key];
	}
});
