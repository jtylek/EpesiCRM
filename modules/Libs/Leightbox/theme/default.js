function libs_leightbox_resize(elem) {

	var s = elem.style;

	if(s.width == '100%') {
		if($(elem.id+'_bigsize').value==1) {
			s.top = '5%';
			s.left = '5%';
			s.width = '90%';
			s.height = '90%';
		} else {
			s.top = '25%';
			s.left = '15%';
			s.width = '70%';
			s.height = '50%';
		}
		//s.border = '10px solid #b3b3b3';
		s.padding = '0px';
	}

	else {
		s.top = '0px';
		s.left = '0px';
		s.width = '100%';
		s.height = '100%';
		s.border = '0px';
		s.padding = '0px';
	}
}
