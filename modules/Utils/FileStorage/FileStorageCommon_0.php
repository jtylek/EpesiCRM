<?php
/**
 *
 * @author pbukowski@telaxus.com
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Utils
 * @subpackage FileStorage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * File storage mechanism with data deduplication.
 */
class Utils_FileStorageCommon extends ModuleCommon {

    const HASH_METHOD = 'sha512';

    //region admin caption
    public static function admin_caption()
    {
        return ['label' => __('Files'), 'section' => __('Data')];
    }
    //endregion

    /**
     * Get file link with leightbox popup to download, preview, etc
     *
     * @param int|array $id Filestorage ID or meta array
     * @param bool $nolink Do not create link, just show filename
     * @param bool $icon Do not add the file icon
     * @param array $action_urls Array with action urls. If empty then default
     *                           \Utils_FileStorage_ActionHandler::getActionUrls
     *                           will be used
     *
     * @return string File label with link
     */
    public static function get_file_label($id, $nolink = false, $icon = true, $action_urls = null, $label = null, $inline = false)
    {
    	$file_exists = self::file_exists($id, false);
    	
		if ($icon) {
			$icon_file = $file_exists ? 'z-attach.png': 'z-attach-off.png';
			$img_src = Base_ThemeCommon::get_template_file(self::module_name(), $icon_file);
			$icon_img = '<img src="' . $img_src . '" style="vertical-align:bottom">';
		}
		else {
			$icon_img = '';
		}
		
		$meta = null;
		try {
			$meta = is_numeric($id) ? self::meta($id) : $id;
		} catch (Exception $e) {
		}		
		
		if (!$filename = $label) {
			$filename = ($meta['filename']?? '') ?: htmlspecialchars('<' . __('missing filename') . '>');
		}
		
		if ($nolink || !$meta) {
			return $filename . ($file_exists ? '': ' [' . __('missing file') . ']');
		}
		
		$link_href = '';
		if ($file_exists) {
            $filesize = filesize_hr($meta['file']);
            $filetooltip = __('File size: %s', array($filesize)) . '<hr>' .
                           __('Uploaded by: %s', array(Base_UserCommon::get_user_label($meta['created_by'], true))) . '<br/>' .
                           __('Uploaded on: %s', array(Base_RegionalSettingsCommon::time2reg($meta['created_on']))) . '<br/>' .
                           __('Number of downloads: %d', array(self::get_downloads_count($id)));
            $link_href = Utils_TooltipCommon::open_tag_attrs($filetooltip) . ' '
                         . Utils_FileStorage_FileLeightbox::get_file_leightbox($meta, $action_urls);
        } else {
        	if (isset($meta['hash'])) {
	            $tooltip_text = __('Missing file: %s', array(substr($meta['hash'], 0, 32) . '...'));
	            $link_href = Utils_TooltipCommon::open_tag_attrs($tooltip_text);
        	}
        }
        
        $ret = '<a ' . $link_href . '>' . $icon_img . '<span class="file_name">' . $filename . '</span></a>';
        
        return $inline? $ret: '<div class="file_link">'.$ret.'</div>';
    }
    
    public static function get_file_inline_node($id, $action_urls = null, $max_width = '200px') 
    {
    	if (!self::file_exists($id, false)) return '';
    	
    	$meta = is_numeric($id) ? self::meta($id) : $id;

    	if ($action_urls === null) {
    		$action_urls = self::get_default_action_urls($meta['id']);
    	}
    	
    	$max_width .= is_numeric($max_width)? 'px': '';

        $type = self::get_mime_type($meta['file'], $meta['filename'], null, false);
    	switch ($type) {
    		case 'application/pdf':
    			if (!self::get_pdf_thumbnail_possible($meta)) {
    				$ret = '';
    				break;
    			}
			// image
            case 'image/jpeg':
            case 'image/gif':
            case 'image/png':
            case 'image/bmp':
				$ret = '<a href="' . $action_urls['preview'] . '" target="_blank"><img src="' . $action_urls['inline'] . '" class="file_inline" style="max-width: ' . $max_width . '" /></a>';
				break;

    		default:
                $ret = '';
    			break;
    	}
    	
    	return $ret;
    }
    
