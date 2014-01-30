<?php
/**
 * Use this module if you want to add attachments to some page.
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2012, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']) || !isset($_REQUEST['path']))
	die('Invalid usage');

$id = $_REQUEST['id'];

define('CID', $_REQUEST['cid']);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user())
	die(__('Permission denied'));


$note = DB::GetRow('SELECT ual.id,crypted,text, sticky, permission, permission_by, title, crypted FROM utils_attachment_link ual INNER JOIN utils_attachment_note uac ON uac.attach_id=ual.id WHERE uac.revision=(SELECT max(x.revision) FROM utils_attachment_note x WHERE x.attach_id=uac.attach_id) AND ual.id=%d', array($id));
$files = DB::GetAssoc('SELECT id, original FROM utils_attachment_file uaf WHERE uaf.attach_id=%d AND uaf.deleted=0', array($id));
if (empty($files)) $files = null; // otherwise JS parses the whole object with all method calls

if(!Base_AclCommon::i_am_admin() && $note['permission_by']!=Acl::get_user()) {
    if(($note['permission']==0 && !$public) ||
        ($note['permission']==1 && !$protected) ||
        ($note['permission']==2 && !$private))
        die(json_encode(array('error'=>__('Permission denied'))));
}

$pass = '';
if($note['crypted']) {
    $note_pass = Module::static_get_module_variable($_REQUEST['path'],'cp'.$note['id']);
    $decoded = Utils_AttachmentCommon::decrypt($note['text'],$note_pass);
    $pass = '*@#old@#*';
    if($decoded!==false) $note['text'] = $decoded;
    else die(json_encode(array('error'=>__('Invalid password'))));
}
$result = array('note'=>$note['text'], 'files'=>$files, 'sticky'=>$note['sticky'], 'permission'=>$note['permission'], 'crypted'=>$note['crypted'], 'title'=>$note['title'],'password'=>$pass);

print(json_encode($result));

?>
