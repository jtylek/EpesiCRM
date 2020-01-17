<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Remove old tables created by phpgacl - no longer needed
 * j@epe.si
 */
@DB::DropTable('acl');
@DB::DropTable('acl_sections');
@DB::DropTable('acl_seq');

@DB::DropTable('aco');
@DB::DropTable('aco_map');
@DB::DropTable('aco_sections');
@DB::DropTable('aco_sections_seq');
@DB::DropTable('aco_seq');

@DB::DropTable('aro');
@DB::DropTable('aro_groups');
@DB::DropTable('aro_groups_id_seq');
@DB::DropTable('aro_groups_map');
@DB::DropTable('aro_map');

@DB::DropTable('aro_sections');
@DB::DropTable('aro_sections_seq');
@DB::DropTable('aro_seq');

@DB::DropTable('axo');
@DB::DropTable('axo_groups');
@DB::DropTable('axo_groups_map');
@DB::DropTable('axo_map');
@DB::DropTable('axo_sections');

?>