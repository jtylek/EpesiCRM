<?php

/**
 * Adds `name` (the patch's relative file path) and `date` (when mark_applied() ran) to the
 * `patches` table, alongside its existing `id` (md5 of that same path) primary key. `id`
 * stays the sole lookup key - PatchesDB::was_applied()/PatchUtil::apply_new() are unchanged,
 * so this cannot affect which patches run or when.
 *
 * `date` cannot be backfilled for rows already in this table: no timestamp was ever recorded
 * before this patch, so there is no real "date applied" to recover, and this leaves it NULL
 * rather than fabricate one. Every row inserted from here on (a real Patch::apply(), or a
 * bulk PatchUtil::mark_applied() run at fresh-install time) gets a real date.
 *
 * `name` CAN be backfilled for already-applied patches, by recomputing every currently-known
 * patch file's identifier the same way Patch::get_identifier() does and matching it against
 * the id already stored - see include/patches.php. A row whose patch file no longer exists in
 * this codebase (patches are never deleted once shipped, per CLAUDE.md, so this should not
 * happen in practice) is left with name NULL rather than guessed at.
 *
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

PatchUtil::db_add_column('patches', 'name', 'C(255)');
PatchUtil::db_add_column('patches', 'date', 'T');

foreach (PatchUtil::list_patches(false) as $patch) {
    DB::Execute(
        'UPDATE patches SET name=%s WHERE id=%s AND name IS NULL',
        array($patch->get_file(), $patch->get_identifier())
    );
}
