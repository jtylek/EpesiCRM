// Loaded between jQuery and jQuery Migrate in the production bundle only (index.php) -
// Migrate 3.x logs a console warning + stack trace per deprecated call even when minified,
// unlike the self-muting Migrate 1.x build it replaced. DEBUG_JS leaves logging on.
jQuery.migrateMute = true;
