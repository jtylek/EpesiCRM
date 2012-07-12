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

function error_message() {
	Utils_FrontPageCommon::display(__( 'Error occured'), __( 'There was an error accessing Google Docs service.').'<br><br>'.__( 'Please contact your administrator.'));
	die();
}

define('CID', $cid);
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');
ModuleManager::load_modules();

if(!Acl::is_user())
	die('Permission denied');
$public = Module::static_get_module_variable($path,'public',false);
$protected = Module::static_get_module_variable($path,'protected',false);
$private = Module::static_get_module_variable($path,'private',false);

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
$buffer_size = strlen($buffer);

$g_auth = Utils_AttachmentCommon::get_google_auth();

if ($g_auth) {
	DB::StartTrans();
	$view_row = DB::GetRow('SELECT id, view_link FROM utils_attachment_googledocs WHERE note_id=%d', array($id));
	if (empty($view_row)) {
		$view_doc = null;
		DB::Execute('INSERT INTO utils_attachment_googledocs (view_link, note_id, doc_id) VALUES (%s, %d, %s)', array('', $id, ''));
		$uag_id = DB::Insert_ID('utils_attachment_googledocs','id');
	} else {
		$view_doc = $view_row[1]?$view_row[1]:'';
	}
	DB::CompleteTrans();
	$wait = 15;
	$time = microtime(true);
	if ($view_doc==='' && $wait > 0) {
		sleep(1);
		$view_doc = DB::GetOne('SELECT view_link FROM utils_attachment_googledocs WHERE note_id=%d', array($id));
		if (!$view_doc) $view_doc = '';
		$wait--;
	}

	if (!$view_doc) {
		if (!isset($uag_id)) {
			DB::Execute('INSERT INTO utils_attachment_googledocs (view_link, note_id, doc_id) VALUES (%s, %d, %s)', array('', $id, ''));
			$uag_id = DB::Insert_ID('utils_attachment_googledocs','id');
		}
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
		switch (true) {
			case strpos($row['original'], '.doc')!==false: $type = 'document'; $content_type = 'application/msword'; break;
			case strpos($row['original'], '.csv')!==false: $type = 'spreadsheet'; $content_type = 'text/csv'; break;
			// application/vnd.oasis.opendocument.spreadsheet
		}
		$body = '<?xml version="1.0" encoding="UTF-8"?><entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007"><category scheme="http://schemas.google.com/g/2005#kind" term="http://schemas.google.com/docs/2007#'.$type.'"/><title>'.$filename.'</title></entry>';
		
		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"GData-Version: 3.0",
			"Content-Length: ".strlen($body),
			"Content-Type: application/atom+xml",
			"X-Upload-Content-Type: ".$content_type,
			"X-Upload-Content-Length: ".$buffer_size
		);

		curl_setopt($curl, CURLOPT_URL, $upload_href);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		curl_setopt($curl, CURLOPT_HEADER, true); 

		$response = curl_exec($curl);
		$info = curl_getinfo($curl);

		curl_setopt($curl, CURLOPT_HEADER, false); 

		if ($info['http_code']!==200) error_message();
		preg_match("/Location: ([^\s]+)/i", $response, $matches);
		$location = $matches[1];
		$part = 0;
		$chunk = 524288;
		do {
			$from = $part*$chunk;
			$to = ($part+1)*$chunk - 1;
			$size = $chunk;
			if ($to > $buffer_size-1) {
				$to = $buffer_size-1;
				$size = $buffer_size % $chunk;
			}
			$headers = array(
				"Authorization: GoogleLogin auth=" . $g_auth,
				"GData-Version: 3.0",
				"Content-Length: ".$size,
				"Content-Type: ".$content_type,
				"Content-Range: bytes ".$from."-".$to."/".$buffer_size
			);
			curl_setopt($curl, CURLOPT_URL, $location);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_UPLOAD, true);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
			curl_setopt($curl, CURLOPT_PUT, false);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, substr($buffer, $part*$chunk, $chunk));
			curl_setopt($curl, CURLOPT_HEADER, true); 

			$response = curl_exec($curl);
			$info = curl_getinfo($curl);

			$header = substr($response, 0, $info['header_size']);
			$body = substr($response, -$info['download_content_length']);

			preg_match("/Location: ([^\s]+)/i", $header, $matches);
			if (isset($matches[1])) $location = $matches[1];
			$part++;
		} while($to < $buffer_size-1);

		curl_setopt($curl, CURLOPT_UPLOAD, false);
			curl_setopt($curl, CURLOPT_HEADER, false); 

		$response = simplexml_load_string($body);

		foreach ($response->link as $l) {
			if ($l['rel']=='edit-media') $edit_media_href = $l['href'];
			if ($l['rel']=='alternate') $view_doc = $l['href'];
			if ($l['rel']=='edit') $edit_href = $l['href'];
		}
		$file_id = (string)($response->id);
		if (!$file_id) error_message();
		
		// Add the file to collection
		$body = '<?xml version="1.0" encoding="UTF-8"?><entry xmlns="http://www.w3.org/2005/Atom"><id>'.$file_id.'</id></entry>';
		$headers = array(
			"Authorization: GoogleLogin auth=" . $g_auth,
			"GData-Version: 3.0",
			"Content-Length: ".strlen($body),
			"Content-Type: application/atom+xml"
		);

		curl_close($curl);
		$curl = curl_init();

		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		curl_setopt($curl, CURLOPT_URL, $collection_href);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_PUT, false);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		$response = curl_exec($curl);
		
		curl_close($curl);
		
		DB::Execute('UPDATE utils_attachment_googledocs SET view_link=%s, doc_id=%s WHERE id=%d', array($view_doc, $edit_href, $uag_id));
		
		sleep(3);
	}

	header('Location: '.$view_doc);
}
error_message();

?>