    public static function get_pdf_thumbnail_possible($meta) {
    	$mime = Utils_FileStorageCommon::get_mime_type($meta['file'], $meta['filename'], null, false);

    	return $mime == 'application/pdf' && class_exists('Imagick');
    }
    
    public static function get_default_action_urls($meta) {
    	$id = is_numeric($meta)? $meta: $meta['id'];
    	
    	$default_action_handler = new Utils_FileStorage_ActionHandler();
    	return $default_action_handler->getActionUrls($id);
    }
    
    public static function get_storage_file_path($hash)
    {
        $dirs = str_split(substr($hash, 0, 5));
        $path = self::Instance()->get_data_dir() . implode(DIRECTORY_SEPARATOR, $dirs);
        @mkdir($path, 0770, true);
        return $path . DIRECTORY_SEPARATOR . substr($hash, 5);
    }

    public static function hash_content(& $content)
    {
        $hash = hash(self::HASH_METHOD, $content);
        return $hash;
    }

    public static function hash_file($file)
    {
        $hash = hash_file(self::HASH_METHOD, $file);
        return $hash;
    }
    
    public static function get_downloads_count($meta) {
    	$id = is_numeric($meta)? $meta: $meta['id'];
    	
    	return DB::GetOne('SELECT COUNT(*) FROM utils_filestorage_access WHERE file_id=%d', array($id))?: 0;
    }

    /**
     * Add data to the filestorage
     *
     * @param string $hash File contents hash
     * @param callable $store_closure Callable that will save data to the specified path
     * <code>
     *   function ($path) use ($some_data) {
     *       return file_put_contents($path, $some_data);
     *   }
     * </code>
     * @param string $filename Original filename - required to guess mime type
     *
     * @return int File id in the database
     * @throws Utils_FileStorage_WriteError
     */
    public static function add_data($hash, $store_closure, $filename = '')
    {
        $path = self::get_storage_file_path($hash);
        $file_id = DB::GetOne('SELECT id FROM utils_filestorage_files WHERE hash=%s',array($hash));
        if (!file_exists($path)) {
            if (!$store_closure($path)) {
                throw new Utils_FileStorage_WriteError('Storing data failed');
            }
        }
        if (!$file_id) {
            $size = filesize($path);
            $type = self::get_mime_type($path, $filename);
            DB::Execute('INSERT INTO utils_filestorage_files(hash,size,type) VALUES(%s,%d,%s)', array($hash,$size,$type));
            $file_id = DB::Insert_ID('utils_filestorage_files', 'id');
            if (!$file_id) {
                throw new Utils_FileStorage_WriteError('Writing file hash to database failed');
            }
        }
        return $file_id;
    }

    /**
     * Add content to the filestorage
     *
     * @param string $content Content to save
     * @param string $filename Original filename - required to guess mime type
     *
     * @return int File id in the database
     */
    public static function add_data_from_content(& $content, $filename = '')
    {
        $hash = self::hash_content($content);
        $store_closure = function ($path) use (& $content) {
            return file_put_contents($path, $content);
        };
        return self::add_data($hash, $store_closure, $filename);
    }

    /**
     * Add file to the filestorage
     *
     * @param string $file File path to save
     * @param string $filename Original filename - required to guess mime type
     *
     * @return int File id in the database
     */
    public static function add_data_from_file($file, $filename = '')
    {
        $hash = self::hash_file($file);
        $store_closure = function ($path) use ($file) {
            return copy($file, $path);
        };
        return self::add_data($hash, $store_closure, $filename);
    }

