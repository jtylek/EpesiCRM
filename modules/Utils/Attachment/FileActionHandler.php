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
    	$action = $request->get('action');
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
    			
    			if (!$filePack && $action == 'inline' && ($thumbnail = $this->createThumbnail($meta))) {
    				$mime = $thumbnail['mime'];
    				$filename = $thumbnail['filename'];
    				$buffer = $thumbnail['contents'];
    			}
    			else {
    				$mime = Utils_FileStorageCommon::get_mime_type($meta['file'], $meta['filename']);
    				$filename = $meta['filename'];
    				$buffer = Utils_FileStorageCommon::read_content($filestorageId);
    				
    				if($crypted) {
    					$buffer = Utils_AttachmentCommon::decrypt($buffer, $_SESSION['client']['cp'.$recordId]);
    					
    					if ($buffer===false) throw new Utils_FileStorage_Exception('File decryption error');
    				}    				
    			}
    			
    			$size += filesize($meta['file']);
    			@ini_set('memory_limit',ceil($size*2/1024/1024+64).'M');
    			
    			if ($filePack)
    				$zip->addFromString($filename, $buffer);
    		}
    		
    		if ($filePack)
    			$zip->close();
    		
    	} catch (Utils_FileStorage_Exception $ex) {
   			return Acl::i_am_admin()? new Response($ex->getMessage(), 400): false;
    	}    	
    	
    	$this->logFileAccessed($filestorageId, $action);
    	
    	if ($filePack) {
    		ob_start();
    		$fp = fopen($zipFilename, 'rb');
    		while (!feof($fp)) {
    			print fread($fp, 1024);
    		}
    		fclose($fp);
    		@unlink($zipFilename);
    		$buffer = ob_get_clean();
    		
    		$response = $this->createFileResponse($buffer, 'application/zip', 'attachment', "note_$recordId.zip", true);    		
    	}
    	else {
    		$response = $this->createFileResponse($buffer, $mime, $disposition, $filename);
    	}
    	
    	return $response;
    }
}
