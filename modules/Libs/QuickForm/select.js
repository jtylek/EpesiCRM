// global storage object for type-ahead info, including reset() method
var typeAheadInfo = {last:0, 
                     accumString:"", 
                     delay:2000,
                     timeout:null, 
                     reset:function() {this.last=0; this.accumString=""}
                    };

// function invoked by select element's onkeydown event handler
function typeAhead() {
   // limit processing to IE event model supporter; don't trap Ctrl+keys
   if (window.event && !window.event.ctrlKey) {
      // timer for current event
      var now = new Date();
      // process for an empty accumString or an event within [delay] ms of last
      if (typeAheadInfo.accumString == "" || now - typeAheadInfo.last < typeAheadInfo.delay) {
         // make shortcut event object reference
         var evt = window.event;
         // get reference to the select element
         var selectElem = evt.srcElement;
         // get typed character ASCII value
         var charCode = evt.keyCode;
		 // detect tab keys
		 if (charCode==9) {
			// clear the accumulated string
			typeAheadInfo.accumString = '';
			// exit
			return;
		 }
         // get the actual character, converted to uppercase
         var newChar =  String.fromCharCode(charCode).toUpperCase();
         // append new character to accumString storage
         typeAheadInfo.accumString += newChar;
         // grab all select element option objects as an array
         var selectOptions = selectElem.options;
         // prepare local variables for use inside loop
         var txt, nearest;
         // look through all options for a match starting with accumString
         for (var i = 0; i < selectOptions.length; i++) {
            // convert each item's text to uppercase to facilitate comparison
            // (use value property if you want match to be for hidden option value)
            txt = selectOptions[i].text.toUpperCase();
            // record nearest lowest index, if applicable
            nearest = (typeAheadInfo.accumString > 
                       txt.substr(0, typeAheadInfo.accumString.length)) ? i : nearest;
            // process if accumString is at start of option text
            if (txt.indexOf(typeAheadInfo.accumString) == 0) {
               // stop any previous timeout timer
               clearTimeout(typeAheadInfo.timeout);
               // store current event's time in object 
               typeAheadInfo.last = now;
               // reset typeAhead properties in [delay] ms unless cleared beforehand
               typeAheadInfo.timeout = setTimeout("typeAheadInfo.reset()", typeAheadInfo.delay);
               // visibly select the matching item
               selectElem.selectedIndex = i;
               // prevent default event actions and propagation
               evt.cancelBubble = true;
               evt.returnValue = false;
               // exit function
               return false;   
            }            
         }
         // if a next lowest match exists, select it
         if (nearest != null) {
            selectElem.selectedIndex = nearest;
         }
      } else {
         // not a desired event, so clear timeout
         clearTimeout(typeAheadInfo.timeout);
      }
      // reset global object
      typeAheadInfo.reset();
   }
   return true;
}