    /**
     * Write file to the storage using store callback.
     * Use write_content or write_file if you are not sure what this function does.
     *
     * @param int        $file_id    File id - retrieved by self::add_data* methods
     * @param string     $filename   Original filename
     * @param string     $link       Unique string identifier to the file - not required
     * @param string     $backref    Reference to the "place" where file is used. Use rb: prefix for recordbrowser. E.g. rb:contact/33
     * @param string|int $created_on Date in unix timestamp or string for database
     * @param int        $created_by User ID
     *
     * @return int       Filestorage ID
     * @throws Utils_FileStorage_WriteError
     * @throws Utils_FileStorage_LinkDuplicate
     */
    public static function write_metadata($file_id, $filename, $link = null,
                                          $backref = null, $created_on = null, $created_by = null)
    {
        if ($link && self::get_storage_id_by_link($link, false)) throw new Utils_FileStorage_LinkDuplicate($link);

        if ($created_on === null) {
            $created_on = time();
        }
        if ($created_by === null) {
            $created_by = Base_AclCommon::get_user();
        }
        $data = [$filename, $link, $backref, $created_on, $created_by, $file_id];
        DB::Execute('INSERT INTO utils_filestorage(filename,link,backref,created_on,created_by,file_id) VALUES(%s,%s,%s,%T,%d,%d)', $data);
        $id = DB::Insert_ID('utils_filestorage', 'id');
        if (!$id) {
            throw new Utils_FileStorage_WriteError('Saving file metadata failed');
        }
        return $id;
    }

    /**
     * Update filestorage metadata. Leave value as false to not update it
     *
     * @param int $id Filestorage ID
     * @param bool|string $filename New Filename
     * @param bool|string $link New Unique link
     * @param bool|string $backref New backref
     * @param bool|int|string $created_on Timestamp in seconds or string
     * @param bool|int $created_by User ID
     * @param bool|int $deleted Deleted - use 0 value to set not deleted
     */
    public static function update_metadata($id, $filename = false, $link = false,
                                           $backref = false, $created_on = false,
                                           $created_by = false, $deleted = false)
    {
        $fields = [];
        $values = [];
        if (false !== $filename) {
            $fields[] = 'filename=%s';
            $values[] = $filename;
        }
        if (false !== $backref) {
            $fields[] = 'backref=%s';
            $values[] = $backref;
        }
        if (false !== $created_on) {
            $fields[] = 'created_on=%T';
            $values[] = $created_on;
        }
        if (false !== $created_by) {
            $fields[] = 'created_by=%d';
            $values[] = $created_by;
        }
        if (false !== $deleted) {
            $fields[] = 'deleted=%d';
            $values[] = $deleted;
        }
        if (!empty($fields)) {
            $fields = implode(',', $fields);
            $values[] = $id;
            DB::Execute("UPDATE utils_filestorage SET $fields WHERE id=%d", $values);
        }
        if (false !== $link) {
            self::add_link($link, $id);
        }
    }

    /**
     * @param string     $filename   Original filename
     * @param string     $content    Content of the file
     * @param string     $link       Unique string identifier to the file - not required
     * @param string     $backref    Reference to the "place" where file is used. Use rb: prefix for recordbrowser. E.g. rb:contact/33
     * @param string|int $created_on Date in unix timestamp or string for database
     * @param int        $created_by User ID
     *
     * @return int Filestorage ID
     * @throws Utils_FileStorage_LinkDuplicate
     */
    public static function write_content($filename, $content, $link = null, $backref = null,
                                         $created_on = null, $created_by = null)
    {
        if ($link && self::get_storage_id_by_link($link, false)) throw new Utils_FileStorage_LinkDuplicate($link);
        $file_id = self::add_data_from_content($content, $filename);
        return self::write_metadata($file_id, $filename, $link, $backref, $created_on, $created_by);
    }

    /**
     * @param string     $filename   Original filename
     * @param string     $file       Filepath on the system to copy from
     * @param string     $link       Unique string identifier to the file - not required
     * @param string     $backref    Reference to the "place" where file is used. Use rb: prefix for recordbrowser. E.g. rb:contact/33
     * @param string|int $created_on Date in unix timestamp or string for database
     * @param int        $created_by User ID
     *
     * @return int Filestorage ID
     * @throws Utils_FileStorage_LinkDuplicate
     */
    public static function write_file($filename, $file, $link = null, $backref = null,
                                      $created_on = null, $created_by = null)
    {
        if ($link && self::get_storage_id_by_link($link, false)) throw new Utils_FileStorage_LinkDuplicate($link);
        $file_id = self::add_data_from_file($file, $filename);
        return self::write_metadata($file_id, $filename, $link, $backref, $created_on, $created_by);
    }

