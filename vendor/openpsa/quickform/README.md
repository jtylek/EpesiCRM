quickform
=========

Forward compatible fork of HTML_QuickForm

This package is intended mainly as a drop-in replacement for existing installations of `HTML_Quickform`. See http://pear.php.net/manual/en/package.html.html-quickform.php for documentation.

The main differences to the original package are:

 - Compatible with PHP 5.4 and newer: It will run without producing warnings or deprecated notices
 - No PEAR dependencies: `HTML_Common` is replaced by a bundled version, and `PEAR_Error`s are replaced by exceptions
 - Support for Composer autoloading: All `include` statements have been removed in favor of classmap autoloading
 - Upon popular request, `HTML_QuickForm_Renderer_Tableless` has been included and is available out of the box (PHP only, you need
   to supply your own stylesheet)

### API Compatibility

 - Some calls are listed in the PEAR documentation as static, but making them statically callable without warnings would require significant rewrites and might break other use cases. So if you get errors about assuming `$this` from an incompatible context, just change your calls to nonstatic ones.

  - `HTML_QuickForm_Renderer::renderHidden` has a slightly changed signature and takes three arguments now: `&$element, $required, $error`, exactly like `HTML_QuickForm_Renderer::renderElement`. This means it is now possible to render validation errors on hidden fields, which is useful for example for CSRF fields. Custom renderer implementations need to add the two arguments to the method's signature, but actual implementations do not need to be changed.
