<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage comment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_CommentCommon extends ModuleCommon {
	/**
	 * Deletes comment by comment id.
	 * All replies to this post are also deleted.
	 * 
	 * @param integer post id
	 */
	public static function delete_post($id){
		if (!$id)
			trigger_error('Invalid action: delete post('.$id.').');
		DB::Execute('DELETE FROM comment WHERE id=%d',$id);
		DB::Execute('DELETE FROM comment_report WHERE id=%d',$id);
		$recSet = DB::Execute('SELECT id FROM comment WHERE parent=%d',$id);
		while($row=$recSet->FetchRow()) self::delete_post($row['id']);
		return false;
	}
	
	/**
	 * Deletes comments by comment group id.
	 * 
	 * @param string comment group id
	 */
	public static function delete_posts_by_topic($topic){
		if (!$topic)
			trigger_error('Invalid action: delete post('.$topic.').');
		$ret = DB::Execute('SELECT id FROM comment WHERE topic=%s',$topic);
		while ($row=$ret->FetchRow()) self::delete_post($row['id']);
	}
}

?>
