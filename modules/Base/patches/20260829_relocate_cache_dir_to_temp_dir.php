<?php

/**
 * Every runtime-generated cache that used to write under DATA_DIR . '/cache'
 * (minify cache in serve.php / modules/Base/Theme/theme_css.php; the compiled
 * module-registry cache in include/module_manager.php; the general Cache::
 * store, Phpfastcache Files driver, in include/cache.php; the asset-version
 * scan cache in include/misc.php's epesi_asset_version()) now writes under
 * TEMP_DIR instead - consistent with Smarty's own compile/cache, which
 * already used TEMP_DIR. See include/config.php's comment on why: regenerable
 * output shouldn't live inside DATA_DIR, so any backup strategy that just
 * archives DATA_DIR wholesale - not only BackupUtil's own exclude list (now
 * simplified to just '^temp/', see include/backups.php) - doesn't need to
 * know to skip it. Full writeup: AI-shared/bug-patterns.md, "Runtime
 * cache/scratch-file call sites default to DATA_DIR instead of TEMP_DIR".
 *
 * This patch removes the entire stale DATA_DIR . '/cache' directory left
 * behind at the old location so it doesn't keep sitting there as dead weight
 * in existing installs' DATA_DIR.
 *
 * Safe: every entry under DATA_DIR/cache is fully regenerable cache, rebuilt
 * automatically at the new TEMP_DIR location on next request/use - confirmed
 * no other code (including modules/Premium/) still writes there (grepped
 * clean). Idempotent: file_exists() guard means a second run is a no-op.
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

$old_cache_dir = DATA_DIR . '/cache';
if (file_exists($old_cache_dir)) {
    recursive_rmdir($old_cache_dir);
    PatchUtil::log("Removed stale cache dir at $old_cache_dir (now cached under TEMP_DIR instead)\n");
}
