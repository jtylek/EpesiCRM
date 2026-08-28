// Maximize/restore toggle for the adminltedark Leightbox header
// (default.tpl). A pure CSS-class swap (.leightbox.maximized, default.css),
// deliberately not theme/default.js's libs_leightbox_resize() - that writes
// inline top/left/width/height styles which fight this theme's transform-
// based centering (default.css's .leightbox rule) and visibly jump the
// popup to the old-style position, which is exactly why that button was
// omitted from this theme's header in the first place. Toggling a class
// instead lets plain CSS override the geometry cleanly, transform included.
function epesi_leightbox_toggle_maximize(btn) {
	var box = btn.closest('.leightbox');
	if (!box) return;
	var maximized = box.classList.toggle('maximized');
	var icon = btn.querySelector('i');
	if (icon) {
		icon.classList.toggle('bi-arrows-fullscreen', !maximized);
		icon.classList.toggle('bi-fullscreen-exit', maximized);
	}
	btn.title = maximized ? btn.getAttribute('data-restore-label') : btn.getAttribute('data-maximize-label');
}
