<?php

@DB::Execute('DROP INDEX attach_id ON utils_attachment_file');
@DB::Execute('DROP INDEX revision ON utils_attachment_file');


?>