    /**
     * Add multiple files, clone file if file id is provided.
     * May be used to update backref for all files.
     *
     * @param array $files array of existing filestorage ids or array with values for the new file
     * @param string|null $backref Backref for all files
     * @return array Newly created Filestorage Ids sorted in ascending order
     */
    public static function add_files(array $files, $backref = null)
    {
        $filestorageIds = [];
        foreach ($files as $file) {
            if (is_array($file)) {
                $filename = $file['filename'];
                $created_by = isset($file['created_by']) ? $file['created_by'] : null;
                $created_on = isset($file['created_on']) ? $file['created_on'] : null;
                $link = isset($file['link']) ? $file['link'] : null;
                if (isset($file['file'])) {
                    $filestorageIds[] = self::write_file($filename, $file['file'],
                                                         $link, $backref,
                                                         $created_on, $created_by);
                } elseif (isset($file['content'])) {
                    $filestorageIds[] = self::write_content($filename, $file['content'],
                                                         $link, $backref,
                                                         $created_on, $created_by);
                }
            } else {
                $meta = self::meta($file, false);
                if (!$meta['backref'] && $backref !== null) {
                    self::update_metadata($meta['id'], false, false, $backref);
                } elseif ($backref === null || $meta['backref'] != $backref) {
                    $file = self::write_file($meta['filename'], $meta['file'],
                                             null, $backref);
                }
                $filestorageIds[] = $file;
            }
        }
        sort($filestorageIds);
        return $filestorageIds;
    }


    /**
     * Replace Filestorage content with a new one.
     * Deleting orphan will remove it's original content if it is not used
     * anywhere else - useful to encrypt the content of the file.
     *
     * @param int    $id            Filestorage ID
     * @param string $content       New content of the file
     * @param bool   $delete_orphan If original content of the file was used only
     *                              in this file, then remove this content
     */
    public static function set_content($id, $content, $delete_orphan = true)
    {
        $file_id = self::add_data_from_content($content);
        self::set_file_id($id, $file_id, $delete_orphan);
    }

    /**
     * Assign File ID to Filestorage ID
     *
     * @param int  $id
     * @param int  $file_id
     * @param bool $delete_orphan If previous File ID was used only by this
     *                            filestorage then it from the filesystem
     */
    public static function set_file_id($id, $file_id, $delete_orphan = true)
    {
        $meta = self::meta($id);
        $id = $meta['id']; // force numeric id - not link
        $old_file_id = $meta['file_id'];
        DB::Execute('UPDATE utils_filestorage SET file_id=%d WHERE id=%d', array($file_id, $id));
        if ($delete_orphan) {
            self::delete_orphaned_file($old_file_id);
        }
    }

    /**
     * Delete File from the filesystem if it is not used by any filestorage
     *
     * @param int $file_id Unique File ID - not filestorage!
     */
    public static function delete_orphaned_file($file_id)
    {
        $used = DB::GetOne('SELECT 1 FROM utils_filestorage WHERE file_id=%d', array($file_id));
        if (!$used) {
            self::delete_file_on_disk($file_id);
            DB::Execute('DELETE FROM utils_filestorage_files WHERE id=%d', array($file_id));
        }
    }

    /**
     * Retrieve meta info about the file
     *
     * @param int|string $id Filestorage ID or unique link string
     * @param bool $use_cache Use cache or not
     *
     * @return array Metadata about the file.
     *               Keys in array: hash, file, filename, link, backref,
     *               created_on, created_by, deleted, file_id
     * @throws Utils_FileStorage_FileNotFound
     * @throws Utils_FileStorage_StorageNotFound
     */
    public static function meta($id, $use_cache = true)
    {
        static $meta_cache = array();
        if (!is_numeric($id)) {
            $id = self::get_storage_id_by_link($id, true, true);
        }
        if ($use_cache && isset($meta_cache[$id])) {
            return $meta_cache[$id];
        }

        $meta = DB::GetRow('SELECT s.*, f.hash, f.type, f.size FROM (SELECT * FROM utils_filestorage WHERE id=%d) s LEFT JOIN utils_filestorage_files f ON s.file_id=f.id', array($id));
        if (!$meta) {
            throw new Utils_FileStorage_StorageNotFound('Exception - DB storage object not found: ' . $id);
        }
        if (!isset($meta['hash']) || empty($meta['hash'])) {
            throw new Utils_FileStorage_FileNotFound('File object does not have corresponding file hash');
        }
        $meta['file'] = self::get_storage_file_path($meta['hash']);
        $meta_cache[$id] = $meta;
        return $meta;
    }

