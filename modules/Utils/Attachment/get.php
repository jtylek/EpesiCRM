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

if(!isset($_REQUEST['cid']) || !isset($_REQUEST['id']) || !isset($_REQUEST['path']))
	die('Invalid usage');
$cid = $_REQUEST['cid'];
$path = $_REQUEST['path'];
$id = $_REQUEST['id'];
$disposition = (isset($_REQUEST['view']) && $_REQUEST['view'])?'inline':'attachment';

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

$public = Module::static_get_module_variable($path,'public',false);
$protected = Module::static_get_module_variable($path,'protected',false);
$private = Module::static_get_module_variable($path,'private',false);
if(!Acl::is_user())
	die('Permission denied');
$row = DB::GetRow('SELECT uaf.attach_id, uaf.revision,uaf.original,ual.local,ual.permission,ual.permission_by FROM utils_attachment_file uaf INNER JOIN utils_attachment_link ual ON ual.id=uaf.attach_id WHERE uaf.id='.DB::qstr($id));
$original = $row['original'];
$rev = $row['revision'];
$local = $row['local'];
$filename = $local.'/'.$row['attach_id'].'_'.$rev;

if(!Base_AclCommon::i_am_admin() && $row['permission_by']!=Acl::get_user()) {
	if(($row['permission']==0 && !$public) ||
		($row['permission']==1 && !$protected) ||
		($row['permission']==2 && !$private))
		die('Permission denied');
}

require_once('mime.php');

if(headers_sent())
	die('Some data has already been output to browser, can\'t send file');

$t = time();
$remote_address = $_SERVER['REMOTE_ADDR'];
$remote_host = gethostbyaddr($_SERVER['REMOTE_ADDR']);
DB::Execute('INSERT INTO utils_attachment_download(attach_file_id,created_by,created_on,download_on,description,ip_address,host_name) VALUES (%d,%d,%T,%T,%s,%s,%s)',array($id,Acl::get_user(),$t,$t,$disposition,$remote_address,$remote_host));
$f_filename = DATA_DIR.'/Utils_Attachment/'.$filename;
if(!file_exists($f_filename))
	die('File doesn\'t exists');
$buffer = file_get_contents($f_filename);

if ($disposition=='inline' && Variable::get('utils_attachments_google_user') && strpos($original, '.doc')!==false) {
	$g_auth = Utils_AttachmentCommon::get_google_auth(Variable::get('utils_attachments_google_user'),Variable::get('utils_attachments_google_pass'));

	if ($g_auth) {
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"GData-Version: 3.0",
		);
		curl_setopt($curl, CURLOPT_URL, "https://docs.google.com/feeds/default/private/full?showfolders=true");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, false);
		$response = curl_exec($curl);

		$response = simplexml_load_string($response);

		foreach ($response->link as $l)
			if ($l['rel']=='http://schemas.google.com/g/2005#resumable-create-media') $upload_href = $l['href'];
	// Reported broken, use default href instead
		
		// Check if collection exists
		$folder_exists = false;
		$folder_name = 'EPESI Docs';
		foreach($response->entry as $file) {
			if ($file->content['type'] == 'application/atom+xml;type=feed' && $file->title == $folder_name) {
				$folder_exists = true;
				$collection_href = (string)($file->content['src']);
			}
		}

		//$upload_href = 'https://docs.google.com/feeds/default/private/full';
		$create_collection_href = 'https://docs.google.com/feeds/default/private/full';

		// Create collection if it doesn't exists
		if (!$folder_exists) {
			$body = '<?xml version="1.0" encoding="UTF-8"?><entry xmlns="http://www.w3.org/2005/Atom"><category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/docs/2007#folder"/><title>'.$folder_name.'</title></entry>';
			$headers = array(
				"Authorization: GoogleLogin auth=" . $g_auth,
				"GData-Version: 3.0",
				"Content-Length: ".strlen($body),
				"Content-Type: application/atom+xml",
			);

			curl_setopt($curl, CURLOPT_URL, $create_collection_href);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
			$response = curl_exec($curl);

			$response = simplexml_load_string($response);

			$collection_href = (string)($response->content['src']);
			
			foreach ($response->link as $l)
				if ($l['rel']=='self') $collection_self_href = $l['href'];
			
			// Make collection public
			$url = $collection_self_href.'/acl';
			$body = '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gAcl="http://schemas.google.com/acl/2007"><category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/acl/2007#accessRule"/><gAcl:withKey key="with link"><gAcl:role value="writer"/></gAcl:withKey><gAcl:scope type="default"/></entry>';
			$headers = array(
				"Authorization: GoogleLogin auth=" . $g_auth,
				"GData-Version: 3.0",
				"Content-Length: ".strlen($body),
				"Content-Type: application/atom+xml",
			);

			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
			$response = curl_exec($curl);
			
		}

		// Create the file
		$filename = 'EPESI Note '.$id;
		$body = '<?xml version="1.0" encoding="UTF-8"?><entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007"><category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/docs/2007#document"/><title>'.$filename.'</title></entry>';
		
		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"GData-Version: 3.0",
			"Content-Length: ".strlen($body),
			"Content-Type: application/atom+xml",
			"X-Upload-Content-Length: 0"
		);

		$upload_href = $create_collection_href;

		curl_setopt($curl, CURLOPT_URL, $upload_href);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		$response = curl_exec($curl);

		$response = simplexml_load_string($response);

		foreach ($response->link as $l) {
			if ($l['rel']=='edit-media') $edit_media_href = $l['href'];
			if ($l['rel']=='alternate') $view_doc = $l['href'];
		}
		$file_id = (string)($response->id);
			
		// Update contents of the file
		$body = '<?xml version="1.0" encoding="UTF-8"?><entry xmlns="http://www.w3.org/2005/Atom" xmlns:gd="http://schemas.google.com/g/2005"><category scheme="http://schemas.google.com/g/2005#kind"term="http://schemas.google.com/docs/2007#document"/><title>'.$filename.'</title></entry>';
		
		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"GData-Version: 3.0",
			"If-Match: *",
			"Content-Length: ".strlen($buffer),
			"Content-Type: application/msword",
		);

		curl_setopt($curl, CURLOPT_URL, $edit_media_href);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_PUT, true);
		curl_setopt($curl, CURLOPT_INFILE, fopen($f_filename, 'r'));
		curl_setopt($curl, CURLOPT_INFILESIZE, strlen($buffer));
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		$response = curl_exec($curl);
		
		// Add the file to collection
		$body = '<?xml version="1.0" encoding="UTF-8"?><entry xmlns="http://www.w3.org/2005/Atom"><id>'.$file_id.'</id></entry>';
		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"GData-Version: 3.0",
			"Content-Length: ".strlen($body),
			"Content-Type: application/atom+xml"
		);

		curl_setopt($curl, CURLOPT_URL, $collection_href);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_PUT, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		$response = curl_exec($curl);
		
		curl_close($curl);
		
		sleep(2);

		header('Location: '.$view_doc);
	}
}

header('Content-Type: '.get_mime_type($f_filename,$original));
header('Content-Length: '.strlen($buffer));
header('Content-disposition: '.$disposition.'; filename="'.$original.'"');
echo $buffer;

?>