function initArray() {
    this.length = initArray.arguments.length;
    for (var i = 0; i < this.length; i++)
        this[i] = initArray.arguments[i];
}
function from10toradix(value,radix){
    var retval = '';
    var ConvArray = new initArray(0,1,2,3,4,5,6,7,8,9,'A','B','C','D','E','F');
    var intnum;
    var tmpnum;
    var i = 0;

    intnum = parseInt(value,10);
    if (isNaN(intnum)){
        retval = 'NaN';
    }else{
        while (intnum > 0.9){
            i++;
            tmpnum = intnum;
            // cancatinate return string with new digit:
            retval = ConvArray[tmpnum % radix] + retval;  
            intnum = Math.floor(tmpnum / radix);
            if (i > 100){
                // break infinite loops
                retval = 'NaN';
                break;
            }
        }
    }
    return retval;
}
update_color_html = function() {
    red = $('color_red').innerHTML;
    green = $('color_green').innerHTML;
    blue = $('color_blue').innerHTML;
    red = from10toradix(red, 16);
    green = from10toradix(green, 16);
    blue = from10toradix(blue, 16);
    if(red.length < 2)
    	red = '0'+red;
    if(green.length < 2)
    	green = '0'+green;
    if(blue.length < 2)
    	blue = '0'+blue;
    if(red == '0')
    	red = '00';
    if(green == '0')
    	green = '00';
    if(blue == '0')
    	blue = '00';
    color = '#'+red+green+blue;
    $('color_html').innerHTML = color;
    $('color_preview').style.background = color;
}

update_color_red = function(value){
    $('color_red').innerHTML = value.toFixed();
    update_color_html();
};
update_color_green = function(value){
    $('color_green').innerHTML = value.toFixed();
    update_color_html();
};
update_color_blue = function(value){
    $('color_blue').innerHTML = value.toFixed();
    update_color_html();
};
var sred = new Control.Slider(
	'handle_red','track_red', 
	{axis:'vertical',range:$R(0,255), minimum:0, maximum:255, increment:10, alignY: 1, onSlide: update_color_red}
);
var sgreen = new Control.Slider(
	'handle_green','track_green', 
	{axis:'vertical',range:$R(0,255), minimum:0, maximum:255, increment:10, alignY: 1, onSlide: update_color_green}
);
var sblue = new Control.Slider(
	'handle_blue','track_blue', 
	{axis:'vertical',range:$R(0,255), minimum:0, maximum:255, increment:10, alignY: 1, onSlide: update_color_blue}
);
