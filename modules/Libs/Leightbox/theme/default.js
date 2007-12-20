function libs_leightbox_resize(elem) {
	var s = elem.style;
	if(s.width == '100%') {
		s.top = '25%';
		s.left = '25%';
		s.width = '50%';
		s.height = '50%';
		s.border = '10px solid #b3b3b3';
		s.padding = '10px';
		s.background = 'white';
	}
	else {
		s.top = '0px';
		s.left = '0px';
		s.width = '100%';
		s.height = '100%';
		s.border = '0px';
		s.padding = '0px';
		s.background = '#f0f0f0';
	}
}
