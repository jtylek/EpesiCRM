full_screen = function(URL) {
   //window.open(URL,"","fullscreen,scrollbars");
   var width = screen.width;
   var height = screen.height;
   win = window.open(URL, 'epesi', 'fullscreen=yes, scrollbars, menubar=no, toolbar=no, location=no, directories=no, resizable=yes, status=no, left=0, top=0, width=' + width + ', height=' + height);
   win.focus();
}
