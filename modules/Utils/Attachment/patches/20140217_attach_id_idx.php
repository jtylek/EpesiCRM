<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

@DB::CreateIndex('attach_id_idx','utils_attachment_file','attach_id');
