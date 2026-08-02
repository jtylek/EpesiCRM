{* Same logo as the default theme, minus the wrapping <a href="#">. That anchor
   goes nowhere, but being focusable it took first place in the login page's tab
   order and drew a focus ring under the logo on load - the username field should
   be the first thing focused/tabbed to instead.

   The default template resolves its fallback image via {$theme_dir}/images/logo.png,
   which points into the data/ theme copy; this theme is served straight from
   modules/, so the fallback references the module's own copy of that same file. *}
<img border="0" src="{if $logo}{$logo}{else}modules/Base/Theme/images/logo.png{/if}" width="550" height="200" alt="EPESI">
