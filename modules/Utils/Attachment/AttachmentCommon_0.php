<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 * Other users can read notes if you set permission with allow_other method.
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package utils-attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_AttachmentCommon extends ModuleCommon {
	/**
	 * Example usage:
	 * Utils_AttachmentCommon::persistent_mass_delete(null,'CRM/Contact'); // deletes all entries located in CRM/Contact*** group
	 */
	public static function persistent_mass_delete($key=null,$group=null,$group_starts_with=true) {
		$wh = array();
		if(isset($key)) $wh[] = 'ual.attachment_key=\''.md5($key).'\'';
		if(isset($group)) {
			if($group_starts_with)
				$wh[] = 'ual.local LIKE \''.DB::addq($group).'%\'';
			else
				$wh[] = 'ual.local='.DB::qstr($group);
		}
		$ret = DB::Execute('SELECT ual.id,ual.local FROM utils_attachment_link ual WHERE '.implode(' AND ',$wh));
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
	
	public static function add($key,$group,$permission,$user,$other_read,$note=null,$oryg=null,$file=null) {
		DB::Execute('INSERT INTO utils_attachment_link(attachment_key,local,permission,permission_by,other_read) VALUES(%s,%s,%d,%d,%b)',array($key,$group,$permission,$user,$other_read));
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
}

?>
