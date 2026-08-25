<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(DB::is_mysql()) {
    @DB::Execute('ALTER DATABASE '.DATABASE_NAME.' CHARACTER SET utf8 COLLATE utf8_general_ci');
    $tabs = array('rc_cache','rc_cache_index','rc_cache_messages','rc_cache_thread','rc_contactgroupmembers','rc_contactgroups','rc_contacts','rc_dictionary','rc_identities','rc_searches','rc_session','rc_system','rc_users');
    foreach($tabs as $t) {
        @DB::Execute('ALTER TABLE '.$t.' CHARACTER SET utf8 COLLATE utf8_general_ci');
        @DB::Execute('ALTER TABLE '.$t.' CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci');
    }
}