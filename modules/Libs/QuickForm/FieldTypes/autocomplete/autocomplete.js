// Vanilla replacement for script.aculo.us's Ajax.Autocompleter/Autocompleter.Base
// (modules/Libs/ScriptAculoUs/1.8.0/controls.js), used by HTML_QuickForm_autocomplete
// and, through it, the autoselect/automulti field types. Same public surface the
// generated on_hide_js code relies on: .element, .update, .options.onHide,
// .stopIndicator(), .hide().
//
// The suggestion <div> is moved to <body> right away (constructor time) instead of
// being patched in later - avoids the containing-block bug the old widget had when
// printed inside a position:relative ancestor (see git history for the AdminLTE
// theme's old Autocompleter.Base.prototype.show patch, no longer needed).
function EpesiAutocompleter(elementId, updateId, url, options) {
	this.element = document.getElementById(elementId);
	this.update = document.getElementById(updateId);
	this.url = url;
	this.options = options || {};
	this.options.paramName = this.options.paramName || this.element.name;
	this.options.frequency = this.options.frequency || 0.4;
	this.options.minChars = this.options.minChars || 1;
	var self = this;
	this.options.onShow = this.options.onShow || function(element, update) {
		var rect = element.getBoundingClientRect();
		update.style.position = 'absolute';
		update.style.left = (rect.left + window.scrollX) + 'px';
		update.style.top = (rect.bottom + window.scrollY) + 'px';
		update.style.minWidth = rect.width + 'px';
		update.style.display = '';
		update.style.opacity = '0';
		update.style.transition = 'opacity 0.15s';
		requestAnimationFrame(function(){ update.style.opacity = '1'; });
	};
	this.options.onHide = this.options.onHide || function(element, update) {
		update.style.transition = 'opacity 0.15s';
		update.style.opacity = '0';
		setTimeout(function(){ update.style.display = 'none'; update.style.opacity=''; update.style.transition=''; }, 150);
	};

	this.hasFocus = false;
	this.changed = false;
	this.active = false;
	this.index = 0;
	this.entryCount = 0;
	this.oldElementValue = this.element.value;
	this.observer = null;

	if (this.update.parentNode !== document.body) document.body.appendChild(this.update);
	this.element.setAttribute('autocomplete', 'off');
	this.update.style.display = 'none';

	this.element.addEventListener('blur', function(){ setTimeout(function(){ self.hide(); }, 250); self.hasFocus = false; self.active = false; });
	this.element.addEventListener('keydown', function(ev){ self.onKeyDown(ev); });
	this.element.addEventListener('keyup', function(ev){ self.onKeyUp(ev); });
}

EpesiAutocompleter.collectTextExcludingClass = function(element, className) {
	var text = '';
	for (var i = 0; i < element.childNodes.length; i++) {
		var node = element.childNodes[i];
		if (node.nodeType === 3) {
			text += node.nodeValue;
		} else if (node.nodeType === 1 && node.childNodes.length && !node.classList.contains(className)) {
			text += EpesiAutocompleter.collectTextExcludingClass(node, className);
		}
	}
	return text;
};

EpesiAutocompleter.prototype.onKeyDown = function(ev) {
	if (this.active && (ev.key === 'Tab' || ev.key === 'Enter')) ev.preventDefault();
};

EpesiAutocompleter.prototype.onKeyUp = function(ev) {
	if (this.active) {
		switch (ev.key) {
			case 'Tab':
			case 'Enter':
				// hide() (not just selectEntry()) matters here: it's what runs
				// the on_hide_js_code a caller like autoselect/automulti wired
				// up via on_hide_js() to parse the "id__label" token selectEntry()
				// just wrote into the visible input and commit it into the real
				// field. The click handler below already does both together -
				// without hide() here too, Tab/Enter left that raw token sitting
				// in the box instead of committing the selection.
				this.selectEntry();
				this.hide();
				ev.preventDefault();
				return;
			case 'Escape':
				this.hide();
				this.active = false;
				ev.preventDefault();
				return;
			case 'ArrowLeft':
			case 'ArrowRight':
				return;
			case 'ArrowUp':
				this.markPrevious();
				this.render();
				return;
			case 'ArrowDown':
				this.markNext();
				this.render();
				return;
		}
	} else if (ev.key === 'Tab' || ev.key === 'Enter') {
		return;
	}

	this.changed = true;
	this.hasFocus = true;
	if (this.observer) clearTimeout(this.observer);
	var self = this;
	this.observer = setTimeout(function(){ self.onObserverEvent(); }, this.options.frequency*1000);
};

