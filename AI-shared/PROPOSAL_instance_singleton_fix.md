# PROPOSAL — Fix `ModuleCommon::Instance()` for PHP 8 (branch `experiment/instance-singleton-fix`)

> **Status:** verified, reversible **proposal for Jasiek (the architect)**. NOT merged into
> `experiment/composer-deps` or `main`. Decision required: accept (and drop the targeted
> workarounds) or keep the per-site workarounds instead.
>
> Cross-reference: `MIGRATION_NOTES.md` §36 (root cause), §20 (storage symptom), §33 (EpesiStore
> symptom), §42 (unrelated fix merged into this branch so the test instance is usable).

---

## 1. Summary

`ModuleCommon::Instance()` is a per-module singleton used across the framework to resolve a
module's own paths (`get_data_dir()`, `get_module_dir()`, `get_module_template_dir()`). It relies
on a `static` local variable in an **inherited** method. **PHP 8.x changed the semantics of such
static variables: they are now SHARED across all inheriting subclasses** (PHP 7.4 gave each
subclass its own copy). As a result `SomeModuleCommon::Instance()` returns "whichever module was
loaded last", so path resolution silently points at the wrong module. This is the single root
cause of §20 (file storage looked under the wrong `data/<Module>/` prefix) and §33 (EpesiStore
loaded `ClientRequester.php` from the wrong dir), plus several latent sites.

This branch fixes it at the root by keying the singleton **per class** via late static binding
(`static::class`), restoring the exact PHP 7.4 behavior. The change is ~8 lines in one file.

---

## 2. The problem

### Symptoms already hit
- **§20** — saving an attachment wrote the file under `data/CRM_Tasks/` (or `CRM_Mail/`,
  `CRM_Roundcube/`…) instead of `data/Utils_FileStorage/`; view/download then looked under the
  wrong prefix → "file not found" / non-clickable links / "missing file".
- **§33** — opening EPESI Store fataled: `Failed opening required '.../ClientRequester.php'`
  because `Instance()->get_module_dir()` returned another module's directory.

### Latent sites (same pattern, not yet triggered)
`self::Instance()->get_data_dir()` / `get_module_dir()` is also used by:
`Base/EpesiStore`, `Base/Theme`, `Base/Print`, `Base/MainModuleIndicator`, `Utils/Attachment`,
`CRM/Fax`. Each is a load-order-dependent landmine that the root fix removes at once.

---

## 3. Root cause (empirically verified)

### Mechanism
1. `ModuleCommon::Instance()` (`include/module_common.php`) holds a `static $obj` local and is
   `final`, inherited by every `*Common` module class.
2. `include/module_manager.php` seeds it on **every module load**:
   `call_user_func(array($class.'Common', 'Instance'), $class)` → sets `$obj` to that module's name.
3. **PHP 7.4:** each subclass had its OWN `$obj` → `Utils_FileStorageCommon::Instance()` always
   returned FileStorage. Correct.
4. **PHP 8.x:** all subclasses SHARE one `$obj` → the last-seeded module wins →
   `Utils_FileStorageCommon::Instance()` returns whatever module was loaded last. Broken.

The code is byte-identical to vanilla 1.9.1 — only the language semantics changed between 7.4 and 8.x.

### Standalone proof (PHP 8.2.12, no Epesi)
A 2-class reproduction (`Base` with a `static $obj`; `ChildA`/`ChildB` inheriting) showed
`ChildA::Instance('Utils_FileStorage')` then `ChildB::Instance('CRM_Tasks')` made
`ChildA::Instance()` return `'CRM_Tasks'` — i.e. SHARED storage. The proposed per-class keying
made `ChildA::Instance()` return `'Utils_FileStorage'` again. (Test script was temporary; deleted.)

---

## 4. The fix

`include/module_common.php`:

