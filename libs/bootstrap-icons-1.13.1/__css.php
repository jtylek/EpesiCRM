<?php
/* CSS loader for the vendored Bootstrap Icons stylesheet - same trick Epesi
   uses for module CSS (see modules/Base/Theme/theme_css.php).

   bootstrap-icons.min.css declares its @font-face sources as url("fonts/..."),
   which the browser resolves relative to the stylesheet's own URL. Served
   through the default /serve.php that base is the project root, so the fonts
   404 and every icon renders as a tofu box. Serving it through this script
   instead makes the base libs/bootstrap-icons-1.13.1/, where fonts/ actually
   lives, so the vendored CSS works unmodified. */
chdir('../../');
require_once('serve.php');
?>
