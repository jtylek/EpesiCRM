<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']))
	die('Invalid usage');

$id = $_REQUEST['id'];

define('CID', $_REQUEST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user())
	die('Permission denied');

$note = DB::GetOne('SELECT text FROM utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id WHERE uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND ual.id=%d', array($id));
$files = DB::GetAssoc('SELECT id, original FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($id));
if (empty($files)) $files = null; // otherwise JS parses the whole object with all method calls

$result = array('note'=>$note, 'files'=>$files);

print(json_encode($result));

?>
