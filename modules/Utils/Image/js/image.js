images = new Array();

utils_image_load_thumb_f = function(id) {
	if(images[id].complete) {
		var src = images[id].src;
		delete(images[id]);
		var elems = document.getElementsByClassName('loader_'+id);
		for(i = 0; i < elems.length; i++) 
			elems[i].src = src;
	} else {
		setTimeout("utils_image_load_thumb_f('"+id+"')", 20);
	}
};

utils_image_load_thumb = function(image, id) {
	if(typeof(images[id])=='undefined') {
		images[id] = new Image(1,1);
		images[id].src = image;
		setTimeout("utils_image_load_thumb_f('"+id+"')", 10);
	}
};