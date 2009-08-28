<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
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
	
	public static function call_user_func_on_file($group,$func,$group_starts_with=true) {
		$ret = DB::Execute('SELECT ual.id,ual.local, f.original, f.revision as rev
				    FROM utils_attachment_link ual INNER JOIN utils_attachment_file f ON (f.attach_id=ual.id AND f.revision=(SELECT max(revision) FROM utils_attachment_file WHERE attach_id=ual.id))
				    WHERE ual.deleted=0 AND '.self::get_where($group,$group_starts_with));
		while($row = $ret->FetchRow()) {
			$id = $row['id'];
			$local = $row['local'];
			$rev = $row['rev'];
			$file = self::Instance()->get_data_dir().$local.'/'.$id.'_'.$rev;
			if(file_exists($file))
    				call_user_func($func,$id,$rev,$file,$row['original']);
		}
	}
	
	public static function add($group,$permission,$user,$note=null,$oryg=null,$file=null,$func=null,$args=null,$add_func=null,$add_args=array()) {
		DB::Execute('INSERT INTO utils_attachment_link(local,permission,permission_by,func,args) VALUES(%s,%d,%d,%s,%s)',array($group,$permission,$user,serialize($func),serialize($args)));
		$id = DB::Insert_ID('utils_attachment_link','id');
		if($oryg===null) $oryg = '';
		DB::Execute('INSERT INTO utils_attachment_file(attach_id,original,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$oryg,$user));
		DB::Execute('INSERT INTO utils_attachment_note(attach_id,text,created_by,revision) VALUES(%d,%s,%d,0)',array($id,$note,$user));
		if($file) {
			$local = self::Instance()->get_data_dir().$group;
			@mkdir($local,0777,true);
			$dest_file = $local.'/'.$id.'_0';
			rename($file,$dest_file);
			if ($add_func) call_user_func($add_func,$id,0,$dest_file,$oryg,$add_args);
		}
		return $id;
	}

	public static function count($group=null,$group_starts_with=true) {
		return DB::GetOne('SELECT count(ual.id) FROM utils_attachment_link ual WHERE ual.deleted=0 AND '.self::get_where($group,$group_starts_with));
	}

	public static function search_group($group,$word,$view_func=false) {
		$ret = array();
		$r = DB::Execute('SELECT ual.local,ual.id,ual.func,ual.args FROM utils_attachment_link ual WHERE (ual.permission<2 OR ual.permission_by='.Acl::get_user().') AND ual.deleted=0 AND '.
				'(0!=(SELECT count(uan.id) FROM utils_attachment_note AS uan WHERE uan.attach_id=ual.id AND uan.text LIKE '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uan.revision=(SELECT MAX(xxx.revision) FROM utils_attachment_note xxx WHERE xxx.attach_id=ual.id)) OR '.
				'0!=(SELECT count(uaf.id) FROM utils_attachment_file AS uaf WHERE uaf.attach_id=ual.id AND uaf.original LIKE '.DB::Concat(DB::qstr('%'),'%s',DB::qstr('%')).' AND uaf.revision=(SELECT MAX(xxx2.revision) FROM utils_attachment_file xxx2 WHERE xxx2.attach_id=ual.id))) '.
				'AND '.self::get_where($group),array($word,$word));
		while($row = $r->FetchRow()) {
			$view = '';
			if($view_func) {
				$func = unserialize($row['func']);
				if($func) {
					$view = call_user_func_array($func,unserialize($row['args']));
				}
				if(!$view) continue;
				$ret[$row['id']] = $view;
			} else {
				$ret[] = array('id'=>$row['id'],'group'=>$row['local']);
			}
		}
		return $ret;
	}
	
	public static function search($word) {
		$attachs = Utils_AttachmentCommon::search_group('',$word,true);
		return $attachs;
	}

}

?>