    /**
     * Check if file exists
     *
     * @param int|array $id              Filestorage ID or meta array
     * @param bool      $throw_exception Throw exception on missing file or return false
     *
     * @return bool True if file exists, false otherwise
     * @throws Utils_FileStorage_FileNotFound May be thrown if $throw_exception set to true
     */
    public static function file_exists($id, $throw_exception = false)
    {
    	try {
    		$meta = is_numeric($id) ? self::meta($id) : $id;
    	} catch (Exception $e) {
    		if ($throw_exception)
    			throw $e;
    		else 
    			return false;
    	}        
        
        if (!file_exists($meta['file'])) {
            if ($throw_exception) {
                throw new Utils_FileStorage_FileNotFound('Exception - file not found: ' . $meta['file']);
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Get contents of the file
     *
     * @param int|string $id Filestorage ID or unique link
     *
     * @return string Contents of the file
     */
    public static function read_content($id)
    {
        $meta = self::meta($id);
        self::file_exists($meta, true);
        return file_get_contents($meta['file']);
    }

    /**
     * Add unique string identifier to the file
     *
     * @param string $link Unique link
     * @param int    $id   Filestorage ID
     *
     * @throws Utils_FileStorage_LinkDuplicate
     */
    public static function add_link($link, $id)
    {
        try {
            $id2 = self::get_storage_id_by_link($link, false, true);
            if ($id2 != $id) {
                throw new Utils_FileStorage_LinkDuplicate($link);
            }
        } catch (Utils_FileStorage_LinkNotFound $e) {
            DB::Execute('UPDATE utils_filestorage SET link=%s WHERE id=%d', array($link, $id));
        }
    }

    /**
     * Get Filestorage ID by link
     *
     * @param string $link            Unique link
     * @param bool   $use_cache       Use cache or not
     * @param bool   $throw_exception Throw exception if link is not found
     *
     * @return int Filestorage ID
     * @throws Utils_FileStorage_LinkNotFound
     */
    public static function get_storage_id_by_link($link, $use_cache = true, $throw_exception = false)
    {
        static $cache = array();
        if (!$use_cache || !isset($cache[$link])) {
            $cache[$link] = DB::GetOne('SELECT id FROM utils_filestorage WHERE link=%s', array($link));
            if (!$cache[$link] && $throw_exception) {
                throw new Utils_FileStorage_LinkNotFound($link);
            }
        }
        return $cache[$link];
    }

    /**
     * Mark file as deleted. Does not remove any content!
     *
     * @param int|string $id Filestorage ID or unique link
     */
    public static function delete($id)
    {
        DB::StartTrans();
        if (!is_numeric($id)) {
            $id = self::get_storage_id_by_link($id, false);
        }
        if ($id) {
            DB::Execute('UPDATE utils_filestorage SET deleted=1 WHERE id=%d', array($id));
        }
        DB::CompleteTrans();
    }

    /**
     * Delete file from disk.
     * Mark all filestorages that used this file as deleted.
     * Use with caution!
     *
     * @param int $file_id Unique File ID - not Filestorage ID!
     */
    public static function delete_file_on_disk($file_id)
    {
        $hash = self::get_hash_by_file_id($file_id);
        if (!$hash) {
            return;
        }

        $filepath = self::get_storage_file_path($hash);
        @unlink($filepath);
        // remove leftover dirs if empty
        for ($i = 0; $i <= 4; $i++) {
            $filepath = dirname($filepath);
            if (!@rmdir($filepath)) {
                break;
            }
        }
        DB::Execute('UPDATE utils_filestorage_files SET deleted=1 WHERE id=%d', array($file_id));
        DB::Execute('UPDATE utils_filestorage SET deleted=1 WHERE file_id=%d', array($file_id));
    }

    /**
     * Get file hash for unique file.
     *
     * @param int $file_id Unique File ID - not Filestorage ID!
     *
     * @return string File contents hash
     */
    public static function get_hash_by_file_id($file_id)
    {
        $hash = DB::GetOne('SELECT hash FROM utils_filestorage_files WHERE id=%d', array($file_id));
        return $hash;
    }

    /**
     * Get filestorage object
     *
     * @param int|string $id Filestorage ID or unique link string
     *
     * @return Utils_FileStorage_Object
     */
    public static function get($id)
    {
        return new Utils_FileStorage_Object($id);
    }


    public static function get_mime_type($file, $original, $buffer = null, $encoding = true)
    {
    	$return = null;

    	//new method, but not compiled in by default
    	if (extension_loaded('fileinfo') && $encoding) {
    		$fff = new finfo(FILEINFO_MIME);
    		if ($file) {
    			$return = $fff->file($file);
    		} elseif ($buffer) {
    			$return = $fff->buffer($buffer);
    		}
    		unset($fff);
    		if ($return) {
    			return $return;
    		}
    	}

    	$delete_file = false;
    	if (!$file) {
    		$file = tempnam(sys_get_temp_dir(), 'mime');
    		if (file_put_contents($file, $buffer)) {
    			$delete_file = true;
    		} else {
    			$file = null;
    		}
    	}

    	if ($file) {

    		// unix system
    		$ret = 0;
    		ob_start();
            if($encoding) {
                @passthru("file -bi {$file}", $ret);
            } else {
                @passthru("file -b --mime-type {$file}", $ret);
            }
    		$output = ob_get_clean();
    		if ($ret == 0) {
    			$return = trim($output);
    		}

    		// mime_content_type
    		if (!$return) {
    			if (function_exists('mime_content_type')) {
    				$return = mime_content_type($file);
    			}
    		}
    	}

    	if ($delete_file) {
    		@unlink($file);
    	}
    	if ($return) {
    		return $return;
    	}

    	preg_match("/\.(.*?)$/", $original, $m);
    	if (!isset($m[1])) {
    		return "application/octet-stream";
    	}
    	switch (strtolower($m[1])) {
    		// case "js": return "application/javascript";
    		// case "json": return "application/json";
    		case "jpg":
    		case "jpeg":
    		case "jpe":
    			return "image/jpeg";
    		case "xlsx":
    		case "xls":
    			return "application/vnd.ms-excel";
    		case "docx":
    		case "txt":
    			return "text/plain";
    		case "doc":
    			return "application/msword";
    		case "pdf":
    			return "application/pdf";
    		case "png":
    		case "gif":
    		case "bmp":
    			return "image/" . strtolower($m[1]);
    			// case "css": return "text/css";
    			// case "xml": return "application/xml";
    		case "html":
    		case "htm":
    		case "php":
    			return "text/html";
    		default:
    			return "application/octet-stream";
    	}
    }
}

class Utils_FileStorage_Exception extends Exception {}
class Utils_FileStorage_StorageNotFound extends Utils_FileStorage_Exception {}
class Utils_FileStorage_LinkNotFound extends Utils_FileStorage_Exception {}
class Utils_FileStorage_LinkDuplicate extends Utils_FileStorage_Exception {}
class Utils_FileStorage_FileNotFound extends Utils_FileStorage_Exception {}
class Utils_FileStorage_WriteError extends Utils_FileStorage_Exception {}

class Utils_FileStorage_Object {
    private $id;
    public function __construct($id) {
        $this->id = $id;
    }

    public function fp() {
        $meta = Utils_FileStorageCommon::meta($this->id);
        return fopen($meta['file'],'rb');
    }

    public function file() {
        $meta = Utils_FileStorageCommon::meta($this->id);
        return $meta['file'];
    }

    public function read() {
        return Utils_FileStorageCommon::read_content($this->id);
    }

    public function delete() {
        return Utils_FileStorageCommon::delete($this->id);
    }

    public function meta() {
        return Utils_FileStorageCommon::meta($this->id);
    }

    public function add_link($link) {
        $meta = Utils_FileStorageCommon::meta($this->id,false);
        return Utils_FileStorageCommon::add_link($link,$meta['id']);
    }
}

?>