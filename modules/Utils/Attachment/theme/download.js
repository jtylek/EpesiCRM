function resize_download() {
	launchpad_style = document.getElementById("leightbox_attachment").style;
	if(launchpad_style.width == '100%') {
		launchpad_style.top = '25%';
		launchpad_style.left = '25%';
		launchpad_style.width = '50%';
		launchpad_style.height = '50%';
		launchpad_style.border = '10px solid #b3b3b3';
		launchpad_style.padding = '10px';
		launchpad_style.background = 'white';
	}
	else {
		launchpad_style.top = '0px';
		launchpad_style.left = '0px';
		launchpad_style.width = '100%';
		launchpad_style.height = '100%';
		launchpad_style.border = '0px';
		launchpad_style.padding = '0px';
		launchpad_style.background = '#f0f0f0';
	}
}
