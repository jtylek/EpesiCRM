---
name: Add PHPStan stubs for legacy libs
about: Small stubs to let PHPStan resolve legacy third-party symbols (Smarty, QuickForm, JSMin, Minify) in CI.

---

This PR adds non-functional stub declarations used only by PHPStan to avoid spurious "class/function/property not found" errors during static analysis. It does not affect runtime behaviour.

Follow-ups:
- Update EpesiSmartyRenderer::_tplFetch to return a string if callers expect a value, or update callers to not use the return value.
- Fix require('Minify.php') to use __DIR__ relative paths or Composer autoload.
- Correct casing for JSMin/JSmin usages to match the real library class.

Files changed:
- phpstan-stubs/smarty.stub.php
- phpstan-stubs/quickform.stub.php
- phpstan-stubs/jsmin.stub.php
- phpstan-stubs/minify.stub.php
- phpstan.neon
