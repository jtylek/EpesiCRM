<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_AttachmentCommon extends ModuleCommon {

	private static function get_where($group,$group_starts_with=true) {
		if($group_starts_with)
			return 'ual.local LIKE \''.DB::addq($group).'%\'';
		else
			return 'ual.local='.DB::qstr($group);
	}
	/**
	 * Example usage:
	 * Utils_AttachmentCommon::persistent_mass_delete('CRM/Contact'); // deletes all entries located in CRM/Contact*** group
	 */
	public static function persistent_mass_delete($group,$group_starts_with=true) {
		$ret = DB::Execute('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE '.self::get_where($group,$group_starts_with));
		while($row = $ret->FetchRow()) {
			$id = $row['id'];
			$local = $row['local'];
			DB::Execute('DELETE FROM utils_attachment_note WHERE attach_id=%d',array($id));
			$rev = DB::GetOne('SELECT count(*) FROM utils_attachment_file WHERE attach_id=%d',array($id));
			$file_base = self::Instance()->get_data_dir().$local.'/'.$id.'_';
			for($i=0; $i<$rev; $i++) {
				@unlink($file_base.$i);
			}
			DB::Execute('DELETE FROM utils_attachment_file WHERE attach_id=%d',array($id));
			DB::Execute('DELETE FROM utils_attachment_link WHERE id=%d',array($id));
		}
	}
	
	public static function add($group,$permission,$user,$note=null,$oryg=null,$file=null,$func=null,$args=null) {
		DB::Execute('INSERT INTO utils_attachment_link(local,permission,permission_by,func,args) VALUES(%s,%d,%d,%s,%s)',array($group,$permission,$user,serialize($func),serialize($args)));
		$id = DB::Insert_ID('utils_attachment_link','id');
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$oryg,$user));
		DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$note,$user));
		if($file) {
			$local = self::Instance()->get_data_dir().$group;
			@mkdir($local,0777,true);
			rename($file,$local.'/'.$id.'_0');
		}
		return $id;
	}

	public static function count($group=null,$group_starts_with=true) {
		return DB::GetOne('SELECT count(ual.id) FROM utils_attachment_link ual WHERE ual.deleted=0 AND '.self::get_where($group,$group_starts_with));
	}

	public static function search_group($group,$word){
		$ret = array();
		$r = DB::Execute('SELECT ual.local,ual.id FROM utils_attachment_link ual WHERE (ual.permission<2 OR ual.permission_by='.Acl::get_user().') AND ual.deleted=0 AND '.
				'(0!=(SELECT count(uan.id) FROM utils_attachment_note AS uan WHERE uan.attach_id=ual.id AND uan.text LIKE '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uan.revision=(SELECT MAX(xxx.revision) FROM utils_attachment_note xxx WHERE xxx.attach_id=ual.id)) OR '.
				'0!=(SELECT count(uaf.id) FROM utils_attachment_file AS uaf WHERE uaf.attach_id=ual.id AND uaf.original LIKE '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uaf.revision=(SELECT MAX(xxx2.revision) FROM utils_attachment_file xxx2 WHERE xxx2.attach_id=ual.id))) '.
				'AND '.self::get_where(null,$group),array($word));
		while($row = $r->FetchRow())
			$ret[] = array('group'=>$row['local'],'view_href'=>'');
		return $ret;
	}

}

?>