EpesiAutocompleter.prototype.show = function() {
	if (this.update.style.display === 'none') this.options.onShow(this.element, this.update);
};

EpesiAutocompleter.prototype.hide = function() {
	this.stopIndicator();
	if (this.update.style.display !== 'none') this.options.onHide(this.element, this.update);
};

EpesiAutocompleter.prototype.startIndicator = function() {};
EpesiAutocompleter.prototype.stopIndicator = function() {};

EpesiAutocompleter.prototype.render = function() {
	if (this.entryCount > 0) {
		for (var i = 0; i < this.entryCount; i++) {
			var entry = this.getEntry(i);
			if (this.index === i) entry.classList.add('selected');
			else entry.classList.remove('selected');
		}
		if (this.hasFocus) {
			this.show();
			this.active = true;
		}
	} else {
		this.active = false;
		this.hide();
	}
};

EpesiAutocompleter.prototype.markPrevious = function() {
	if (this.index > 0) this.index--;
	else { this.index = this.entryCount - 1; this.update.scrollTop = this.update.scrollHeight; }
	var selection = this.getEntry(this.index);
	if (selection.offsetTop < this.update.scrollTop) this.update.scrollTop = this.update.scrollTop - selection.offsetHeight;
};

EpesiAutocompleter.prototype.markNext = function() {
	if (this.index < this.entryCount - 1) this.index++;
	else { this.index = 0; this.update.scrollTop = 0; }
	var selection = this.getEntry(this.index);
	var bottom = selection.offsetTop + selection.offsetHeight;
	if (bottom > this.update.scrollTop + this.update.offsetHeight) this.update.scrollTop = this.update.scrollTop + selection.offsetHeight;
};

EpesiAutocompleter.prototype.getEntry = function(index) {
	return this.update.firstElementChild.children[index];
};

EpesiAutocompleter.prototype.getCurrentEntry = function() {
	return this.getEntry(this.index);
};

EpesiAutocompleter.prototype.selectEntry = function() {
	this.active = false;
	this.updateElement(this.getCurrentEntry());
};

EpesiAutocompleter.prototype.updateElement = function(selectedElement) {
	this.element.value = EpesiAutocompleter.collectTextExcludingClass(selectedElement, 'informal');
	this.oldElementValue = this.element.value;
	this.element.focus();
};

EpesiAutocompleter.prototype.updateChoices = function(choices) {
	if (this.changed || !this.hasFocus) return;
	this.update.innerHTML = choices;
	var list = this.update.firstElementChild;
	if (list && list.children.length) {
		this.entryCount = list.children.length;
		for (var i = 0; i < this.entryCount; i++) {
			var entry = this.getEntry(i);
			(function(self, entry, idx){
				entry.addEventListener('mouseover', function(){ self.index = idx; self.render(); });
				entry.addEventListener('click', function(){ self.index = idx; self.selectEntry(); self.hide(); });
			})(this, entry, i);
		}
	} else {
		this.entryCount = 0;
	}
	this.stopIndicator();
	this.update.scrollTop = 0;
	this.index = 0;
	this.render();
};

EpesiAutocompleter.prototype.getToken = function() {
	return (this.element.value || '').replace(/^\s+|\s+$/g, '');
};

EpesiAutocompleter.prototype.onObserverEvent = function() {
	this.changed = false;
	if (this.getToken().length >= this.options.minChars) {
		this.getUpdatedChoices();
	} else {
		this.active = false;
		this.hide();
	}
	this.oldElementValue = this.element.value;
};

EpesiAutocompleter.prototype.getUpdatedChoices = function() {
	var self = this;
	var body = encodeURIComponent(this.options.paramName) + '=' + encodeURIComponent(this.getToken());
	jQuery.ajax({
		url: this.url,
		method: 'POST',
		data: body,
		contentType: 'application/x-www-form-urlencoded',
		dataType: 'text'
	}).done(function(responseText) {
		self.updateChoices(responseText);
	});
};
