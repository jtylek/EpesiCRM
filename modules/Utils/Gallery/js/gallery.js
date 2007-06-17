var gallery_slideshow_data = new Array();
var gallery_slideshow_real = new Array();
var preview;
var slideshow = -1;
var speed = 500;

utils_gallery_load_image_f = function() {
	if(preview.complete) {
		if( document.getElementById('gallery_slideshow_image') ) {
			document.getElementById('gallery_slideshow_image').src = preview.src;
		}
	} else {
		setTimeout("utils_gallery_load_image_f()", 20);
	}
};

utils_gallery_load_image = function(image) {
	preview = new Image(1,1);
	preview.src = gallery_slideshow_data[image];
	setTimeout("utils_gallery_load_image_f()", 10);
};



utils_gallery_set_speed = function() {
	speed = document.getElementById('utils_gallery_speed').value;
}
utils_gallery_load_image_in_slideshow_f = function(image) {
	if(preview.complete) {
		if( document.getElementById('gallery_slideshow_image') ) {
			document.getElementById('gallery_slideshow_image').src = preview.src;
			slideshow = setTimeout("utils_gallery_auto_start_f("+eval(parseInt(image)+1)+")", speed);
		}
	} else {
		setTimeout("utils_gallery_load_image_in_slideshow_f("+image+")", 20);
	}
};
utils_gallery_load_image_in_slideshow = function(image) {
	preview = new Image(1,1);
	preview.src = gallery_slideshow_data[image];
	setTimeout("utils_gallery_load_image_in_slideshow_f("+image+")", 10);
};

utils_gallery_set_data = function( images ) {
	gallery_slideshow_data = images;
}
utils_gallery_set_real = function( images ) {
	gallery_slideshow_real = images;
}




utils_gallery_auto_start_f = function(image) {
	if(image < gallery_slideshow_data.size() ) {
		document.getElementById('gallery_slideshow_auto').innerHTML = '<a class=utils_gallery_picture_link href="javascript:void(0)" onclick=utils_gallery_auto_stop('+image+')>Stop Slideshow</a>';
		document.getElementById('gallery_slideshow_image').src = 'modules/Utils/Gallery/theme/loader.gif';
		utils_gallery_load_image_in_slideshow(image);
	}
}


utils_gallery_auto_start = function(image) {
	document.getElementById('gallery_slideshow_prev').innerHTML = '&lt; Prev';
	document.getElementById('gallery_slideshow_next').innerHTML = 'Next &gt;';
	document.getElementById('gallery_slideshow_down').innerHTML = 'Download';
	slideshow = setTimeout("utils_gallery_auto_start_f("+eval(parseInt(image)+1)+")", speed);
}

utils_gallery_auto_stop = function(image) {	
	utils_gallery_show(image);
}


utils_gallery_show = function( image ) {
	clearTimeout(slideshow);
	//deb.innerHTML += image;
	
	if(image > 0 ) {
		document.getElementById('gallery_slideshow_prev').innerHTML = '<a class=utils_gallery_picture_link href="javascript:void(0)" onclick=utils_gallery_show('+eval(parseInt(image)-1)+')>&lt; Prev</a>';
	} else {
		document.getElementById('gallery_slideshow_prev').innerHTML = '&lt; Prev';
	}
	if(image < gallery_slideshow_data.size()-1 ) {
		document.getElementById('gallery_slideshow_next').innerHTML = '<a class=utils_gallery_picture_link href="javascript:void(0)" onclick=utils_gallery_show('+eval(parseInt(image)+1)+')>Next &gt;</a>';
	} else {
		document.getElementById('gallery_slideshow_next').innerHTML = 'Next &gt;';
	}
	document.getElementById('gallery_slideshow_auto').innerHTML = '<a class=utils_gallery_picture_link href="javascript:void(0)" onclick=utils_gallery_auto_start('+image+')>Start Slideshow</a>';
	document.getElementById('gallery_slideshow_down').innerHTML = '<a class=utils_gallery_picture_link href='+gallery_slideshow_real[image]+'>Download</a>';
	
	utils_gallery_load_image(image);
}