var quills = {};
var quills_hib = {};

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

function quill_toolbar_config(key, value) {
	if (!value.switchable) return value.toolbar;
	return {
		container: value.toolbar,
		handlers: { switchtoolbar: function() { quill_switch_toolbar(key); } }
	};
}

function quill_update_switch_title(quill) {
	var config = quill.epesiConfig;
	if (!config || !config.switchable) return;
	var toolbarModule = quill.getModule('toolbar');
	var btn = toolbarModule && toolbarModule.container && toolbarModule.container.querySelector('.ql-switchtoolbar');
	if (btn) btn.title = config.advanced ? config.switchTitleToBasic : config.switchTitleToAdvanced;
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
	var value = { toolbar: config.advanced ? config.toolbarAdvanced : config.toolbarBasic, switchable: true };
	var newQuill = new Quill(container, {theme: 'snow', modules: {toolbar: quill_toolbar_config(key, value)}});
	newQuill.clipboard.dangerouslyPasteHTML(html, 'silent');
	input.value = html;
	newQuill.epesiConfig = config;
	newQuill.on('text-change', function() { input.value = newQuill.root.innerHTML; });
	quill_update_switch_title(newQuill);
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
				var quill = new Quill(container, {theme: 'snow', modules: {toolbar: quill_toolbar_config(key, value)}});
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
				quill_update_switch_title(quill);
				quills[key] = quill;
			}
		})(key, value);
		delete quills_hib[key];
	}
});
