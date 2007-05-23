images = new Array();
image_counter = 0;

load_thumb_f = function(id) {
	if(images[id].complete) {
		if( document.getElementById("img_"+id) ) {
			document.getElementById("img_"+id).src = images[id].src;
		}
	} else {
		setTimeout("load_thumb_f("+id+")", 20);
	}
};

load_thumb = function(image, id) {
	images[id] = new Image(1,1);
	images[id].src = image;
	setTimeout("load_thumb_f("+id+")", 10);
	image_counter++;
};