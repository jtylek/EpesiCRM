<?php

DB::Execute("UPDATE contact_field SET extra=1");
DB::Execute("UPDATE contact_field SET extra=0 WHERE field IN ('Last Name','First Name','Company Name','Last Name','Email','Login','Username','Set Password','Confirm Password','Admin','Access')");

DB::Execute("UPDATE company_field SET extra=1");
DB::Execute("UPDATE company_field SET extra=0 WHERE field IN ('Company Name','Group')");

?>
