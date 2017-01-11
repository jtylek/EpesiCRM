(function($){

	function setOffset(el, newOffset){
		var $el = $(el);

		// get the current css position of the element
		var cssPosition = $el.css('position');

		// whether or not element is hidden
		var hidden = false;

		// if element was hidden, show it
		if($el.css('display') == 'none'){
			hidden = true;
			$el.show();
		}

		// get the current offset of the element
		var curOffset = $el.offset();

		// if there is no current jQuery offset, give up
		if(!curOffset){
			// if element was hidden, hide it again
			if(hidden)
				$el.hide();
			return;
		}

		// set position to relative if it's static
		if (cssPosition == 'static') {
			$el.css('position', 'relative');
			cssPosition = 'relative';
		}

		// get current 'left' and 'top' values from css
		// this is not necessarily the same as the jQuery offset
		var delta = {
			left : parseInt($el.css('left'), 10),
			top: parseInt($el.css('top'), 10)
		};

		// if the css left or top are 'auto', they aren't numbers
		if (isNaN(delta.left)){
			delta.left = (cssPosition == 'relative') ? 0 : el.offsetLeft;
		}
		if (isNaN(delta.top)){
			delta.top = (cssPosition == 'relative') ? 0 : el.offsetTop;
		}

		if (newOffset.left || 0 === newOffset.left){
			$el.css('left', newOffset.left - curOffset.left + delta.left + 'px');
		}
		if (newOffset.top || 0 === newOffset.top){
			$el.css('top', newOffset.top - curOffset.top + delta.top + 'px');
		}

		// if element was hidden, hide it again
		if(hidden)
			$el.hide();
	}

	$.fn.extend({

		/**
		 * Store the original version of offset(), so that we don't lose it
		 */
		_offset : $.fn.offset,

		/**
		 * Set or get the specific left and top position of the matched
		 * elements, relative the the browser window by calling setXY
		 * @param {Object} newOffset
		 */
		offset : function(newOffset){
			return !newOffset ? this._offset() : this.each(function(){
				setOffset(this, newOffset);
			});
		}
	});

  $.fn.clonePosition = function(element, options){
    var options = $.extend({
      cloneWidth: true,
      cloneHeight: true,
      offsetLeft: 0,
      offsetTop: 0
    }, (options || {}));

    var offsets = $(element).offset();

    $(this).offset({top: (offsets.top + options.offsetTop),
      left: (offsets.left + options.offsetLeft)});

    if (options.cloneWidth) $(this).width($(element).width());
    if (options.cloneHeight) $(this).height($(element).height());

    return this;
  }
  
  $.fn.absolutize = function() {
    element = $(this);

    if (element.css('position') == 'absolute') {
        return element;
    }

    element.css({
        position: 'absolute',
        top:    element.offset().top + 'px',
        left:   element.offset().left + 'px',
        width:  element.width() + 'px',
        height: element.height() + 'px'
    });

    return this;
  }
})(jQuery);
