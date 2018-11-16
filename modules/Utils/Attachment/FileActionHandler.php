<?php

require_once __DIR__ . '/../RecordBrowser/FileActionHandler.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Utils_Attachment_FileActionHandler
	extends Utils_RecordBrowser_FileActionHandler
{
    protected function getHandlingScript()
    {
        return 'modules/Utils/Attachment/file.php';
    }

    /**
     * Get Action urls for RB file leightbox
     *
     * @param int    $filestorageId Filestorage ID
     * @param string $tab           Recordset name. e.g. company
     * @param int    $recordId      Record ID
     * @param string $field         Field identifier. e.g. company_name
     * @param string $crypted       If file is crypted or not 
     *
     * @return array
     */
    public function getActionUrlsAttachment($filestorageId, $tab, $recordId, $field, $crypted)
    {
        $params = ['tab' => $tab, 'record' => $recordId, 'field' => $field, 'crypted' => $crypted, 'cid' => CID];
        return $this->getActionUrls($filestorageId, $params);
    }
   
    protected function hasAccess($action, $request)
    {
    	$crypted = $request->get('crypted');
    	$recordId = $request->get('record');
    	
    	if ($crypted && !isset($_SESSION['client']['cp'.$recordId]))
    		return false;
    		
    	return parent::hasAccess($action, $request);
    }
    
    protected function getFile(Request $request, $disposition)
    {
    	$filestorageId = $request->get('id');
    	$type = $request->get('action');
    	$crypted = $request->get('crypted');
    	$recordId = $request->get('record');
    	$filePack = is_array($filestorageId);
    	
    	try {
    		$filestorageIds = is_array($filestorageId)? $filestorageId: array($filestorageId);
    		
    		if ($filePack) {
    			$zipFilename = tempnam('tmp', 'zip');    			
    			
    			$zip = new ZipArchive();
    			//create the file and throw the error if unsuccessful
    			if ($zip->open($zipFilename, ZIPARCHIVE::OVERWRITE )!==true) 
    				throw new Utils_FileStorage_Exception("cannot open $zipFilename for writing - contact with administrator");
    		}
    		
    		$size = 0;
    		foreach ($filestorageIds as $filestorageId) {
    			$meta = Utils_FileStorageCommon::meta($filestorageId);
    			$buffer = Utils_FileStorageCommon::read_content($filestorageId);
    			
    			if($crypted) {
    				$buffer = Utils_AttachmentCommon::decrypt($buffer, $_SESSION['client']['cp'.$recordId]);
    				
    				if ($buffer===false) throw new Utils_FileStorage_Exception('File decryption error');
    			}
    			
    			$size += filesize($meta['file']);
    			@ini_set('memory_limit',ceil($size*2/1024/1024+64).'M');
    			
    			if ($filePack)
    				$zip->addFromString($meta['filename'], $buffer);
    		}
    		
    		if ($filePack)
    			$zip->close();
    		
    	} catch (Utils_FileStorage_Exception $ex) {
    		if (Base_AclCommon::i_am_admin()) {
    			return new Response($ex->getMessage(), 400);
    		}
    		return false;
    	}    	
    	
    	$type = self::actions[$type];
    	$time = time();
    	foreach ($filestorageIds as $filestorageId) {
    		$remote_address = get_client_ip_address();
    		$remote_host = gethostbyaddr($remote_address);
    		DB::Execute('INSERT INTO utils_filestorage_access(file_id,date_accessed,accessed_by,type,ip_address,host_name) '.
    				'VALUES (%d,%T,%d,%d,%s,%s)',array($filestorageId,$time,Acl::get_user()?Acl::get_user():0,$type,$remote_address,$remote_host));
    	}
    	
    	$response = new Response();
    	if (!$filePack) {	    	
	    	$mime = Utils_FileStorageCommon::get_mime_type($meta['file'], $meta['filename']);	    	
	    	
	    	$response->setContent($buffer);
	    	$response->headers->set('Content-Type', $mime);
	    	$response->headers->set('Content-Length', strlen($buffer));
	    	$response->headers->set('Content-Disposition', "$disposition; filename=\"$meta[filename]\"");	    	
    	}
    	else {
    		ob_start();
    		$fp = fopen($zipFilename, 'rb');
    		while (!feof($fp)) {
    			print fread($fp, 1024);
    		}
    		fclose($fp);
    		@unlink($zipFilename);
    		$buffer = ob_get_clean();
    		
    		$response->setContent($buffer);
    		$response->headers->set('Content-Type', 'application/zip');
    		$response->headers->set('Content-Length', strlen($buffer));
    		$response->headers->set('Content-Disposition', "attachment; filename=note_".$recordId.'.zip');
    		$response->headers->set('Pragma', 'no-cache');
    		$response->headers->set('Expires', '0');
    	}
    	return $response;
    }
}
