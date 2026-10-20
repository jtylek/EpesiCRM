# PHP 8.5 compatibility

## `ekmungai/eloquent-ifrs`

PHP 8.4 and newer report deprecations for typed parameters that have a
`null` default without an explicit nullable type. The affected dependency is
`ekmungai/eloquent-ifrs` 6.0.0. Examples include `IFRS\Models\Account`,
`IFRS\Models\Transaction`, and the reporting classes.

The upstream compatibility fix is commit
`5be52092e38f1329ef703237599e4990487295a` (PR #196). It explicitly marks
151 nullable parameters and is based on the installed package commit
`f391c50d87e18dd713da2342aa683f748403b28a`. At the time of writing, the fix
is not included in a released Composer version.

For the local Windows development installation, the upstream compatibility
patch was applied to `vendor/ekmungai/eloquent-ifrs`. This removes the PHP
8.5 deprecation notices without changing Epesi application code. A future
`composer install` or update can replace the vendor copy, so reapply the
upstream commit or use the released package once the fix is published.

Production settings should keep `display_errors=Off`; the notices should be
written to the Laravel log rather than displayed in the browser.