```php
// BEFORE
public static final function Instance($arg=null) {
    static $obj;
    if(isset($arg)) $obj = $arg;
    elseif(is_string($obj)) { $cl = $obj.'Common'; $obj = new $cl($obj); }
    return $obj;
}

// AFTER
public static final function Instance($arg=null) {
    static $objs = array();
    $cls = static::class;                 // the actual subclass (late static binding)
    if(isset($arg)) $objs[$cls] = $arg;
    elseif(isset($objs[$cls]) && is_string($objs[$cls])) {
        $cl = $objs[$cls].'Common';
        $objs[$cls] = new $cl($objs[$cls]);
    }
    return $objs[$cls] ?? null;
}
```

### Why this is correct
- `static::class` resolves to the class the method was **called on**. PHP "forwarding" static
  calls (`self::`, `parent::`, `static::`, `forward_static_call`) PRESERVE that class, so a
  base-class method doing `self::Instance()` on a subclass still yields the subclass. Non-forwarding
  calls (`Foo::Instance()`) set it to `Foo`. Both match the seeding at `module_manager.php`.
- This restores **exactly** the PHP 7.4 per-class-singleton behavior — it is a behavior *restoration*,
  not new behavior.

---

## 5. Verification (this branch)

**Step 1 — no regressions (workarounds still in place):** re-tested Core on PHP 8.2 — Dashboard,
Contacts, Companies, Tasks, Print (PDF), attachments, Help, theme rendering. No errors; PHP log
clean. (The path-resolution features Print/Attachment/Help/Theme have NO workaround, so they
directly exercise the fix and passed.)

**Step 2 — root fix is sufficient (workarounds removed):** reverted the §20 narrow fix and the §33
`__DIR__` workaround on this branch; file view/download/get-link (§20) and EPESI Store (§33) work
**purely on the root fix**. → The single `Instance()` change replaces both targeted workarounds.

---

## 6. What this replaces

If accepted, these become redundant and are already reverted on this branch:
- **§20** narrow fix in `Utils/FileStorage/FileStorageCommon_0.php::get_storage_file_path()`
  (deterministic `DATA_DIR.'/'.self::module_name().'/'`).
- **§33** `__DIR__` workaround in `Base/EssClient/EssClientCommon_0.php::server()`.

And it pre-empts the 7 latent sites listed in §2 without per-site patches.

---

## 7. Data caveat (important)

- On a **clean production 7.4→8.2 migration**, files already live in `data/Utils_FileStorage/`, so
  the code fix alone is sufficient — no data migration.
- On **this local test instance**, files written while the bug was live got scattered under
  `data/CRM_Tasks/`, `data/CRM_Mail/`, etc. After the fix, reads correctly target
  `data/Utils_FileStorage/`; those older scattered files won't be found. This is a harmless test
  artifact (verified with fresh uploads). A production instance with scattered files (if any exist
  from running 8.x with the bug) would need a one-time move into `data/Utils_FileStorage/` — handle
  as a separate, careful data step.

---

## 8. Risk assessment

- **Low–moderate.** The fix restores known-good 7.4 semantics; it should *reduce* bugs, not add
  them. `Instance()` is called widely, hence the full Core re-test above.
- The only theoretical risk: code that accidentally relied on the *broken* shared behavior. None was
  found; our prior workarounds (§33 `__DIR__`) worked *around* the bug, not *with* it.

---

## 9. How to review / how to revert

- **Review:** `git diff experiment/composer-deps..experiment/instance-singleton-fix` — the core
  change is `include/module_common.php`; the two reverts are in `FileStorageCommon_0.php` and
  `EssClientCommon_0.php`; plus docs.
- **Revert (not merged):** simply don't merge; or delete the branch:
  `git push origin --delete experiment/instance-singleton-fix`. `main`/`composer-deps` are untouched.
- **Revert (if later merged):** `git revert <merge-commit>` restores the workarounds.

---

## 10. Decision for Jasiek

Accept the root fix in `Instance()` (per-class via `static::class`) and drop the §20/§33
workarounds — **or** keep the per-site workarounds and leave `Instance()` as-is. This proposal
recommends the root fix: one small, reversible, behavior-restoring change that fixes the whole class
of bugs at the source.
