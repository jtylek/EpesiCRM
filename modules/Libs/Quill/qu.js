var quills = {};
var quills_hib = {};

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
				var quill = new Quill(container, {theme: 'snow', modules: {toolbar: value.toolbar}});
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
				quills[key] = quill;
			}
		})(key, value);
		delete quills_hib[key];
	}
});
