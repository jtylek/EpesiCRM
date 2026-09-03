<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// CKEditor -> Quill migration (AI-shared/dont-reintroduce.md): this
// module's requires() now lists Libs_QuillInstall instead of Libs_CKEditorInstall -
// requires() alone only affects fresh installs' dependency resolution, existing
// installs need this patch to actually pick up the new module.
ModuleManager::install('Libs/Quill');
